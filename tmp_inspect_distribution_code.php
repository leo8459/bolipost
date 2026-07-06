<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$code = $argv[1] ?? '';

$certi = App\Models\PaqueteCerti::query()
    ->where('codigo', $code)
    ->orWhere('cod_especial', $code)
    ->first();

$ordi = App\Models\PaqueteOrdi::query()
    ->where('codigo', $code)
    ->orWhere('cod_especial', $code)
    ->first();

$payload = [
    'certi' => $certi ? $certi->only(['id', 'codigo', 'cod_especial', 'fk_estado', 'cuidad', 'zona']) : null,
    'ordi' => $ordi ? $ordi->only(['id', 'codigo', 'cod_especial', 'fk_estado', 'ciudad', 'zona']) : null,
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
