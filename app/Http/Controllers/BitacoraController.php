<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BitacoraController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $userId = (int) $request->query('user_id', 0);
        $codEspecial = strtoupper(trim((string) $request->query('cod_especial', '')));
        $provincia = strtoupper(trim((string) $request->query('provincia', '')));

        $bitacoras = Bitacora::query()
            ->with([
                'user:id,name',
                'paqueteEms:id,codigo,cod_especial',
                'paqueteContrato:id,codigo,cod_especial',
                'paqueteOrdi:id,codigo,cod_especial',
                'paqueteCerti:id,codigo,cod_especial',
            ])
            ->when($userId > 0, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->when($codEspecial !== '', function ($query) use ($codEspecial) {
                $query->whereRaw('trim(upper(cod_especial)) = ?', [$codEspecial]);
            })
            ->when($provincia !== '', function ($query) use ($provincia) {
                $query->whereRaw('trim(upper(COALESCE(provincia, \'\'))) = ?', [$provincia]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('cod_especial', 'ILIKE', "%{$q}%")
                        ->orWhere('transportadora', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia', 'ILIKE', "%{$q}%")
                        ->orWhere('factura', 'ILIKE', "%{$q}%")
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteEms', function ($emsQuery) use ($q) {
                            $emsQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteContrato', function ($contratoQuery) use ($q) {
                            $contratoQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteOrdi', function ($ordiQuery) use ($q) {
                            $ordiQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('paqueteCerti', function ($certiQuery) use ($q) {
                            $certiQuery->where('codigo', 'ILIKE', "%{$q}%")
                                ->orWhere('cod_especial', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $provincias = Bitacora::query()
            ->select('provincia')
            ->whereNotNull('provincia')
            ->whereRaw("trim(provincia) <> ''")
            ->distinct()
            ->orderBy('provincia')
            ->pluck('provincia');

        return view('bitacoras.index', compact('bitacoras', 'users', 'provincias', 'q', 'userId', 'codEspecial', 'provincia'));
    }

    public function create(): View
    {
        return view('bitacoras.create', [
            'bitacora' => new Bitacora(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validateStoreData($request);
        $createdOrUpdated = $this->storeByCodEspecial($payload);

        return redirect()
            ->route('bitacoras.index')
            ->with('success', $createdOrUpdated . ' bitacora(s) registrada(s) correctamente para el cod_especial ' . $payload['cod_especial'] . '.');
    }

    public function edit(Bitacora $bitacora): View
    {
        return view('bitacoras.edit', [
            'bitacora' => $bitacora,
        ]);
    }

    public function update(Request $request, Bitacora $bitacora): RedirectResponse
    {
        $data = $this->validateEditData($request, $bitacora);
        $message = 'No se actualizaron datos. La bitacora quedo sin cambios.';

        if ($data !== []) {
            $bitacora->update($data);
            $message = 'Factura e imagen de bitacora actualizadas correctamente.';
        }

        return redirect()
            ->route('bitacoras.index')
            ->with('success', $message);
    }

    public function destroy(Bitacora $bitacora): RedirectResponse
    {
        if (!empty($bitacora->imagen_factura) && Storage::disk('public')->exists($bitacora->imagen_factura)) {
            Storage::disk('public')->delete($bitacora->imagen_factura);
        }

        $bitacora->delete();

        return redirect()
            ->route('bitacoras.index')
            ->with('success', 'Bitacora eliminada correctamente.');
    }

    private function validateStoreData(Request $request): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cod_especial' => ['required', 'string', 'max:50'],
                'transportadora' => ['nullable', 'string', 'max:255'],
                'provincia' => ['nullable', 'string', 'max:255'],
                'factura' => ['nullable', 'string', 'max:255'],
                'precio_total' => ['nullable', 'numeric', 'min:0'],
                'peso' => ['nullable', 'numeric', 'min:0'],
                'imagen_factura' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            ],
            [],
            [
                'cod_especial' => 'cod especial',
                'transportadora' => 'transportadora',
                'provincia' => 'provincia',
                'factura' => 'factura',
                'precio_total' => 'precio total',
                'peso' => 'peso',
                'imagen_factura' => 'imagen de factura',
            ]
        );

        $validator->after(function ($validator) use ($request) {
            $codEspecial = strtoupper(trim((string) $request->input('cod_especial')));
            if ($codEspecial === '') {
                return;
            }

            $emsExists = PaqueteEms::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $contratoExists = Recojo::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $ordiExists = PaqueteOrdi::query()
                ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                ->exists();

            $certiExists = PaqueteCerti::query()
                ->where(function ($query) use ($codEspecial) {
                    $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                        ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codEspecial]);
                })
                ->exists();

            if (!$emsExists && !$contratoExists && !$ordiExists && !$certiExists) {
                $validator->errors()->add('cod_especial', 'No existen paquetes EMS, contratos, ordinarios o certificados con ese codigo.');
            }
        });

        $data = $validator->validate();

        $codEspecial = strtoupper(trim((string) ($data['cod_especial'] ?? '')));
        $totales = $this->obtenerTotalesPorCodEspecial($codEspecial);

        $data['cod_especial'] = $codEspecial;
        $data['user_id'] = (int) Auth::id();
        $data['transportadora'] = $this->emptyToNull($data['transportadora'] ?? null);
        $data['provincia'] = $this->normalizeUpperOrNull($data['provincia'] ?? null);
        $data['factura'] = $this->emptyToNull($data['factura'] ?? null);
        $data['precio_total'] = $data['precio_total'] ?? ($totales['precio_total'] > 0 ? $totales['precio_total'] : null);
        $data['peso'] = $data['peso'] ?? $totales['peso'];

        if ($request->hasFile('imagen_factura')) {
            $data['imagen_factura'] = $request->file('imagen_factura')->store('bitacoras/facturas', 'public');
        } else {
            $data['imagen_factura'] = null;
        }

        return $data;
    }

    private function storeByCodEspecial(array $payload): int
    {
        $codEspecial = (string) $payload['cod_especial'];
        $ems = PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $contratos = Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $ordinarios = PaqueteOrdi::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
            ->orderBy('id')
            ->get(['id']);

        $certificados = PaqueteCerti::query()
            ->where(function ($query) use ($codEspecial) {
                $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codEspecial])
                    ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codEspecial]);
            })
            ->orderBy('id')
            ->get(['id']);

        $total = 0;
        $items = collect();

        foreach ($ems as $paquete) {
            $items->push([
                'paquetes_ems_id' => (int) $paquete->id,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($contratos as $contrato) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => (int) $contrato->id,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($ordinarios as $ordinario) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => (int) $ordinario->id,
                'paquetes_certi_id' => null,
            ]);
        }

        foreach ($certificados as $certificado) {
            $items->push([
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_ordi_id' => null,
                'paquetes_certi_id' => (int) $certificado->id,
            ]);
        }

        DB::transaction(function () use ($payload, $items, &$total) {
            $lastIndex = max(0, $items->count() - 1);

            foreach ($items->values() as $index => $item) {
                $attributes = [
                    'cod_especial' => $payload['cod_especial'],
                    'paquetes_ems_id' => $item['paquetes_ems_id'],
                    'paquetes_contrato_id' => $item['paquetes_contrato_id'],
                    'paquetes_ordi_id' => $item['paquetes_ordi_id'],
                    'paquetes_certi_id' => $item['paquetes_certi_id'],
                ];
                $values = [
                    'user_id' => $payload['user_id'],
                    'transportadora' => $payload['transportadora'],
                    'provincia' => $payload['provincia'],
                    'factura' => $payload['factura'],
                    'precio_total' => $index === $lastIndex ? $payload['precio_total'] : null,
                    'peso' => $index === $lastIndex ? $payload['peso'] : null,
                ];

                if (!empty($payload['imagen_factura'])) {
                    $values['imagen_factura'] = $payload['imagen_factura'];
                }

                Bitacora::query()->updateOrCreate($attributes, $values);
                $total++;
            }
        });

        return $total;
    }

    private function validateEditData(Request $request, Bitacora $bitacora): array
    {
        $data = $request->validate(
            [
                'factura' => ['nullable', 'string', 'max:255'],
                'imagen_factura' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            ],
            [],
            [
                'factura' => 'factura',
                'imagen_factura' => 'imagen de factura',
            ]
        );

        $updates = [];

        $factura = $this->emptyToNull($data['factura'] ?? null);
        if ($factura !== $this->emptyToNull($bitacora->factura)) {
            $updates['factura'] = $factura;
        }

        if ($request->hasFile('imagen_factura')) {
            if (!empty($bitacora->imagen_factura) && Storage::disk('public')->exists($bitacora->imagen_factura)) {
                Storage::disk('public')->delete($bitacora->imagen_factura);
            }

            $updates['imagen_factura'] = $request->file('imagen_factura')->store('bitacoras/facturas', 'public');
        }

        return $updates;
    }

    private function obtenerTotalesPorCodEspecial(string $codEspecial): array
    {
        $codigo = strtoupper(trim($codEspecial));

        $pesoEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoContrato = (float) Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoOrdi = (float) PaqueteOrdi::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('peso');

        $pesoCerti = (float) PaqueteCerti::query()
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
                    ->orWhereRaw('trim(upper(COALESCE(codigo, \'\'))) = ?', [$codigo]);
            })
            ->sum('peso');

        $precioEms = (float) PaqueteEms::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('precio');

        $precioContrato = (float) Recojo::query()
            ->whereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = ?', [$codigo])
            ->sum('precio');

        return [
            'peso' => round($pesoEms + $pesoContrato + $pesoOrdi + $pesoCerti, 3),
            'precio_total' => round($precioEms + $precioContrato, 2),
        ];
    }

    private function emptyToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeUpperOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : strtoupper($text);
    }
}
