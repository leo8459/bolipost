<?php

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Blade::if('aclcan', fn () => true);
$constants = new ReflectionClass(App\Http\Controllers\TarifaContratoController::class);
$template = file_get_contents(resource_path('views/tarifa_contrato/create.blade.php'));
$template = preg_replace('/^@extends\([^\n]+\)\R/', '', $template);
$template = str_replace("@include('footer')", '', $template);
$conPrecarga = in_array('--precarga', $argv, true);
$origen = (new App\Models\TarifarioContrato)->forceFill(['id' => 72, 'nombre' => 'ENTEL', 'anio' => 2027, 'tarifas_count' => 261]);
$origen->setRelation('empresasActivas', collect());
$filas = $conPrecarga ? array_map(fn ($n) => [
    'origen' => 'LA PAZ', 'destino' => 'LA PAZ', 'servicio' => 'SERVICIO LOCAL ENTEL',
    'kilo' => '17', 'kilo_extra' => '6', 'horas_entrega' => '24', 'zona' => 'Zona 2',
    'provincia' => 'PROVINCIA '.$n, 'peso' => $n === 1 ? '1.50' : null,
], range(1, 261)) : [];
echo Illuminate\Support\Facades\Blade::render(
    '<input id="outside-sheet" aria-label="Outside sheet">'.$template.' @yield("content") @stack("js")',
    [
        'defaults' => ['tarifario_contrato_id' => 70],
        'tarifariosOrigen' => collect([$origen]),
        'tarifarioOrigen' => $conPrecarga ? $origen : null,
        'filasPrecargadas' => $filas,
        'maxSheetRows' => count($filas) + 50,
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
