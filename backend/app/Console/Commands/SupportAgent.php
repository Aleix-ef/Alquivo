<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupportAgent extends Command
{
    protected $signature = 'support:agent {email} {--revoke : Retirar acceso a la bandeja}';

    protected $description = 'Autorizar una cuenta existente y verificada para atender soporte (requiere doble factor)';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('La cuenta no existe. Regístrala primero; este comando no crea cuentas ni contraseñas.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            DB::table('support_agents')->where('user_id', $user->id)->delete();
            SecurityAudit::record('support.access_revoked', $user->id);
            $this->info('Acceso al equipo retirado.');

            return self::SUCCESS;
        }
        if (! $user->hasVerifiedEmail() || ! $user->two_factor_confirmed_at) {
            $this->error('Verifica el correo y activa el doble factor antes de conceder acceso.');

            return self::FAILURE;
        }
        DB::table('support_agents')->insertOrIgnore(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        SecurityAudit::record('support.access_granted', $user->id);
        $this->info('Cuenta autorizada. La bandeja está en /support/inbox.');

        return self::SUCCESS;
    }
}
