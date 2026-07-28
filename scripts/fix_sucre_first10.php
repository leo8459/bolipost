<?php

require 'C:/xampp/htdocs/proyectos/apifacturacionagbc/vendor/autoload.php';

$app = require 'C:/xampp/htdocs/proyectos/apifacturacionagbc/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$map = [
    49 => [[
        'titulo' => 'Aerolinea',
        'nombre_servicio' => 'Aerolinea',
        'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
        'codigo_servicio' => 'AEROLINEA',
        'servicio_nombre' => 'AEROLINEA',
    ]],
    62 => [[
        'titulo' => 'Ciudades Intermedias',
        'nombre_servicio' => 'Ciudades Intermedias',
        'descripcion_servicio' => 'Servicio Ciudades Intermedias - Envio de paqueteria',
        'codigo_servicio' => 'CIUDADES_INTERMEDIAS',
        'servicio_nombre' => 'CIUDADES INTERMEDIAS',
    ]],
    83 => [[
        'titulo' => 'EMS Nacional',
        'nombre_servicio' => 'EMS Nacional',
        'descripcion_servicio' => 'Servicio EMS Nacional - Envio de paqueteria',
        'codigo_servicio' => 'EMS_NACIONAL',
        'servicio_nombre' => 'EMS NACIONAL',
    ]],
    136 => [[
        'titulo' => 'Aerolinea',
        'nombre_servicio' => 'Aerolinea',
        'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
        'codigo_servicio' => 'AEROLINEA',
        'servicio_nombre' => 'AEROLINEA',
    ]],
    233 => [[
        'titulo' => 'EMS Nacional',
        'nombre_servicio' => 'EMS Nacional',
        'descripcion_servicio' => 'Servicio EMS Nacional - Envio de paqueteria',
        'codigo_servicio' => 'EMS_NACIONAL',
        'servicio_nombre' => 'EMS NACIONAL',
    ]],
    252 => [[
        'titulo' => 'Aerolinea',
        'nombre_servicio' => 'Aerolinea',
        'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
        'codigo_servicio' => 'AEROLINEA',
        'servicio_nombre' => 'AEROLINEA',
    ]],
    261 => [[
        'titulo' => 'Aerolinea',
        'nombre_servicio' => 'Aerolinea',
        'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
        'codigo_servicio' => 'AEROLINEA',
        'servicio_nombre' => 'AEROLINEA',
    ]],
    361 => [[
        'titulo' => 'EMS Nacional',
        'nombre_servicio' => 'EMS Nacional',
        'descripcion_servicio' => 'Servicio EMS Nacional - Envio de paqueteria',
        'codigo_servicio' => 'EMS_NACIONAL',
        'servicio_nombre' => 'EMS NACIONAL',
    ]],
    365 => [[
        'titulo' => 'EMS Nacional',
        'nombre_servicio' => 'EMS Nacional',
        'descripcion_servicio' => 'Servicio EMS Nacional - Envio de paqueteria',
        'codigo_servicio' => 'EMS_NACIONAL',
        'servicio_nombre' => 'EMS NACIONAL',
    ]],
    374 => [
        'by_concepto' => [
            1 => [
                'titulo' => 'Aerolinea',
                'nombre_servicio' => 'Aerolinea',
                'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
                'codigo_servicio' => 'AEROLINEA',
                'servicio_nombre' => 'AEROLINEA',
            ],
            4 => [
                'titulo' => 'EMS Internacional',
                'nombre_servicio' => 'EMS Internacional',
                'descripcion_servicio' => 'Servicio EMS Internacional - Entrega/Envio de Paqueteria',
                'codigo_servicio' => 'EMS_INTERNACIONAL',
                'servicio_nombre' => 'EMS INTERNACIONAL',
            ],
        ],
        'by_codigo' => [
            'SRVE-0' => 'Servicio Aerolinea - Pago de envio',
            'SRVE-3' => 'Servicio EMS Internacional - Entrega/Envio de Paqueteria',
        ],
    ],
];

$ventaIds = [27, 43, 64, 117, 220, 239, 249, 347, 351, 360];

DB::transaction(function () use ($map, $ventaIds): void {
    $ventas = DB::table('ventas')
        ->whereIn('id', $ventaIds)
        ->get(['id', 'origen_venta_id'])
        ->keyBy('origen_venta_id');

    foreach ($map as $cartId => $expectedList) {
        $items = DB::table('facturacion_cart_items')
            ->where('cart_id', $cartId)
            ->orderBy('id')
            ->get();

        foreach ($items as $idx => $item) {
            $resumen = json_decode($item->resumen_origen ?? '{}', true);
            if (!is_array($resumen)) {
                $resumen = [];
            }

            if (isset($expectedList['by_concepto'])) {
                $conceptoId = (int) ($resumen['concepto_facturacion_id'] ?? 0);
                if ($conceptoId <= 0 || !isset($expectedList['by_concepto'][$conceptoId])) {
                    continue;
                }
                $expected = $expectedList['by_concepto'][$conceptoId];
            } else {
                if (!isset($expectedList[$idx])) {
                    continue;
                }
                $expected = $expectedList[$idx];
            }

            $resumen['descripcion_servicio'] = $expected['descripcion_servicio'];
            $resumen['codigo_servicio'] = $expected['codigo_servicio'];
            $resumen['servicio_nombre'] = $expected['servicio_nombre'];

            DB::table('facturacion_cart_items')
                ->where('id', $item->id)
                ->update([
                    'titulo' => $expected['titulo'],
                    'nombre_servicio' => $expected['nombre_servicio'],
                    'resumen_origen' => json_encode($resumen, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }

        if (!isset($ventas[$cartId])) {
            continue;
        }

        $ventaId = $ventas[$cartId]->id;
        $detalles = DB::table('detalle_ventas')
            ->where('venta_id', $ventaId)
            ->orderBy('id')
            ->get();

        foreach ($detalles as $idx => $detalle) {
            if (isset($expectedList['by_codigo'])) {
                $codigo = trim((string) ($detalle->codigo ?? ''));
                if ($codigo === '' || !isset($expectedList['by_codigo'][$codigo])) {
                    continue;
                }

                DB::table('detalle_ventas')
                    ->where('id', $detalle->id)
                    ->update([
                        'descripcion' => $expectedList['by_codigo'][$codigo],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if (!isset($expectedList[$idx])) {
                continue;
            }

            DB::table('detalle_ventas')
                ->where('id', $detalle->id)
                ->update([
                    'descripcion' => $expectedList[$idx]['descripcion_servicio'],
                    'updated_at' => now(),
                ]);
        }
    }
});

echo "OK\n";
