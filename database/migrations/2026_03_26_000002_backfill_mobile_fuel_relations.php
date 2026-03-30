<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('fuel_invoices')
            || !Schema::hasTable('fuel_invoice_details')
            || !Schema::hasTable('fuel_logs')
            || !Schema::hasTable('vehicle_log')
        ) {
            return;
        }

        $rows = DB::table('fuel_invoice_details as fid')
            ->leftJoin('fuel_invoices as fi', 'fi.id', '=', 'fid.fuel_invoice_id')
            ->leftJoin('vehicle_log as vl', 'vl.fuel_log_id', '=', 'fid.id')
            ->select([
                'fid.id as detail_id',
                'fid.fuel_invoice_id',
                'fid.fuel_log_id as legacy_fuel_log_id',
                'fid.gas_station_id as detail_gas_station_id',
                'fid.cantidad',
                'fid.precio_unitario',
                'fid.subtotal',
                'fid.estado',
                'fi.numero',
                'fi.numero_factura',
                'fi.nombre_cliente',
                'fi.fecha_emision',
                'fi.gas_station_id as invoice_gas_station_id',
                'fi.siat_source_url',
                'fi.fuel_latitude',
                'fi.fuel_longitude',
                'fi.fuel_location_label',
                'fi.fuel_recorded_at',
                'fi.antifraud_payload_json',
                'vl.kilometraje_salida',
                'vl.kilometraje_llegada',
                'vl.recorrido_inicio',
                'vl.recorrido_destino',
                'vl.latitud_inicio',
                'vl.logitud_inicio',
                'vl.latitud_destino',
                'vl.logitud_destino',
            ])
            ->where(function ($query) {
                $query->whereNull('fid.fuel_log_id')
                    ->orWhereNull('fi.gas_station_id')
                    ->orWhereNull('fi.antifraud_payload_json');
            })
            ->orderBy('fid.id')
            ->get();

        foreach ($rows as $row) {
            if ($row->legacy_fuel_log_id === null) {
                $legacyId = DB::table('fuel_logs')->insertGetId([
                    'fecha' => $row->fecha_emision ?? $row->fuel_recorded_at ?? now(),
                    'galones' => $row->cantidad,
                    'precio_galon' => $row->precio_unitario,
                    'total_calculado' => $row->subtotal,
                    'kilometraje' => $row->kilometraje_llegada ?? $row->kilometraje_salida,
                    'recibo' => $row->numero_factura ?? $row->numero,
                    'observaciones' => 'BACKFILL MOVIL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('fuel_invoice_details')
                    ->where('id', $row->detail_id)
                    ->update([
                        'fuel_log_id' => $legacyId,
                        'updated_at' => now(),
                    ]);
            }

            $invoiceUpdates = [];
            if ($row->invoice_gas_station_id === null && $row->detail_gas_station_id !== null) {
                $invoiceUpdates['gas_station_id'] = $row->detail_gas_station_id;
            }
            if ($row->antifraud_payload_json === null) {
                $invoiceUpdates['antifraud_payload_json'] = json_encode([
                    'gps' => [
                        'latitude' => $row->fuel_latitude,
                        'longitude' => $row->fuel_longitude,
                        'label' => $row->fuel_location_label,
                        'recorded_at' => $row->fuel_recorded_at,
                    ],
                    'qr_payload' => $row->siat_source_url,
                    'route' => [
                        'start_label' => $row->recorrido_inicio,
                        'end_label' => $row->recorrido_destino,
                        'start_latitude' => $row->latitud_inicio,
                        'start_longitude' => $row->logitud_inicio,
                        'end_latitude' => $row->latitud_destino,
                        'end_longitude' => $row->logitud_destino,
                    ],
                    'odometer' => [
                        'start' => $row->kilometraje_salida,
                        'end' => $row->kilometraje_llegada,
                    ],
                    'client_context' => [
                        'source' => 'migration_backfill_mobile_fuel_relations',
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }

            if (!empty($invoiceUpdates)) {
                $invoiceUpdates['updated_at'] = now();
                DB::table('fuel_invoices')
                    ->where('id', $row->fuel_invoice_id)
                    ->update($invoiceUpdates);
            }
        }
    }

    public function down(): void
    {
        // Backfill irreversible.
    }
};
