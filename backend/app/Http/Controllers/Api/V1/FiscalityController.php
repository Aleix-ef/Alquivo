<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Fiscality\Models\TaxReportSnapshot;
use App\Domain\Fiscality\Models\TaxYear;
use App\Domain\Fiscality\Services\TaxDossierService;
use App\Domain\Fiscality\Services\TaxInputValidator;
use App\Domain\Fiscality\Services\TaxReportRenderer;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FiscalityController extends Controller
{
    public function __construct(private readonly PlanService $plans, private readonly TaxDossierService $dossiers) {}

    public function index(Request $request)
    {
        $data = $request->validate(['year' => ['sometimes', 'integer', Rule::in(config('fiscality.years'))]]);
        $year = (int) ($data['year'] ?? max(config('fiscality.years')));
        $access = $this->plans->hasFeature($request->user()->portfolio(), 'fiscal_reports');
        $history = $this->snapshots($request)->where('year', $year)->latest('id')->limit(20)->get()
            ->map(fn ($snapshot) => ['id' => $snapshot->id, 'created_at' => $snapshot->created_at->toIso8601String(), 'year' => $snapshot->year, 'property_count' => $snapshot->payload['property_count'], 'partial' => $snapshot->payload['partial']]);

        return [
            'access' => $access, 'years' => config('fiscality.years'), 'history' => $history,
            'expense_categories' => config('fiscality.expense_categories'),
            'dossier' => $access ? $this->dossiers->build($request->user(), $year) : null,
        ];
    }

    public function profile(Request $request, int $year)
    {
        $this->assertAccess($request, $year);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:100'],
            'regime' => ['required', Rule::in(['common', 'foral', 'non_resident', 'company', 'unknown'])],
        ]);
        DB::transaction(function () use ($request, $year, $data) {
            $taxYear = $this->lockedYear($request, $year);
            abort_if($taxYear->revision !== (int) $data['revision'], 409, 'La ficha ha cambiado en otra ventana. Recarga antes de guardar.');
            $taxYear->update(['profile' => ['name' => $data['name'], 'regime' => $data['regime']], 'revision' => $taxYear->revision + 1]);
        });

        return ['message' => 'Perfil fiscal guardado.'];
    }

    public function property(Request $request, int $year, Property $property, TaxInputValidator $validator)
    {
        abort_unless($property->portfolio_id === $request->user()->portfolio()->id, 404);
        $this->assertAccess($request, $year);
        $data = $validator->validate($request->all(), $year, $property);
        DB::transaction(function () use ($request, $year, $property, $data) {
            $taxYear = $this->lockedYear($request, $year);
            $record = $taxYear->records()->where('property_id', $property->id)->first();
            abort_if(($record?->revision ?? 0) !== (int) $data['revision'], 409, 'La ficha ha cambiado en otra ventana. Recarga antes de guardar.');
            $taxYear->records()->updateOrCreate(['property_id' => $property->id], ['inputs' => $data['inputs'], 'revision' => ($record?->revision ?? 0) + 1]);
        });

        return ['message' => 'Ficha fiscal guardada.'];
    }

    public function storeReport(Request $request, int $year)
    {
        $this->assertAccess($request, $year);
        $data = $request->validate(['property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('portfolio_id', $request->user()->portfolio()->id)->whereNull('deleted_at')]]);
        $snapshot = DB::transaction(function () use ($request, $year, $data) {
            $this->lockedYear($request, $year);
            $payload = $this->dossiers->build($request->user(), $year, $data['property_id'] ?? null);
            abort_if($payload['property_count'] === 0, 422, 'Añade un inmueble antes de crear un dossier.');
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = $this->snapshots($request)->where('year', $year)->where('fingerprint', $fingerprint)->first();
            if ($existing) {
                return $existing;
            }
            abort_if($this->snapshots($request)->where('year', $year)->count() >= config('fiscality.max_reports_per_year'), 422, 'Has alcanzado el límite de 100 versiones de este ejercicio. Puedes descargar las existentes.');

            return TaxReportSnapshot::create([
                'portfolio_id' => $request->user()->portfolio()->id, 'user_id' => $request->user()->id,
                'year' => $year, 'fingerprint' => $fingerprint, 'payload' => $payload,
            ]);
        });

        return response()->json(['id' => $snapshot->id, 'created_at' => $snapshot->created_at->toIso8601String()], 201);
    }

    public function download(Request $request, int $snapshot, string $format, TaxReportRenderer $renderer)
    {
        // Historical dossiers remain downloadable after downgrade. Ownership is still required.
        $report = $this->snapshots($request)->findOrFail($snapshot);
        $filename = 'alquivo-fiscal-'.$report->year.'-'.$report->id.'.'.$format;
        $payload = [...$report->payload, 'report_id' => $report->id, 'generated_at' => $report->created_at->format('d/m/Y H:i').' UTC'];

        return response($format === 'pdf' ? $renderer->pdf($payload) : $renderer->csv($payload), 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function snapshots(Request $request)
    {
        return TaxReportSnapshot::where('portfolio_id', $request->user()->portfolio()->id)->where('user_id', $request->user()->id);
    }

    private function assertAccess(Request $request, int $year): void
    {
        abort_unless(in_array($year, config('fiscality.years'), true), 422, 'El ejercicio solicitado todavía no está habilitado.');
        abort_unless($this->plans->hasFeature($request->user()->portfolio(), 'fiscal_reports'), 403, 'La fiscalidad no está disponible en tu plan actual.');
    }

    private function lockedYear(Request $request, int $year): TaxYear
    {
        Portfolio::whereKey($request->user()->portfolio()->id)->lockForUpdate()->firstOrFail();

        return TaxYear::firstOrCreate(['portfolio_id' => $request->user()->portfolio()->id, 'user_id' => $request->user()->id, 'year' => $year]);
    }
}
