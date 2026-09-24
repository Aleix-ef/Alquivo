<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;

final class DemoAdmin extends Command
{
    protected $signature = 'demo:admin {--revoke : Retirar el rol de administrador local}';

    protected $description = 'Conceder acceso de pruebas a la demo existente, solo en entorno local';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Este permiso solo se puede gestionar en APP_ENV=local.');

            return self::FAILURE;
        }
        $user = User::where('email', 'demo@alquivo.test')->first();
        if (! $user || (! $this->option('revoke') && (! $user->hasVerifiedEmail() || ! $user->two_factor_confirmed_at))) {
            $this->error('La demo debe existir, tener el correo verificado y el doble factor activo. No se modifica su autenticación.');

            return self::FAILURE;
        }
        $user->forceFill(['role' => $this->option('revoke') ? 'user' : 'admin'])->save();
        SecurityAudit::record($this->option('revoke') ? 'demo.admin_revoked' : 'demo.admin_granted', $user->id);
        $this->info($this->option('revoke') ? 'Rol retirado.' : 'Demo administradora local. No se han activado pagos ni modificado credenciales.');

        return self::SUCCESS;
    }
}
