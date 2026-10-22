<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Identity\Services\LegalEvidence;
use App\Domain\Identity\Services\RevokeSessions;
use App\Domain\Identity\Services\TwoFactor;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChat;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(private readonly StorageUsageService $storageUsage, private readonly PlanService $plans) {}

    public function update(Request $request)
    {
        $user = $request->user()->refresh();
        $portfolio = $user->portfolio();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $emailChanged = $request->input('email') !== $user->email;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'portfolio_name' => ['required', 'string', 'max:100'],
            // Never relabel existing amounts as another currency without a conversion workflow.
            'currency' => ['required', Rule::in([$portfolio->currency])],
            'country_code' => ['required', 'string', 'size:2'],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password:web'],
            'two_factor_code' => [Rule::requiredIf($emailChanged && (bool) $user->two_factor_confirmed_at), 'nullable', 'string', 'max:40'],
        ]);

        $email = strtolower($data['email']);
        $emailChanged = $email !== $user->email;
        if ($emailChanged && $user->two_factor_confirmed_at) {
            app(TwoFactor::class)->throttle($user->id);
        }
        $user = DB::transaction(function () use ($user, $request, $data, $email) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $changed = $email !== $user->email;
            if ($changed) {
                abort_unless(Hash::check($request->input('current_password'), $user->password), 422, 'Confirma tu contraseña actual.');
            }
            if ($changed && $user->two_factor_confirmed_at) {
                app(TwoFactor::class)->consumeForSecurity($user, $request,
                    'change_email:'.hash('sha256', $email), trim($data['two_factor_code'] ?? ''));
            }
            $user->update(['name' => $data['name'], 'email' => $email, 'email_verified_at' => $changed ? null : $user->email_verified_at]);
            // An email-only account has no TOTP secret: never switch it to an unusable factor.
            if ($changed && $user->two_factor_method === 'both' && filled($user->two_factor_secret)) {
                $user->forceFill(['two_factor_method' => 'authenticator'])->save();
            }

            return $user;
        });
        if ($emailChanged) {
            app(RevokeSessions::class)->execute($user, $request->hasSession() ? $request->session()->getId() : null);
            SecurityAudit::record('account.email_changed', $user->id);
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                report($exception);
            }
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
        SecurityAudit::record('account.password_changed', $request->user()->id);
        app(RevokeSessions::class)->execute($request->user(), $request->hasSession() ? $request->session()->getId() : null);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

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
        abort_if($portfolio && $portfolio->members()->count() > 1, 422, 'No puedes eliminar una cartera con otros miembros.');
        abort_if($user->subscribed('default'), 422, 'Cancela primero tu suscripción desde el portal de facturación.');

        Auth::guard('web')->logout();
        if ($user->stripe_id) {
            $user->deleteStripeCustomer();
        }
        $cleanupIds = DB::transaction(function () use ($user, $portfolio) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            app(LegalEvidence::class)->closeAccount($user);
            $cleanupIds = $portfolio ? [app(PrivateFileDeletion::class)->schedule("portfolios/{$portfolio->id}", true)] : [];
            foreach (SupportConversation::where('user_id', $user->id)->lockForUpdate()->get() as $conversation) {
                $cleanupIds[] = app(SupportChat::class)->scheduleDeletion($conversation);
            }
            app(RevokeSessions::class)->execute($user);
            $user->subscriptions()->each(function ($subscription) {
                $subscription->items()->delete();
                $subscription->delete();
            });
            $portfolio?->delete();
            $user->delete();

            return $cleanupIds;
        });
        foreach ($cleanupIds as $cleanupId) {
            app(PrivateFileDeletion::class)->process($cleanupId);
        }
        SecurityAudit::record('account.deleted', $user->id);
        if ($request->hasSession()) {
            $request->session()->invalidate();
        }

        return response()->noContent();
    }
}
