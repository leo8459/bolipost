<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$code = 'EN000005184SRZ';
$queries = [
  'contrato' => App\Models\Recojo::query()->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])->count(),
  'ordinario' => App\Models\PaqueteOrdi::query()->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])->count(),
  'certificado' => App\Models\PaqueteCerti::query()->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])->count(),
  'interno' => App\Models\PaqueteInt::query()->where(function($q) use ($code) {
      $q->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])
        ->orWhereRaw("trim(upper(COALESCE(cod_especial, ''))) = trim(upper(?))", [$code]);
  })->count(),
  'ems' => App\Models\PaqueteEms::query()->where(function($q) use ($code) {
      $q->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])
        ->orWhereRaw("trim(upper(COALESCE(cod_especial, ''))) = trim(upper(?))", [$code]);
  })->count(),
  'solicitud_ems' => App\Models\SolicitudCliente::query()->where(function($q) use ($code) {
      $q->whereRaw("trim(upper(COALESCE(codigo_solicitud, ''))) = trim(upper(?))", [$code])
        ->orWhereRaw("trim(upper(COALESCE(barcode, ''))) = trim(upper(?))", [$code])
        ->orWhereRaw("trim(upper(COALESCE(cod_especial, ''))) = trim(upper(?))", [$code]);
  })->count(),
];
foreach ($queries as $label => $count) {
  echo $label . '=' . $count . PHP_EOL;
}
$interno = App\Models\PaqueteInt::query()->where(function($q) use ($code) {
    $q->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])
      ->orWhereRaw("trim(upper(COALESCE(cod_especial, ''))) = trim(upper(?))", [$code]);
})->first(['id','codigo','cod_especial','precio']);
$ems = App\Models\PaqueteEms::query()->where(function($q) use ($code) {
    $q->whereRaw("trim(upper(codigo)) = trim(upper(?))", [$code])
      ->orWhereRaw("trim(upper(COALESCE(cod_especial, ''))) = trim(upper(?))", [$code]);
})->first(['id','codigo','cod_especial','precio']);
echo 'interno_row=' . json_encode($interno, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo 'ems_row=' . json_encode($ems, JSON_UNESCAPED_UNICODE) . PHP_EOL;
