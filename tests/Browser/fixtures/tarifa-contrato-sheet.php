<?php

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Blade::if('aclcan', fn () => true);
$constants = new ReflectionClass(App\Http\Controllers\TarifaContratoController::class);
echo Illuminate\Support\Facades\Blade::render(
    '<input id="outside-sheet" aria-label="Outside sheet"><form id="tarifas-sheet-form">@include("tarifa_contrato.spreadsheet")</form>@stack("js")',
    [
        'defaults' => ['tarifario_contrato_id' => 70],
        'tarifaContrato' => new App\Models\TarifaContrato,
        'tarifarios' => collect([
            (new App\Models\TarifarioContrato)->forceFill(['id' => 70, 'nombre' => 'Tarifario A', 'anio' => 2026]),
            (new App\Models\TarifarioContrato)->forceFill(['id' => 71, 'nombre' => 'Tarifario A', 'anio' => 2027]),
        ]),
        'errors' => new Illuminate\Support\ViewErrorBag,
        'serviciosPorAnio' => [
            '2026' => [...App\Models\ServicioContrato::PREDEFINIDOS, 'SERVICIO SOLO 2026'],
            '2027' => ['SERVICIO SOLO 2027'],
        ],
        'departamentos' => $constants->getConstant('DEPARTAMENTOS'),
        'provinciasPorDepartamento' => $constants->getConstant('PROVINCIAS_POR_DEPARTAMENTO'),
    ]
);
