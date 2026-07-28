<?php

function pdoFromEnv(string $envPath): PDO
{
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $config = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        $config[$key] = $value;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        trim((string) ($config['DB_HOST'] ?? '127.0.0.1')),
        trim((string) ($config['DB_PORT'] ?? '5432')),
        trim((string) ($config['DB_DATABASE'] ?? ''))
    );

    $pdo = new PDO(
        $dsn,
        trim((string) ($config['DB_USERNAME'] ?? '')),
        trim((string) ($config['DB_PASSWORD'] ?? '')),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

function normalizeAdmisiones(string $nombre): array
{
    $nombre = strtoupper(trim($nombre));

    return match ($nombre) {
        'CERTIFICADAS' => ['titulo' => 'Certificadas', 'nombre_servicio' => 'Certificadas', 'descripcion_servicio' => 'Servicio Certificadas - Envio de paqueteria'],
        'ORDINARIAS' => ['titulo' => 'Ordinarias', 'nombre_servicio' => 'Ordinarias', 'descripcion_servicio' => 'Servicio Ordinarias - Envio de paqueteria'],
        'CIUDADES_INTERMEDIAS' => ['titulo' => 'Ciudades Intermedias', 'nombre_servicio' => 'Ciudades Intermedias', 'descripcion_servicio' => 'Servicio Ciudades Intermedias - Envio de paqueteria'],
        'CIUDADES_INTERMEDIAS_TRINIDAD_COBIJA' => ['titulo' => 'Ciudades Intermedias Trinidad Cobija', 'nombre_servicio' => 'Ciudades Intermedias Trinidad Cobija', 'descripcion_servicio' => 'Servicio Ciudades Intermedias Trinidad Cobija - Envio de paqueteria'],
        'ECA' => ['titulo' => 'ECA', 'nombre_servicio' => 'ECA', 'descripcion_servicio' => 'Servicio ECA - Envio de paqueteria'],
        'EMS_LOCAL_COBERTURA_1' => ['titulo' => 'EMS Local Cobertura 1', 'nombre_servicio' => 'EMS Local Cobertura 1', 'descripcion_servicio' => 'Servicio EMS Local Cobertura 1 - Envio de paqueteria'],
        'EMS_LOCAL_COBERTURA_2' => ['titulo' => 'EMS Local Cobertura 2', 'nombre_servicio' => 'EMS Local Cobertura 2', 'descripcion_servicio' => 'Servicio EMS Local Cobertura 2 - Envio de paqueteria'],
        'EMS_LOCAL_COBERTURA_3' => ['titulo' => 'EMS Local Cobertura 3', 'nombre_servicio' => 'EMS Local Cobertura 3', 'descripcion_servicio' => 'Servicio EMS Local Cobertura 3 - Envio de paqueteria'],
        'EMS_LOCAL_COBERTURA_4' => ['titulo' => 'EMS Local Cobertura 4', 'nombre_servicio' => 'EMS Local Cobertura 4', 'descripcion_servicio' => 'Servicio EMS Local Cobertura 4 - Envio de paqueteria'],
        'EMS_NACIONAL' => ['titulo' => 'EMS Nacional', 'nombre_servicio' => 'EMS Nacional', 'descripcion_servicio' => 'Servicio EMS Nacional - Envio de paqueteria'],
        'ENCOMIENDA' => ['titulo' => 'Encomienda', 'nombre_servicio' => 'Encomienda', 'descripcion_servicio' => 'Servicio Encomienda - Envio de paqueteria'],
        'INTERNACIONAL' => ['titulo' => 'Internacional', 'nombre_servicio' => 'Internacional', 'descripcion_servicio' => 'Servicio Internacional - Envio de paqueteria'],
        'SUPER_EXPRESS_NACIONAL' => ['titulo' => 'Super Express Nacional', 'nombre_servicio' => 'Super Express Nacional', 'descripcion_servicio' => 'Servicio Super Express Nacional - Envio de paqueteria'],
        'TRINIDAD_COBIJA' => ['titulo' => 'Trinidad Cobija', 'nombre_servicio' => 'Trinidad Cobija', 'descripcion_servicio' => 'Servicio Trinidad Cobija - Envio de paqueteria'],
        default => ['titulo' => $nombre, 'nombre_servicio' => $nombre, 'descripcion_servicio' => $nombre],
    };
}

function normalizeConcepto(string $nombre): array
{
    $nombre = strtoupper(trim($nombre));

    return match ($nombre) {
        'AEROLINEA' => ['titulo' => 'Aerolinea', 'nombre_servicio' => 'Aerolinea', 'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio'],
        'CASILLA' => ['titulo' => 'Casilla', 'nombre_servicio' => 'Casilla', 'descripcion_servicio' => 'Servicio Casilla - Pago casilla'],
        'EMS INTERNACIONAL' => ['titulo' => 'EMS Internacional', 'nombre_servicio' => 'EMS Internacional', 'descripcion_servicio' => 'Servicio EMS Internacional - Entrega/Envio de Paqueteria'],
        'ENCOMIENDA INTERNACIONAL' => ['titulo' => 'Encomienda Internacional', 'nombre_servicio' => 'Encomienda Internacional', 'descripcion_servicio' => 'Servicio Encomienda Internacional - Entrega/Envio de Paqueteria'],
        'ESTAMPILLAS' => ['titulo' => 'Estampillas', 'nombre_servicio' => 'Estampillas', 'descripcion_servicio' => 'Servicio Venta de Estampillas - Venta'],
        'ORDINARIAS INTERNACIONAL' => ['titulo' => 'Ordinarias Internacional', 'nombre_servicio' => 'Ordinarias Internacional', 'descripcion_servicio' => 'Servicio Ordinaria Internacional - Entrega/Envio de Paqueteria'],
        'TARJETA POSTAL' => ['titulo' => 'Tarjeta postal', 'nombre_servicio' => 'Tarjeta postal', 'descripcion_servicio' => 'Servicio Venta de Tarjeta Postal - Venta'],
        default => ['titulo' => $nombre, 'nombre_servicio' => $nombre, 'descripcion_servicio' => $nombre],
    };
}

$bridgePdo = pdoFromEnv('C:/xampp/htdocs/proyectos/apifacturacionagbc/.env');
$bolipostPdo = pdoFromEnv(__DIR__ . '/../.env');

$bridgeVentasStmt = $bridgePdo->query("
    select id, \"codigoOrden\" as codigo_orden, numero_factura, origen_venta_id
    from ventas
    where origen_sucursal_nombre = 'COBIJA'
    order by id
");
$ventas = $bridgeVentasStmt->fetchAll();

$cartIds = array_values(array_unique(array_map(
    static fn (array $venta): int => (int) $venta['origen_venta_id'],
    array_filter($ventas, static fn (array $venta): bool => (int) $venta['origen_venta_id'] > 0)
)));

if ($cartIds === []) {
    echo json_encode(['items' => 0, 'detalles' => 0, 'respuestas' => 0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$cartIdList = implode(',', array_map('intval', $cartIds));
$itemsStmt = $bridgePdo->query("
    select id, cart_id, origen_tipo, origen_id, codigo, titulo, nombre_servicio, resumen_origen
    from facturacion_cart_items
    where cart_id in ($cartIdList)
    order by cart_id, id
");
$items = $itemsStmt->fetchAll();

$paqueteIds = [];
$conceptoIds = [];
foreach ($items as $item) {
    $tipo = ltrim((string) $item['origen_tipo'], '\\');
    if ($tipo === 'App\Models\PaqueteEms') {
        $paqueteIds[] = (int) $item['origen_id'];
        continue;
    }

    if ($tipo === 'App\Models\ConceptoFacturacion') {
        $resumen = json_decode((string) ($item['resumen_origen'] ?? '{}'), true) ?: [];
        $conceptoIds[] = (int) (($resumen['concepto_facturacion_id'] ?? 0) ?: ($item['origen_id'] ?? 0));
    }
}

$paqueteMap = [];
if ($paqueteIds !== []) {
    $paqueteIdList = implode(',', array_map('intval', array_values(array_unique($paqueteIds))));
    $stmt = $bolipostPdo->query("
        select p.id as paquete_id, s.nombre_servicio
        from paquetes_ems p
        left join tarifario t on t.id = p.tarifario_id
        left join servicio s on s.id = t.servicio_id
        where p.id in ($paqueteIdList)
    ");
    foreach ($stmt->fetchAll() as $row) {
        $paqueteMap[(int) $row['paquete_id']] = $row;
    }
}

$conceptoMap = [];
if ($conceptoIds !== []) {
    $conceptoIdList = implode(',', array_map('intval', array_values(array_unique($conceptoIds))));
    $stmt = $bolipostPdo->query("
        select id, nombre
        from conceptos_facturacion
        where id in ($conceptoIdList)
    ");
    foreach ($stmt->fetchAll() as $row) {
        $conceptoMap[(int) $row['id']] = $row;
    }
}

$expectedByItemId = [];
foreach ($items as $item) {
    $tipo = ltrim((string) $item['origen_tipo'], '\\');

    if ($tipo === 'App\Models\PaqueteEms') {
        $source = $paqueteMap[(int) $item['origen_id']] ?? null;
        if ($source) {
            $expectedByItemId[(int) $item['id']] = normalizeAdmisiones((string) $source['nombre_servicio']);
        }
        continue;
    }

    if ($tipo === 'App\Models\ConceptoFacturacion') {
        $resumen = json_decode((string) ($item['resumen_origen'] ?? '{}'), true) ?: [];
        $conceptoId = (int) (($resumen['concepto_facturacion_id'] ?? 0) ?: ($item['origen_id'] ?? 0));
        $source = $conceptoMap[$conceptoId] ?? null;
        if ($source) {
            $expectedByItemId[(int) $item['id']] = normalizeConcepto((string) $source['nombre']);
        }
    }
}

$updatedItems = 0;
$updatedDetalles = 0;
$updatedRespuestas = 0;

$bridgePdo->beginTransaction();

try {
    foreach ($items as $item) {
        $itemId = (int) $item['id'];
        $expected = $expectedByItemId[$itemId] ?? null;
        if (!$expected) {
            continue;
        }

        $resumen = json_decode((string) ($item['resumen_origen'] ?? '{}'), true) ?: [];
        $resumen['descripcion_servicio'] = $expected['descripcion_servicio'];
        $resumen['servicio_nombre'] = strtoupper($expected['nombre_servicio']);
        $resumen['codigo_servicio'] = strtoupper(str_replace(' ', '_', $expected['nombre_servicio']));

        $updateItemStmt = $bridgePdo->prepare("
            update facturacion_cart_items
            set titulo = :titulo,
                nombre_servicio = :nombre_servicio,
                resumen_origen = :resumen_origen,
                updated_at = now()
            where id = :id
        ");
        $updateItemStmt->execute([
            ':titulo' => $expected['titulo'],
            ':nombre_servicio' => $expected['nombre_servicio'],
            ':resumen_origen' => json_encode($resumen, JSON_UNESCAPED_UNICODE),
            ':id' => $itemId,
        ]);
        $updatedItems++;

        $updateDetalleStmt = $bridgePdo->prepare("
            update detalle_ventas
            set descripcion = :descripcion,
                updated_at = now()
            where id in (
                select dv.id
                from detalle_ventas dv
                inner join ventas v on v.id = dv.venta_id
                where v.origen_venta_id = :cart_id
                  and dv.codigo = :codigo
            )
        ");
        $updateDetalleStmt->execute([
            ':descripcion' => $expected['descripcion_servicio'],
            ':cart_id' => (int) $item['cart_id'],
            ':codigo' => (string) $item['codigo'],
        ]);
        $updatedDetalles += $updateDetalleStmt->rowCount();

        $cartStmt = $bridgePdo->prepare("
            select id, respuesta_emision
            from facturacion_carts
            where id = :id
        ");
        $cartStmt->execute([':id' => (int) $item['cart_id']]);
        $cart = $cartStmt->fetch();
        if (!$cart) {
            continue;
        }

        $respuesta = json_decode((string) ($cart['respuesta_emision'] ?? '{}'), true);
        if (!is_array($respuesta)) {
            continue;
        }

        $changed = false;
        $paths = [
            ['consultaSefe', 'detalleFactura', 'detalle'],
            ['factura', 'detalleFactura', 'detalle'],
            ['detalleFactura', 'detalle'],
            ['detalle'],
        ];

        foreach ($paths as $path) {
            $node = &$respuesta;
            $ok = true;
            foreach ($path as $segment) {
                if (!is_array($node) || !array_key_exists($segment, $node)) {
                    $ok = false;
                    break;
                }
                $node = &$node[$segment];
            }

            if ($ok && is_array($node)) {
                foreach ($node as &$row) {
                    if (is_array($row) && (($row['codigo'] ?? null) === (string) $item['codigo'])) {
                        $row['descripcion'] = strtoupper($expected['descripcion_servicio']);
                        $changed = true;
                    }
                }
                unset($row);
            }
            unset($node);
        }

        if ($changed) {
            $updateCartStmt = $bridgePdo->prepare("
                update facturacion_carts
                set respuesta_emision = :respuesta_emision,
                    updated_at = now()
                where id = :id
            ");
            $updateCartStmt->execute([
                ':respuesta_emision' => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
                ':id' => (int) $cart['id'],
            ]);
            $updatedRespuestas++;
        }
    }

    $bridgePdo->commit();
} catch (Throwable $e) {
    $bridgePdo->rollBack();
    throw $e;
}

echo json_encode([
    'items' => $updatedItems,
    'detalles' => $updatedDetalles,
    'respuestas' => $updatedRespuestas,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
