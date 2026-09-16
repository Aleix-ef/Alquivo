<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Services\CheckoutService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;

class BillingController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function checkout(Request $request)
    {
        abort_unless(config('beta.billing_enabled'), 403, 'La contratación está desactivada durante la validación. No se ha iniciado ningún pago.');
        $data = $request->validate([
            'plan' => ['required', Rule::in($this->commercialPlanCodes())],
            'period' => ['required', Rule::in(['monthly'])],
        ]);
        try {
            return $this->checkout->start($request->user(), $this->frontendOrigin($request));
        } catch (ApiErrorException $exception) {
            Log::warning('Checkout provider unavailable', ['type' => get_class($exception)]);

            return response()->json(['message' => 'No hemos podido conectar con la facturación. Puedes volver a intentarlo sin iniciar otra contratación.'], 503);
        }
    }

    public function portal(Request $request)
    {
        abort_unless(config('beta.billing_enabled'), 403, 'La gestión de pagos está desactivada. Si ya tenías una suscripción, contacta con soporte para revisarla o cancelarla.');
        abort_unless($request->user()->stripe_id, 422, 'Todavía no existe un perfil de facturación.');

        try {
            return ['url' => $request->user()->billingPortalUrl($this->frontendOrigin($request).'/plans')];
        } catch (ApiErrorException $exception) {
            return response()->json(['message' => 'No se pudo abrir la gestión de pago. Inténtalo de nuevo o contacta con soporte.'], 503);
        }
    }

    private function frontendOrigin(Request $request): string
    {
        $fallback = rtrim(config('services.frontend_url'), '/');
        $origin = rtrim((string) $request->header('Origin'), '/');
        $allowed = array_map(
            static fn (string $value) => rtrim($value, '/'),
            config('cors.allowed_origins', []),
        );

        return $origin !== '' && in_array($origin, $allowed, true) ? $origin : $fallback;
    }

    private function commercialPlanCodes(): array
    {
        return collect(config('plans'))->filter(fn (array $plan) => $plan['commercial'])->keys()->all();
    }
}
