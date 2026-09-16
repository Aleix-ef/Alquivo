<?php

namespace App\Console\Commands;

use App\Support\ProductionReadiness;
use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Validar la configuración técnica de producción sin mostrar secretos';

    public function handle(ProductionReadiness $readiness): int
    {
        $failures = $readiness->failures();
        foreach ($failures as $failure) {
            $this->error($failure);
        }
        if ($failures) {
            return self::FAILURE;
        }
        $this->info('Configuración técnica validada. Verifica además TLS, copias/restauración, entrega de correo, antivirus, monitorización y documentación legal.');

        return self::SUCCESS;
    }
}
