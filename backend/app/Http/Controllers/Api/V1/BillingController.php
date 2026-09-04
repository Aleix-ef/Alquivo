<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;

class BillingController extends Controller
{
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in($this->commercialPlanCodes())],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);
        $price = config("plans.{$data['plan']}.prices.{$data['period']}");
        abort_unless(is_string($price) && str_starts_with($price, 'price_'), 503, 'El precio de Stripe debe ser un identificador que empiece por price_.');
        $user = $request->user();
        abort_if($user->subscribed('default'), 422, 'Ya tienes una suscripción. Utiliza el portal para cambiarla.');
        $frontend = $this->frontendOrigin($request);
        $builder = $user->newSubscription('default', $price);
        if ($user->portfolio()->trial_ends_at?->isFuture()) {
            $builder->trialUntil($user->portfolio()->trial_ends_at);
        }
        $checkout = $builder
            ->withMetadata(['portfolio_id' => (string) $user->portfolio()->id, 'plan' => $data['plan']])
            ->checkout([
                'success_url' => $frontend.'/plans?checkout=success',
                'cancel_url' => $frontend.'/plans?checkout=cancelled',
                'allow_promotion_codes' => true,
                'billing_address_collection' => 'required',
                'tax_id_collection' => ['enabled' => true],
                'managed_payments' => ['enabled' => false],
            ]);

        return ['url' => $checkout->url];
    }

    public function portal(Request $request)
    {
        abort_unless($request->user()->stripe_id, 422, 'Todavía no existe un perfil de facturación.');

        return ['url' => $request->user()->billingPortalUrl($this->frontendOrigin($request).'/plans')];
    }

    public function changePlan(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in($this->commercialPlanCodes())],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);
        $price = config("plans.{$data['plan']}.prices.{$data['period']}");
        abort_unless(is_string($price) && str_starts_with($price, 'price_'), 503, 'El precio de Stripe no está configurado.');

        $user = $request->user();
        $subscription = $user->subscription('default');
        abort_unless($subscription && $subscription->valid(), 422, 'No existe una suscripción activa que cambiar.');
        abort_if($subscription->stripe_price === $price, 422, 'Ya tienes seleccionado este plan y periodo.');

        try {
            $remote = $user->stripe()->subscriptions->retrieve($subscription->stripe_id);
            $item = $remote->items->first();
            abort_unless($item, 422, 'La suscripción no contiene un precio modificable.');
            $effectiveAt = (int) ($item->current_period_end ?? $remote->trial_end ?? $remote->billing_cycle_anchor);
            abort_if($effectiveAt <= now()->timestamp, 422, 'Stripe no ha proporcionado una fecha de renovación válida.');

            $scheduleId = is_string($remote->schedule) ? $remote->schedule : $remote->schedule?->id;
            $schedule = $scheduleId
                ? $user->stripe()->subscriptionSchedules->retrieve($scheduleId)
                : $user->stripe()->subscriptionSchedules->create(['from_subscription' => $subscription->stripe_id]);

            $currentPhase = [
                'start_date' => $schedule->current_phase->start_date,
                'end_date' => $effectiveAt,
                'items' => [['price' => $item->price->id, 'quantity' => $item->quantity ?? 1]],
                'proration_behavior' => 'none',
            ];
            if ($remote->trial_end && $remote->trial_end <= $effectiveAt) {
                $currentPhase['trial_end'] = $remote->trial_end;
            }

            $user->stripe()->subscriptionSchedules->update($schedule->id, [
                'end_behavior' => 'release',
                'proration_behavior' => 'none',
                'metadata' => ['portfolio_id' => (string) $user->portfolio()->id, 'pending_plan' => $data['plan']],
                'phases' => [
                    $currentPhase,
                    [
                        'start_date' => $effectiveAt,
                        'items' => [['price' => $price, 'quantity' => 1]],
                        'duration' => ['interval' => $data['period'] === 'yearly' ? 'year' : 'month', 'interval_count' => 1],
                        'billing_cycle_anchor' => 'phase_start',
                        'proration_behavior' => 'none',
                        'metadata' => ['plan' => $data['plan']],
                    ],
                ],
            ]);
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Stripe no ha podido aplicar el cambio en este momento. Inténtalo de nuevo en unos minutos.',
            ], 502);
        }

        $user->portfolio()->update([
            'pending_plan' => $data['plan'],
            'pending_billing_period' => $data['period'],
            'pending_plan_effective_at' => date('Y-m-d H:i:s', $effectiveAt),
            'billing_schedule_id' => $schedule->id,
        ]);

        return response()->json([
            'message' => 'Cambio programado. Tu plan y tus límites actuales se mantendrán hasta la próxima renovación.',
        ]);
    }

    public function cancelPlanChange(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        abort_unless($portfolio->billing_schedule_id, 422, 'No hay ningún cambio de plan programado.');

        try {
            $request->user()->stripe()->subscriptionSchedules->release($portfolio->billing_schedule_id);
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json(['message' => 'Stripe no ha podido cancelar el cambio en este momento.'], 502);
        }

        $portfolio->update([
            'pending_plan' => null,
            'pending_billing_period' => null,
            'pending_plan_effective_at' => null,
            'billing_schedule_id' => null,
        ]);

        return ['message' => 'Cambio programado cancelado. Mantendrás tu plan actual.'];
    }

    private function frontendOrigin(Request $request): string
    {
        $fallback = rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/');
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
