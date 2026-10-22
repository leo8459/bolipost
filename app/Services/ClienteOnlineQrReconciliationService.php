<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\SolicitudCliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClienteOnlineQrReconciliationService
{
    private const QR_EXPIRATION_HOURS = 48;

    private const EXPIRATION_JUSTIFICATION = 'Cancelación automática: QR sin pago confirmado al vencer las 48 horas.';

    public function __construct(private ClienteSolicitudFacturacionService $facturacion) {}

    public function syncPending(): array
    {
        $counts = ['checked' => 0, 'paid' => 0, 'cancelled' => 0, 'failures' => 0];
        $afterId = 0;

        do {
            $response = $this->facturacion->listarQrClienteOnlinePendientes($afterId, 100);
            $carts = (array) data_get($response, 'data.carts', []);
            $nextAfterId = (int) data_get($response, 'data.pagination.next_after_id', $afterId);
            if ($nextAfterId <= $afterId && $carts !== []) {
                $nextAfterId = max(array_map(fn ($cart) => (int) data_get($cart, 'id', 0), $carts));
            }

            foreach ($carts as $cart) {
                if (!is_array($cart)) {
                    continue;
                }

                try {
                    $result = $this->syncCart($cart);
                    $counts['checked'] += $result['checked'] ? 1 : 0;
                    $counts['paid'] += $result['paid'] ? 1 : 0;
                    $counts['cancelled'] += $result['cancelled'] ? 1 : 0;
                } catch (\Throwable $exception) {
                    $counts['failures']++;
                    Log::warning('No se pudo reconciliar un QR de cliente online; se reintentará en la siguiente ejecución.', [
                        'cart_id' => (int) ($cart['id'] ?? 0),
                        'origen_usuario_id' => (string) ($cart['origen_usuario_id'] ?? ''),
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if ($nextAfterId <= $afterId) {
                break;
            }
            $afterId = $nextAfterId;
        } while (true);

        return $counts;
    }

    private function syncCart(array $cart): array
    {
        $cartId = (int) ($cart['id'] ?? 0);
        $originUserId = trim((string) ($cart['origen_usuario_id'] ?? ''));
        if ($cartId <= 0 || !preg_match('/^cliente_online_(\d+)$/', $originUserId, $matches)) {
            return ['checked' => false, 'paid' => false, 'cancelled' => false];
        }

        $item = collect((array) ($cart['items'] ?? []))
            ->first(fn ($candidate) => strtolower((string) data_get($candidate, 'origen_tipo', '')) === 'cliente_online');
        if (!is_array($item)) {
            return ['checked' => false, 'paid' => false, 'cancelled' => false];
        }

        $summary = (array) data_get($item, 'resumen_origen', []);
        $createdAtRaw = trim((string) ($cart['created_at'] ?? ''));
        if ($createdAtRaw === '') {
            throw new RuntimeException('Facturación no devolvió la fecha de creación del QR.');
        }
        $createdAt = Carbon::parse($createdAtRaw);
        $expired = $createdAt->lte(now()->subHours(self::QR_EXPIRATION_HOURS));
        $clienteId = (int) $matches[1];
        $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
        $solicitud = $solicitudId > 0
            ? SolicitudCliente::query()->where('cliente_id', $clienteId)->find($solicitudId)
            : null;

        // Before payment there normally is no local shipment row. Once the
        // remote QR is older than 48h, only linked, still-active local rows
        // need a final reconciliation; the QR itself remains untouched.
        if ($expired && !$this->solicitudNeedsExpiryAction($solicitud)) {
            return ['checked' => false, 'paid' => false, 'cancelled' => false];
        }

        return Cache::lock('cliente-online-qr-reconcile:'.$cartId, 55)->block(2, function () use (
            $cartId,
            $originUserId,
            $clienteId,
            $solicitudId,
            $expired
        ): array {
            $solicitud = $solicitudId > 0
                ? SolicitudCliente::query()->where('cliente_id', $clienteId)->find($solicitudId)
                : null;
            if ($expired && !$this->solicitudNeedsExpiryAction($solicitud)) {
                return ['checked' => false, 'paid' => false, 'cancelled' => false];
            }

            $payment = $this->facturacion->consultarPagoQrPorUsuario($originUserId, $cartId);
            if (empty($payment['payment_status_confirmed'])) {
                throw new RuntimeException('Facturación no confirmó el estado del pago.');
            }

            $status = $this->normalizePaymentStatus((string) ($payment['payment_status'] ?? ''));
            if ($status === 'unknown') {
                throw new RuntimeException('Facturación devolvió un estado de pago no reconocido.');
            }
            if ($status === 'paid') {
                $updated = $this->markLinkedSolicitudAsPaid($clienteId, $solicitudId);

                return ['checked' => true, 'paid' => $updated, 'cancelled' => false];
            }

            if ($expired && in_array($status, ['pending', 'cancelled'], true)) {
                $updated = $this->cancelLinkedSolicitud($clienteId, $solicitudId);

                return ['checked' => true, 'paid' => false, 'cancelled' => $updated];
            }

            return ['checked' => true, 'paid' => false, 'cancelled' => false];
        });
    }

    private function solicitudNeedsExpiryAction(?SolicitudCliente $solicitud): bool
    {
        if (!$solicitud || $solicitud->forma_pago !== 'origen') {
            return false;
        }

        $cancelledStateId = (int) Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');
        if ($cancelledStateId <= 0) {
            throw new RuntimeException('No existe el estado CANCELADO.');
        }

        return (int) $solicitud->estado_id !== $cancelledStateId;
    }

    private function markLinkedSolicitudAsPaid(int $clienteId, int $solicitudId): bool
    {
        if ($solicitudId <= 0) {
            return false;
        }

        return DB::transaction(function () use ($clienteId, $solicitudId): bool {
            $solicitud = SolicitudCliente::query()
                ->where('cliente_id', $clienteId)
                ->whereKey($solicitudId)
                ->lockForUpdate()
                ->first();
            if (!$solicitud || $solicitud->forma_pago !== 'origen') {
                return false;
            }

            $solicitud->update([
                'forma_pago' => 'pagado',
                'pago_destinatario' => false,
            ]);

            return true;
        });
    }

    private function cancelLinkedSolicitud(int $clienteId, int $solicitudId): bool
    {
        if ($solicitudId <= 0) {
            return false;
        }

        return DB::transaction(function () use ($clienteId, $solicitudId): bool {
            $solicitud = SolicitudCliente::query()
                ->where('cliente_id', $clienteId)
                ->whereKey($solicitudId)
                ->lockForUpdate()
                ->first();
            if (!$solicitud || $solicitud->forma_pago !== 'origen') {
                return false;
            }

            $cancelledStateId = (int) Estado::query()
                ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
                ->value('id');
            if ($cancelledStateId <= 0) {
                throw new RuntimeException('No existe el estado CANCELADO.');
            }
            if ((int) $solicitud->estado_id === $cancelledStateId) {
                return false;
            }

            $justificacion = trim((string) $solicitud->justificacion);
            if (!str_contains($justificacion, self::EXPIRATION_JUSTIFICATION)) {
                $justificacion = trim($justificacion."\n".self::EXPIRATION_JUSTIFICATION);
            }

            $solicitud->update([
                'estado_id' => $cancelledStateId,
                'justificacion' => $justificacion,
            ]);

            return true;
        });
    }

    private function normalizePaymentStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pagado', 'paid', 'success', 'successful', 'succeeded', 'completed', 'approved', 'confirmed' => 'paid',
            'cancelado', 'cancelled', 'canceled', 'rejected', 'failed', 'failure', 'expired', 'error' => 'cancelled',
            'pendiente', 'pending', 'holding', 'unpaid', 'no_pagado' => 'pending',
            default => 'unknown',
        };
    }
}
