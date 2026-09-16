<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $r)
    {
        $id = $r->user()->portfolio()->id;
        $from = $r->filled('from') ? Carbon::parse($r->input('from')) : today()->startOfMonth();
        $to = $r->filled('to') ? Carbon::parse($r->input('to')) : today()->addMonths(2)->endOfMonth();
        $events = Reminder::where('portfolio_id', $id)->whereNull('completed_at')->whereBetween('starts_at', [$from, $to])->with('property')->get()->map(fn ($x) => [
            'id' => 'reminder-'.$x->id, 'reminder_id' => $x->id, 'type' => 'reminder',
            'title' => $x->title, 'description' => $x->description,
            'date' => $x->starts_at->toDateString(), 'starts_at' => $x->starts_at->toIso8601String(),
            'property_id' => $x->property_id, 'property' => $x->property,
        ]);
        $charges = RentCharge::where('portfolio_id', $id)->whereBetween('due_date', [$from, $to])->with('lease.property')->get()->map(fn ($x) => ['id' => 'charge-'.$x->id, 'type' => 'rent', 'title' => 'Alquiler '.$x->status, 'date' => $x->due_date->toDateString(), 'amount' => (float) $x->amount - (float) $x->paid_amount, 'property' => $x->lease->property]);
        $leases = Lease::where('portfolio_id', $id)->whereBetween('end_date', [$from, $to])->with('property')->get()->map(fn ($x) => ['id' => 'lease-'.$x->id, 'type' => 'lease_end', 'title' => 'Fin de contrato', 'date' => $x->end_date->toDateString(), 'property' => $x->property]);
        $documents = Document::where('portfolio_id', $id)->whereBetween('expires_at', [$from, $to])->with('property')->get()->map(fn ($x) => ['id' => 'document-'.$x->id, 'type' => 'document_expiry', 'title' => 'Vence '.$x->name, 'date' => $x->expires_at->toDateString(), 'property' => $x->property]);

        return ['events' => $events->concat($charges)->concat($leases)->concat($documents)->sortBy('date')->values()];
    }

    public function store(Request $r)
    {
        $p = $r->user()->portfolio();
        $d = $r->validate(['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string'], 'starts_at' => ['required', 'date'], 'property_id' => ['nullable', 'integer']]);
        if (! empty($d['property_id'])) {
            Property::where('portfolio_id', $p->id)->findOrFail($d['property_id']);
        }

        return response()->json(Reminder::create([...$d, 'portfolio_id' => $p->id]), 201);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $this->ensureOwned($request, $reminder);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'], 'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'date'], 'property_id' => ['sometimes', 'nullable', 'integer'],
            'completed' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('property_id', $data) && $data['property_id']) {
            Property::where('portfolio_id', $reminder->portfolio_id)->findOrFail($data['property_id']);
        }
        if (array_key_exists('completed', $data)) {
            $data['completed_at'] = $data['completed'] ? now() : null;
            unset($data['completed']);
        }
        $reminder->update($data);

        return $reminder->fresh('property');
    }

    public function destroy(Request $request, Reminder $reminder)
    {
        $this->ensureOwned($request, $reminder);
        $reminder->delete();

        return response()->noContent();
    }

    private function ensureOwned(Request $request, Reminder $reminder): void
    {
        abort_unless($reminder->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
