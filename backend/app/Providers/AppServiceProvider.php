<?php

namespace App\Providers;

use App\Listeners\SyncStripePlan;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Domain\Assistant\Contracts\AIProviderInterface::class, \App\Domain\Assistant\Providers\OpenAIProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('services.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));
        ResetPassword::toMailUsing(fn ($user, $token) => (new MailMessage)
            ->subject('Recupera tu acceso a Alquivo')
            ->line('Has solicitado cambiar tu contraseña. Si no has sido tú, puedes ignorar este correo.')
            ->action('Crear una nueva contraseña', rtrim(config('services.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line('El enlace caduca en '.config('auth.passwords.users.expire').' minutos.'));
        VerifyEmail::toMailUsing(function ($user) {
            $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
            parse_str(parse_url($signed, PHP_URL_QUERY), $query);
            $url = rtrim(config('services.frontend_url'), '/').'/verify-email?'.http_build_query([
                'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()), ...$query,
            ]);

            return (new MailMessage)->subject('Confirma tu correo en Alquivo')
                ->line('Confirma este correo para proteger tu cuenta y poder recibir códigos de acceso por email si eliges ese método.')
                ->action('Confirmar mi correo', $url)
                ->line('El enlace caduca en 60 minutos. Si lo abres en otro dispositivo, inicia sesión con esta cuenta.');
        });
        Event::listen(Login::class, fn ($event) => SecurityAudit::record('auth.login', $event->user->id));
        Event::listen(Failed::class, fn ($event) => SecurityAudit::record('auth.failed', $event->user?->id));
        Event::listen(Logout::class, fn ($event) => SecurityAudit::record('auth.logout', $event->user?->id));
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))))),
        ]);
        RateLimiter::for('auth-recovery', fn (Request $request) => [
            Limit::perMinute(10)->by('recovery-ip:'.$request->ip()),
            Limit::perHour(5)->by('recovery-account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))))),
        ]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('two-factor-challenge', fn (Request $request) => Limit::perMinute(20)->by('mfa-ip:'.$request->ip()));
        RateLimiter::for('two-factor-settings', fn (Request $request) => Limit::perMinute(6)->by('mfa-settings:'.$request->user()?->id));
        RateLimiter::for('support', fn (Request $request) => [
            Limit::perMinute(3)->by('support-minute:'.$request->ip()),
            Limit::perHour(10)->by('support-hour:'.$request->ip()),
            Limit::perHour(100)->by('support-global'),
        ]);
        RateLimiter::for('support-chat', fn (Request $request) => [
            Limit::perMinute(12)->by('support-chat:'.($request->user()?->id ?? $request->ip())),
            Limit::perHour(120)->by('support-chat-hour:'.($request->user()?->id ?? $request->ip())),
        ]);
        RateLimiter::for('fiscal-create', fn (Request $request) => Limit::perMinute(6)->by('fiscal-create:'.$request->user()?->id));
        RateLimiter::for('fiscal-download', fn (Request $request) => Limit::perMinute(12)->by('fiscal-download:'.$request->user()?->id));
        Event::listen(WebhookHandled::class, SyncStripePlan::class);
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
