<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteInt;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Services\BitacoraFacturaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TransportInvoiceApiController extends Controller
{
    public function store(Request $request, BitacoraFacturaQrService $facturaQrService): JsonResponse
    {
        @set_time_limit(120);

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'cod_especial' => ['required', 'string', 'max:50'],
            'tipo_transporte' => ['required', 'in:aereo,terrestre'],
            'transportadora' => ['required_if:tipo_transporte,terrestre', 'nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'factura' => ['required', 'string', 'max:255'],
            'precio_total' => ['required', 'numeric', 'min:0'],
            'peso' => ['required', 'numeric', 'min:0', 'max:150'],
            'imagen_factura' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'peso.max' => 'El peso no puede ser mayor a 150 kg.',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $codEspecial = strtoupper(trim((string) $request->input('cod_especial')));
            if ($codEspecial === '') {
                return;
            }

            $bitacoraExists = Bitacora::query()
                ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                ->exists();

            if ($bitacoraExists) {
                $validator->errors()->add('cod_especial', 'Ya existe una factura de transporte para este código especial.');

                return;
            }

            $packageExists = PaqueteEms::query()
                ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                ->exists()
                || Recojo::query()
                    ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                    ->exists()
                || PaqueteOrdi::query()
                    ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                    ->exists()
                || PaqueteCerti::query()
                    ->where(function ($query) use ($codEspecial): void {
                        $query->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                            ->orWhereRaw("trim(upper(COALESCE(codigo, ''))) = ?", [$codEspecial]);
                    })
                    ->exists()
                || PaqueteInt::query()
                    ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                    ->exists()
                || (Schema::hasTable('solicitud_clientes') && SolicitudCliente::query()
                    ->whereRaw("trim(upper(COALESCE(cod_especial, ''))) = ?", [$codEspecial])
                    ->exists());

            if (! $packageExists) {
                $validator->errors()->add('cod_especial', 'No encontramos un envío con ese código especial.');
            }
        });

        $data = $validator->validate();
        $userId = (int) $data['user_id'];

        $file = $request->file('imagen_factura');
        $qrResult = $facturaQrService->extractFromUploadedFile($file);
        $invoiceData = ($qrResult['success'] ?? false) && is_array($qrResult['invoice_data'] ?? null)
            ? $qrResult['invoice_data']
            : [];
        $codEspecial = strtoupper(trim((string) $data['cod_especial']));
        $tipoTransporte = (string) $data['tipo_transporte'];
        $transportadora = $tipoTransporte === 'aereo'
            ? 'BOA'
            : strtoupper(trim((string) ($data['transportadora'] ?? '')));
        $provincia = strtoupper(trim((string) ($data['provincia'] ?? '')));
        $imagePath = null;

        try {
            $imagePath = $file->store('bitacoras/facturas', 'public');

            $bitacora = DB::transaction(fn (): Bitacora => Bitacora::query()->create([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => null,
                'user_id' => $userId,
                'cod_especial' => $codEspecial,
                'transportadora' => $transportadora,
                'provincia' => $provincia !== '' ? $provincia : null,
                'factura' => trim((string) $data['factura']),
                'precio_total' => $data['precio_total'],
                'peso' => $data['peso'],
                'qr_url' => $qrResult['qr_url'] ?? null,
                'qr_texto' => $qrResult['qr_text'] ?? null,
                'qr_datos' => $invoiceData !== [] ? $invoiceData : null,
                'factura_fecha_emision' => $this->normalizeInvoiceDate($invoiceData['fecha_emision'] ?? null),
                'factura_nit_emisor' => $invoiceData['nit_emisor'] ?? null,
                'factura_cuf' => $invoiceData['cuf'] ?? null,
                'factura_razon_social' => $invoiceData['razon_social_emisor'] ?? null,
                'factura_cliente' => $invoiceData['nombre_cliente'] ?? null,
                'factura_direccion' => $invoiceData['direccion_emisor'] ?? null,
                'imagen_factura' => $imagePath,
            ]));
        } catch (\Throwable $error) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $error;
        }

        return response()->json([
            'message' => 'Factura de transporte registrada correctamente.',
            'data' => [
                'id' => (int) $bitacora->id,
                'cod_especial' => $bitacora->cod_especial,
                'transportadora' => $bitacora->transportadora,
                'provincia' => $bitacora->provincia,
                'factura' => $bitacora->factura,
                'precio_total' => $bitacora->precio_total,
                'peso' => $bitacora->peso,
                'imagen_factura_url' => Storage::disk('public')->url($bitacora->imagen_factura),
                'created_at' => $bitacora->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function normalizeInvoiceDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($text)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
