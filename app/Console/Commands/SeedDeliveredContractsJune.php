<?php

namespace App\Console\Commands;

use App\Models\Estado;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SeedDeliveredContractsJune extends Command
{
    protected $signature = 'contratos:seed-entregados-junio
        {--count=100 : Cantidad total de envios entregados a generar}
        {--year=2026 : Gestion para el mes a poblar}
        {--month=6 : Mes a poblar}
        {--company-id= : Empresa destino}
        {--user-id= : Usuario creador/asignado}';

    protected $description = 'Genera contratos entregados de prueba distribuidos por dia con al menos dos entregas por fecha.';

    public function handle(): int
    {
        $count = max(60, (int) $this->option('count'));
        $year = max(2020, (int) $this->option('year'));
        $month = max(1, min(12, (int) $this->option('month')));
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $empresaId = (int) ($this->option('company-id') ?: 0);
        $userId = (int) ($this->option('user-id') ?: 0);
        $user = $this->resolveUser($userId, $empresaId);

        if (! $user) {
            $this->error('No se encontro un usuario con empresa asignada para generar los entregados.');
            return self::FAILURE;
        }

        $empresaId = (int) $user->empresa_id;
        $estadoEntregadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id') ?? 0);

        if ($estadoEntregadoId <= 0) {
            $this->error('No existe el estado ENTREGADO en la tabla estados.');
            return self::FAILURE;
        }

        if (! DB::table('eventos')->where('id', 318)->exists() || ! DB::table('eventos')->where('id', 316)->exists()) {
            $this->error('Faltan los eventos 318 o 316 en la tabla eventos.');
            return self::FAILURE;
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?: 'LA PAZ')));
        $destinos = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
        $contenidos = ['DOCUMENTOS', 'MUESTRA COMERCIAL', 'REPUESTOS', 'CATALOGOS', 'INSUMOS', 'ARCHIVO', 'SOBRE CORPORATIVO'];
        $remitentes = ['EMPRESA DEMO', 'LOGISTICA BOLIVIA', 'SERVICIOS INTEGRALES', 'CORP EXPRESS', 'COMERCIAL ANDINA'];
        $nombres = ['JUAN PEREZ', 'MARIA QUISPE', 'CARLOS ROJAS', 'ANA TORREZ', 'LUIS FLORES', 'ROSA MAMANI', 'PEDRO VEIZAGA', 'ELENA VARGAS'];
        $apellidos = ['CONDORI', 'MOLLO', 'ALVAREZ', 'SALAZAR', 'CHOQUE', 'MENDOZA', 'GUTIERREZ', 'RAMOS'];

        $basePerDay = array_fill(1, $daysInMonth, 2);
        $remaining = $count - array_sum($basePerDay);
        for ($i = 0; $i < $remaining; $i++) {
            $basePerDay[($i % $daysInMonth) + 1]++;
        }

        $created = 0;

        DB::transaction(function () use ($basePerDay, $year, $month, $empresaId, $user, $estadoEntregadoId, $origen, $destinos, $contenidos, $remitentes, $nombres, $apellidos, &$created) {
            foreach ($basePerDay as $day => $itemsForDay) {
                for ($slot = 0; $slot < $itemsForDay; $slot++) {
                    $sequence = $created + 1;
                    $createdAt = Carbon::create($year, $month, $day, 8 + ($slot % 9), ($slot * 7) % 60, 0);
                    $registeredAt = $createdAt->copy()->subHours(2);
                    $codigo = sprintf('CDEMOJ%s%02d%03dBO', substr((string) $year, -2), $day, $sequence);
                    $destino = $destinos[($day + $slot) % count($destinos)];
                    $remitente = $remitentes[$sequence % count($remitentes)] . ' ' . (($sequence % 9) + 1);
                    $destinatario = $nombres[$sequence % count($nombres)] . ' ' . $apellidos[($sequence + 2) % count($apellidos)];
                    $peso = number_format(0.350 + (($sequence % 11) * 0.185), 3, '.', '');
                    $telefonoR = '7' . str_pad((string) (1000000 + (($sequence * 37) % 8999999)), 7, '0', STR_PAD_LEFT);
                    $telefonoD = '6' . str_pad((string) (1000000 + (($sequence * 53) % 8999999)), 7, '0', STR_PAD_LEFT);

                    $contratoId = DB::table('paquetes_contrato')->insertGetId([
                        'user_id' => (int) $user->id,
                        'empresa_id' => $empresaId,
                        'codigo' => $codigo,
                        'cod_especial' => null,
                        'estados_id' => $estadoEntregadoId,
                        'origen' => $origen,
                        'destino' => $destino,
                        'nombre_r' => $remitente,
                        'telefono_r' => $telefonoR,
                        'contenido' => $contenidos[$sequence % count($contenidos)],
                        'cantidad' => (string) (($sequence % 4) + 1),
                        'direccion_r' => 'AV. EMPRESA ' . (($sequence % 45) + 100),
                        'nombre_d' => $destinatario,
                        'telefono_d' => $telefonoD,
                        'direccion_d' => 'ZONA CENTRAL CALLE ' . (($sequence % 70) + 1),
                        'mapa' => null,
                        'provincia' => null,
                        'peso' => $peso,
                        'precio' => null,
                        'tarifa_contrato_id' => null,
                        'fecha_recojo' => $registeredAt->format('Y-m-d H:i:s'),
                        'observacion' => 'ENTREGA DEMO DE JUNIO',
                        'justificacion' => null,
                        'imagen' => null,
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                    ]);

                    DB::table('eventos_contrato')->insert([
                        [
                            'codigo' => $codigo,
                            'evento_id' => 318,
                            'user_id' => (int) $user->id,
                            'created_at' => $registeredAt->format('Y-m-d H:i:s'),
                            'updated_at' => $registeredAt->format('Y-m-d H:i:s'),
                        ],
                        [
                            'codigo' => $codigo,
                            'evento_id' => 316,
                            'user_id' => (int) $user->id,
                            'created_at' => $createdAt->format('Y-m-d H:i:s'),
                            'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                        ],
                    ]);

                    DB::table('cartero')->insert([
                        'id_paquetes_ems' => null,
                        'id_paquetes_certi' => null,
                        'id_paquetes_ordi' => null,
                        'id_paquetes_contrato' => $contratoId,
                        'id_solicitud_cliente' => null,
                        'id_estados' => $estadoEntregadoId,
                        'id_user' => (int) $user->id,
                        'recibido_por' => $destinatario,
                        'descripcion' => 'Entrega demo generada automaticamente',
                        'imagen' => null,
                        'imagen_devolucion' => null,
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $createdAt->format('Y-m-d H:i:s'),
                    ]);

                    $created++;
                }
            }
        });

        $this->info("Se generaron {$created} contratos entregados para {$month}/{$year} en la empresa #{$empresaId} usando el usuario #{$user->id}.");

        return self::SUCCESS;
    }

    private function resolveUser(int $userId, int $empresaId): ?User
    {
        if ($userId > 0) {
            return User::query()->whereKey($userId)->whereNotNull('empresa_id')->first();
        }

        if ($empresaId > 0) {
            return User::query()->where('empresa_id', $empresaId)->orderBy('id')->first();
        }

        return User::query()->whereNotNull('empresa_id')->orderBy('id')->first();
    }
}
