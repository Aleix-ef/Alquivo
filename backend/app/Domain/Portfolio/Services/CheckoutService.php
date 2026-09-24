<?php

namespace App\Domain\Portfolio\Services;

use App\Models\User;
use App\Support\ProductFeatures;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CheckoutService
{
    public function __construct(private StripeCheckoutGateway $gateway, private BillingState $billing) {}

    public function start(User $user, string $frontend): array
    {
        abort_unless(app(ProductFeatures::class)->billing(), 403, 'La Beta es gratuita y la contratación está desactivada.');
        $lock = Cache::lock('billing-checkout:'.$user->id, 120);
        abort_unless($lock->get(), 409, 'Ya estamos preparando tu suscripción. Espera unos segundos.');
        try {
            return $this->locked($user->fresh(), $frontend);
        } finally {
            $lock->release();
        }
    }

    private function locked(User $user, string $frontend): array
    {
        abort_if($this->billing->blocksCheckout($user), 422, 'Ya existe una suscripción. Abre la gestión de pago para revisarla o resolver el pago pendiente.');
        $priceId = config('plans.founder.prices.monthly');
        abort_unless(is_string($priceId) && str_starts_with($priceId, 'price_') && filled(config('cashier.secret')), 503, 'La contratación todavía no está disponible. Tu cuenta y tus datos siguen accesibles.');
        $customer = $this->gateway->customer($user);
        abort_if($this->gateway->hasOpenSubscription($user, $customer), 422, 'Stripe ya tiene una suscripción para tu cuenta. Revisa la gestión de pago; puede estar pendiente de confirmación.');

        $attempt = DB::table('billing_checkout_attempts')->where('user_id', $user->id)->first();
        if ($attempt?->stripe_session_id) {
            $session = $this->gateway->session($user, $attempt->stripe_session_id);
            if ($session['status'] === 'open') {
                return ['url' => $session['url']];
            }
            abort_if($session['status'] !== 'expired', 409, 'Stripe está confirmando tu suscripción. Recarga Planes dentro de unos segundos.');
            DB::table('billing_checkout_attempts')->where('id', $attempt->id)->delete();
            $attempt = null;
        } elseif ($attempt && now()->greaterThan($attempt->expires_at)) {
            DB::table('billing_checkout_attempts')->where('id', $attempt->id)->delete();
            $attempt = null;
        }

        if (! $attempt) {
            $price = $this->gateway->price($user, $priceId);
            abort_unless(($price['active'] ?? false) && ($price['currency'] ?? '') === 'eur'
                && ($price['unit_amount'] ?? null) === (int) round(config('plans.founder.price_monthly') * 100)
                && data_get($price, 'recurring.interval') === 'month' && data_get($price, 'recurring.interval_count') === 1,
                503, 'Estamos revisando el precio de la suscripción. No se ha iniciado ningún pago.');
            $portfolio = $user->portfolio();
            $expires = now()->addHours(24);
            $subscription = ['metadata' => ['type' => 'default', 'name' => 'default', 'plan' => 'founder', 'portfolio_id' => (string) $portfolio->id, 'is_on_session_checkout' => true]];
            if ($portfolio->trial_ends_at?->isFuture()) {
                // Stripe requires sufficient time after an open Checkout expires.
                $subscription['trial_end'] = max($portfolio->trial_ends_at->timestamp, now()->addHours(48)->addSeconds(60)->timestamp);
            }
            $parameters = [
                'customer' => $customer, 'mode' => 'subscription', 'payment_method_types' => ['card'],
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'subscription_data' => $subscription, 'expires_at' => $expires->timestamp,
                'success_url' => $frontend.'/plans?checkout=success', 'cancel_url' => $frontend.'/plans?checkout=cancelled',
                'billing_address_collection' => 'required', 'tax_id_collection' => ['enabled' => true],
                'customer_update' => ['name' => 'auto'],
            ];
            DB::table('billing_checkout_attempts')->insert([
                'user_id' => $user->id, 'request_key' => (string) Str::uuid(), 'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR),
                'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $attempt = DB::table('billing_checkout_attempts')->where('user_id', $user->id)->first();
        }
        // Persist identical parameters BEFORE the external call: a timeout can be retried safely.
        $session = $this->gateway->create($user, json_decode($attempt->parameters, true, flags: JSON_THROW_ON_ERROR), $attempt->request_key);
        DB::table('billing_checkout_attempts')->where('id', $attempt->id)->update(['stripe_session_id' => $session['id'], 'updated_at' => now()]);

        return ['url' => $session['url']];
    }
}
