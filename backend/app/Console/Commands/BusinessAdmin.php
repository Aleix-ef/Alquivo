<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BusinessAdmin extends Command
{
    protected $signature = 'admin:account {email} {--revoke : Retirar la autorización administrativa}';

    protected $description = 'Autorizar explícitamente la cuenta configurada; no crea usuarios ni modifica planes o soporte';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if ($email !== config('admin.authorized_email')) {
            $this->error('Este correo no está autorizado en la configuración administrativa.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            $grant = DB::table('business_admins')->where('email', $email)->first();
            DB::table('business_admins')->where('email', $email)->delete();
            SecurityAudit::record('admin.access_revoked', $grant?->user_id);
            $this->info('Autorización administrativa retirada. Soporte y planes no se modifican.');

            return self::SUCCESS;
        }

        return DB::transaction(function () use ($email) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || ! $user->hasVerifiedEmail() || ! $user->two_factor_confirmed_at) {
                $this->error('Se requiere una cuenta existente, correo verificado y doble factor activo. No se ha concedido acceso.');

                return self::FAILURE;
            }
            $existing = DB::table('business_admins')->where('email', $email)->orWhere('user_id', $user->id)->first();
            if ($existing && ($existing->user_id !== $user->id || $existing->email !== $email)) {
                $this->error('Existe una autorización anterior diferente. Revócala explícitamente antes de reasignar el acceso.');

                return self::FAILURE;
            }
            // A fresh version after revocation prevents old verified sessions reviving.
            DB::table('business_admins')->insertOrIgnore([
                'user_id' => $user->id, 'email' => $email, 'grant_version' => (string) Str::uuid(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            SecurityAudit::record('admin.access_granted', $user->id);
            $this->info('Cuenta autorizada para /admin. Se exigirá una verificación MFA reciente.');

            return self::SUCCESS;
        });
    }
}
