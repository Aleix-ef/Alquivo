<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(private readonly StorageUsageService $storageUsage, private readonly PlanService $plans) {}

    public function update(Request $request)
    {
        $user = $request->user();
        $portfolio = $user->portfolio();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'portfolio_name' => ['required', 'string', 'max:100'],
            'currency' => ['required', Rule::in(['EUR', 'USD', 'GBP'])],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        $email = strtolower($data['email']);
        $emailChanged = $email !== $user->email;
        $user->update(['name' => $data['name'], 'email' => $email, 'email_verified_at' => $emailChanged ? null : $user->email_verified_at]);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
        $portfolio->update([
            'name' => $data['portfolio_name'],
            'currency' => $data['currency'],
            'country_code' => strtoupper($data['country_code']),
        ]);

        return ['user' => $user->fresh(), 'portfolio' => $portfolio->fresh()];
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => $data['password']]);
        $request->user()->tokens()->delete();

        return ['message' => 'Contraseña actualizada.'];
    }

    public function usage(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $used = $this->storageUsage->used($portfolio);

        return $this->plans->summary($portfolio, $used);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['ELIMINAR'])],
        ]);
        $user = $request->user();
        $portfolio = $user->portfolio();
        abort_if($portfolio->members()->count() > 1, 422, 'No puedes eliminar una cartera con otros miembros.');
        abort_if($user->subscribed('default'), 422, 'Cancela primero tu suscripción desde el portal de facturación.');

        Auth::guard('web')->logout();
        if ($user->stripe_id) {
            $user->deleteStripeCustomer();
        }
        DB::transaction(function () use ($user, $portfolio) {
            $user->subscriptions()->each(function ($subscription) {
                $subscription->items()->delete();
                $subscription->delete();
            });
            $portfolio->delete();
            $user->delete();
        });
        Storage::disk('local')->deleteDirectory("portfolios/{$portfolio->id}");
        if ($request->hasSession()) {
            $request->session()->invalidate();
        }

        return response()->noContent();
    }
}
