<?php

// Local visual QA only. Uses invented data and never reads or writes the application database.
require __DIR__.'/../backend/vendor/autoload.php';
require_once __DIR__.'/../backend/tests/Support/FiscalFixture.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$inputs = Tests\Support\FiscalFixture::inputs(['ownership_percent' => '50', 'carryforwards' => [['year' => 2021, 'amount' => '1000'], ['year' => 2024, 'amount' => '200']]]);
$property = ['id' => 1, 'name' => 'Vivienda del Jardín', 'type' => 'housing', 'country_code' => 'ES', 'address_line' => 'Calle de Ejemplo, 25', 'city' => 'Valencia'];
$profile = ['name' => 'Contribuyente ficticio · prueba visual', 'regime' => 'common'];
$calculator = new App\Domain\Fiscality\Services\PropertyTaxCalculator;
$result = $calculator->calculate(2025, $profile, $property, $inputs);
$item = ['property' => $property, 'inputs' => $inputs, 'result' => $result, 'source' => ['charge_income_cents' => 1200000, 'paid_income_cents' => 1150000, 'paid_expenses_cents' => 300000, 'documents' => [['id' => 7, 'name' => 'Recibo de comunidad y seguro anual de la vivienda de ejemplo']]]];
$pending = $item;
$pending['property']['name'] = 'Apartamento con datos pendientes';
$pending['inputs'] = [];
$pending['result'] = $calculator->calculate(2025, $profile, $property, []);
$payload = ['year' => 2025, 'profile' => $profile, 'property_count' => 2, 'calculated_count' => 1, 'partial' => true, 'properties' => [$item, $pending], 'figure_labels' => App\Domain\Fiscality\Services\PropertyTaxCalculator::FIGURES, 'totals' => $result['figures'], 'report_id' => 'MUESTRA', 'generated_at' => '10/09/2026 12:00 UTC', 'rules_version' => config('fiscality.rules_version'), 'sources' => config('fiscality.sources'), 'notice' => 'Borrador fiscal para revisión. Datos totalmente ficticios. No calcula la cuota personal de IRPF ni presenta una declaración.'];
$path = '/tmp/alquivo-fiscal-qa.pdf';
file_put_contents($path, app(App\Domain\Fiscality\Services\TaxReportRenderer::class)->pdf($payload));
echo $path.PHP_EOL;
