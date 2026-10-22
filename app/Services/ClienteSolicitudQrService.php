<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteSolicitudQr;
use App\Models\Estado;
use App\Models\SolicitudCliente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClienteSolicitudQrService
{
    public function __construct(private ClienteSolicitudFacturacionService $facturacion) {}

    public function sync(ClienteSolicitudQr $qr): void
    {
        Cache::lock('cliente-qr-sync:'.$qr->id, 60)->block(2, function () use ($qr): void {
            $qr->refresh();
            if ($qr->status !== 'pending') {
                return;
            }
            $cliente = new Cliente;
            $cliente->id = (int) $qr->cliente_id;
            $payment = $this->paymentStatus($this->facturacion->consultarPagoQr($cliente, (int) $qr->cart_id));
            if ($payment === null) {
                throw new RuntimeException('Facturación no confirmó el estado del pago.');
            }

            DB::transaction(function () use ($qr, $payment): void {
                $locked = ClienteSolicitudQr::whereKey($qr->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'pending') {
                    return;
                }
                $locked->checked_at = now();
                if ($payment === 'paid') {
                    $locked->status = 'paid';
                    $locked->paid_at = now();
                    if ($locked->solicitud_id) {
                        $solicitud = SolicitudCliente::whereKey($locked->solicitud_id)->lockForUpdate()->firstOrFail();
                        $solicitud->update(['forma_pago' => 'pagado']);
                    }
                } elseif ($locked->expires_at->lte(now())) {
                    if ($locked->solicitud_id) {
                        $solicitud = SolicitudCliente::whereKey($locked->solicitud_id)->lockForUpdate()->firstOrFail();
                        if ($solicitud->forma_pago === 'origen') {
                            $estadoId = Estado::whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])->value('id');
                            if (! $estadoId) {
                                throw new RuntimeException('No existe el estado CANCELADO.');
                            }
                            $solicitud->update([
                                'estado_id' => $estadoId,
                                'justificacion' => trim($solicitud->justificacion."\nCancelación automática: QR sin pago confirmado al vencer las 48 horas."),
                            ]);
                        }
                    }
                    $locked->status = 'cancelled';
                    $locked->cancelled_at = now();
                }
                $locked->save();
            });
        });
    }

    public function paymentStatus(array $body): ?string
    {
        if (data_get($body, 'respuesta.ok') === false || data_get($body, 'respuesta.respuesta.ok') === false) {
            return null;
        }
        $statuses = [];
        foreach (['cart', 'respuesta', 'respuesta.respuesta', 'respuesta.qr_data', 'respuesta.respuesta.qr_data', 'respuesta.items.0'] as $prefix) {
            foreach (['estado_pago', 'estadoPago', 'payment_status', 'paymentStatus', 'payment_state', 'paymentState'] as $field) {
                $statuses[] = strtolower(trim((string) data_get($body, $prefix.'.'.$field, '')));
            }
        }
        $statuses[] = strtolower(trim((string) ($body['payment_status'] ?? '')));
        if (array_intersect($statuses, ['pagado', 'success', 'paid', 'completed', 'approved', 'confirmed'])) {
            return 'paid';
        }
        if (array_intersect($statuses, ['pendiente', 'pending', 'unpaid', 'no_pagado', 'vencido', 'expired', 'cancelado', 'cancelled', 'canceled'])) {
            return 'unpaid';
        }

        return null;
    }
}
