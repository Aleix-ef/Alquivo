<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Leasing\Actions\CreateContact;
use App\Domain\Leasing\Actions\UpdateContactPhone;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        return Contact::where('portfolio_id', $request->user()->portfolio()->id)
            ->with(['leases.property'])->latest()->paginate(30);
    }

    public function store(Request $request)
    {
        return response()->json(app(CreateContact::class)->execute($request->user()->portfolio(), $request->user(), $request->all()), 201);
    }

    public function update(Request $request, Contact $contact, UpdateContactPhone $updatePhone)
    {
        $this->ensureOwned($request, $contact);
        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(['person', 'company'])],
            'name' => ['sometimes', 'string', 'max:120'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        return DB::transaction(function () use ($request, $contact, $updatePhone, $data) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($request->user()->portfolio()->id);
            abort_unless($portfolio->members()->whereKey($request->user()->id)->wherePivot('role', 'owner')->exists(), 404);
            $contact = Contact::query()->where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($contact->id);
            if (array_key_exists('phone', $data)) {
                $contact = $updatePhone->execute($portfolio, $request->user(), ['contact_id' => $contact->id, 'phone' => $data['phone']]);
                unset($data['phone']);
            }
            $contact->update($data);

            return $contact->fresh();
        });
    }

    public function destroy(Request $request, Contact $contact)
    {
        $this->ensureOwned($request, $contact);
        abort_if($contact->leases()->exists(), 422, 'No puedes archivar un contacto vinculado a un alquiler.');
        $contact->delete();

        return response()->noContent();
    }

    private function ensureOwned(Request $request, Contact $contact): void
    {
        abort_unless($contact->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
