<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Services\IssueExpenseService;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IssueController extends Controller
{
    public function __construct(private readonly IssueExpenseService $expenses) {}

    public function index(Request $r)
    {
        return Issue::where('portfolio_id', $r->user()->portfolio()->id)
            ->when($r->filled('property_id'), fn ($query) => $query->where('property_id', $r->integer('property_id')))
            ->with(['property', 'assignedContact', 'expenseTransaction'])
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->latest()->paginate(30);
    }

    public function show(Request $r, Issue $issue)
    {
        $this->ensureOwned($r, $issue);

        return $issue->load(['property', 'assignedContact', 'expenseTransaction']);
    }

    public function store(Request $r)
    {
        $p = $r->user()->portfolio();
        $d = $r->validate(['property_id' => ['required', 'integer'], 'assigned_contact_id' => ['nullable', 'integer'], 'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string'], 'priority' => ['required', Rule::in(['low', 'medium', 'high'])], 'reported_at' => ['required', 'date'], 'due_date' => ['nullable', 'date'], 'estimated_cost' => ['nullable', 'numeric', 'min:0']]);
        Property::where('portfolio_id', $p->id)->findOrFail($d['property_id']);
        $this->validateContact($p->id, $d['assigned_contact_id'] ?? null);

        return response()->json(Issue::create([...$d, 'portfolio_id' => $p->id, 'status' => 'open']), 201);
    }

    public function update(Request $r, Issue $issue)
    {
        $this->ensureOwned($r, $issue);
        $d = $r->validate([
            'assigned_contact_id' => ['sometimes', 'nullable', 'integer'],
            'title' => ['sometimes', 'string', 'max:160'], 'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting', 'resolved', 'cancelled'])],
            'reported_at' => ['sometimes', 'date'], 'due_date' => ['sometimes', 'nullable', 'date'],
            'estimated_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'actual_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'create_expense' => ['sometimes', 'boolean'],
            'expense_status' => ['required_if:create_expense,true', Rule::in(['pending', 'paid'])],
            'expense_date' => ['required_if:create_expense,true', 'date'],
        ]);
        $this->validateContact($issue->portfolio_id, $d['assigned_contact_id'] ?? null);
        $createExpense = (bool) ($d['create_expense'] ?? false);
        unset($d['create_expense'], $d['expense_status'], $d['expense_date']);
        if ($createExpense) {
            abort_unless(
                ($d['status'] ?? $issue->status) === 'resolved'
                && (float) ($d['actual_cost'] ?? $issue->actual_cost) > 0,
                422,
                'Resuelve la incidencia e indica el coste real antes de crear el gasto.',
            );
        }
        if (($d['status'] ?? null) === 'resolved') {
            $d['resolved_at'] = $issue->resolved_at ?? now();
        } elseif (array_key_exists('status', $d)) {
            $d['resolved_at'] = null;
        }
        DB::transaction(function () use ($issue, $d, $createExpense, $r) {
            $lockedIssue = Issue::lockForUpdate()->findOrFail($issue->id);
            $lockedIssue->update($d);
            if ($createExpense) {
                $this->expenses->create($lockedIssue, $r->string('expense_status')->toString(), $r->date('expense_date')->toDateString());
            }
        });

        return $issue->fresh()->load(['property', 'assignedContact', 'expenseTransaction']);
    }

    private function validateContact(int $portfolioId, ?int $contactId): void
    {
        if ($contactId) {
            Contact::where('portfolio_id', $portfolioId)->findOrFail($contactId);
        }
    }

    private function ensureOwned(Request $request, Issue $issue): void
    {
        abort_unless($issue->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
