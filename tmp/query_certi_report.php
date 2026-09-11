<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('paquetes_certi as p')
    ->leftJoin('estados as e', 'e.id', '=', 'p.fk_estado')
    ->leftJoin('ventanilla as v', 'v.id', '=', 'p.fk_ventanilla')
    ->whereIn(DB::raw('UPPER(TRIM(e.nombre_estado))'), ['VENTANILLA', 'RECIBIDO'])
    ->selectRaw("UPPER(TRIM(COALESCE(p.cuidad, ''))) AS ciudad")
    ->selectRaw("UPPER(TRIM(COALESCE(v.nombre_ventanilla, p.ventanilla, ''))) AS ventanilla")
    ->selectRaw('UPPER(TRIM(e.nombre_estado)) AS estado')
    ->selectRaw('COUNT(*) AS total')
    ->groupByRaw("UPPER(TRIM(COALESCE(p.cuidad, '')))")
    ->groupByRaw("UPPER(TRIM(COALESCE(v.nombre_ventanilla, p.ventanilla, '')))")
    ->groupByRaw('UPPER(TRIM(e.nombre_estado))')
    ->orderBy('ciudad')
    ->orderBy('ventanilla')
    ->orderBy('estado')
    ->get();

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
