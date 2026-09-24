<?php

namespace App\Domain\Support\Services;

use App\Domain\Support\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\TransientToken;

final class SupportAccess
{
    public function canManage(?User $user): bool
    {
        return $user && $user->hasVerifiedEmail() && $user->two_factor_confirmed_at
            && ($user->local_admin || DB::table('support_agents')->where('user_id', $user->id)->exists());
    }

    public function isTeam(Request $request): bool
    {
        return $request->is('api/v1/support/team/*');
    }

    public function isPublic(Request $request): bool
    {
        return $request->is('api/v1/public/support/chat*');
    }

    public function guestHash(Request $request): string
    {
        abort_unless($request->hasSession(), 419, 'Vuelve a abrir el chat para iniciar una sesión segura.');
        if (! $request->session()->has('support_guest_key')) {
            $request->session()->put('support_guest_key', Str::random(64));
        }

        return hash('sha256', $request->session()->get('support_guest_key'));
    }

    public function query(Request $request): Builder
    {
        $query = SupportConversation::query();
        if ($this->isTeam($request)) {
            $token = $request->user()?->currentAccessToken();
            abort_unless($this->canManage($request->user()) && (! $token || $token instanceof TransientToken), 403, 'El acceso al equipo requiere autorización, correo verificado y doble factor.');

            return $query;
        }
        if ($this->isPublic($request)) {
            return $query->whereNull('user_id')->where('guest_key_hash', $this->guestHash($request));
        }
        abort_unless($request->user(), 401);

        return $query->where('user_id', $request->user()->id);
    }

    public function owner(Request $request): array
    {
        if ($this->isPublic($request)) {
            return ['user_id' => null, 'guest_key_hash' => $this->guestHash($request)];
        }

        return ['user_id' => $request->user()->id, 'guest_key_hash' => null];
    }
}
