<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Services\RecurringTransactionService;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringRuleController extends Controller
{
    public function __construct(private readonly RecurringTransactionService $generator) {}

    public function index(Request $request)
    {
        return RecurringRule::where('portfolio_id', $request->user()->portfolio()->id)
            ->with('property')->latest()->paginate(30);
    }

    public function store(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $data = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:50'], 'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        if (! empty($data['property_id'])) {
            Property::where('portfolio_id', $portfolio->id)->findOrFail($data['property_id']);
        }
        $rule = RecurringRule::create([
            ...$data, 'portfolio_id' => $portfolio->id, 'next_date' => $data['starts_on'], 'active' => true,
        ]);
        $this->generator->generateDue($rule);

        return response()->json($rule->fresh('property'), 201);
    }

    public function update(Request $request, RecurringRule $recurringRule)
    {
        abort_unless($recurringRule->portfolio_id === $request->user()->portfolio()->id, 404);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $recurringRule->update($data);

        return $recurringRule->fresh('property');
    }
}
