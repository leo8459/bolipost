<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Estado as EstadoModel;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo as RecojoModel;
use App\Models\Saca as SacaModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoExpedicion extends Component
{
    use WithPagination;
    private const EVENTO_ID_DESPACHO_REABIERTO_SALIDA = 224;
    private const EVENTO_ID_DESPACHO_ACTUALIZADO_ENTRADA = 230;
    private const ROUTE_PERMISSION = 'despachos.expedicion';
    protected array $estadoIdCache = [];

    public $ciudadToOficina = [
        'LA PAZ' => 'BOLPZ',
        'TARIJA' => 'BOTJA',
        'POTOSI' => 'BOPOI',
        'PANDO' => 'BOCIJ',
        'COCHABAMBA' => 'BOCBB',
        'ORURO' => 'BOORU',
        'BENI' => 'BOTDD',
        'SUCRE' => 'BOSRE',
        'SANTA CRUZ' => 'BOSRZ',
        'PERU/LIMA' => 'PELIM',
    ];

    public $search = '';
    public $searchQuery = '';
    public $intervencionDespachoId = null;
    public $intervencionSacaId = '';
    public $intervencionCodigoPaquete = '';
    public $intervencionCodEspecial = '';
    public $intervencionPesoDetectado = null;
    public $intervencionFuentePaquete = '';
    public $intervencionSacas = [];

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function volverApertura($id)
    {
        $this->authorizePermission($this->featurePermission('restore'));
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
        $estadoClausuraId = $this->getEstadoIdByNombre('CLAUSURA', 14);

        $despacho = DespachoModel::query()
            ->where('fk_estado', $estadoExpedicionId)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => $estadoClausuraId]);
        $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_REABIERTO_SALIDA);
        session()->flash('success', 'Despacho devuelto a apertura.');
    }

    public function intervenirDespacho($id)
    {
        $this->authorizePermission($this->featurePermission('confirm'));
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
        $estadoIntervenirId = $this->getEstadoIdByNombre('INTERVENIR', 20);

        $despacho = DespachoModel::query()
            ->where('fk_estado', $estadoExpedicionId)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => $estadoIntervenirId]);
        $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_ACTUALIZADO_ENTRADA);
        session()->flash('success', 'Despacho enviado a intervencion.');
    }

    public function openIntervencionModal($id)
    {
        $this->authorizePermission($this->featurePermission('edit'));
        $estadoIntervenirId = $this->getEstadoIdByNombre('INTERVENIR', 20);
        $despacho = DespachoModel::query()
            ->where('fk_estado', $estadoIntervenirId)
            ->findOrFail($id);

        $sacas = SacaModel::query()
            ->where('fk_despacho', $despacho->id)
            ->orderByRaw('CAST(nro_saca AS INTEGER) ASC')
            ->get(['id', 'nro_saca', 'identificador', 'busqueda', 'peso']);

        if ($sacas->isEmpty()) {
            session()->flash('error', 'El despacho no tiene sacas para intervenir.');
            return;
        }

        $this->resetIntervencionForm();
        $this->intervencionDespachoId = (int) $despacho->id;
        $this->intervencionSacas = $sacas->map(function ($saca) {
            return [
                'id' => (int) $saca->id,
                'label' => 'Saca ' . $saca->nro_saca . ' - ' . $saca->identificador,
                'cod_especial' => trim((string) $saca->busqueda),
                'peso' => round((float) ($saca->peso ?? 0), 3),
            ];
        })->values()->all();

        $this->intervencionSacaId = (string) $this->intervencionSacas[0]['id'];
        $this->syncIntervencionCodEspecial();
        $this->dispatch('openIntervencionModal');
    }

    public function updatedIntervencionSacaId($value)
    {
        $this->syncIntervencionCodEspecial();
        $this->syncIntervencionPesoDetectado();
    }

    public function updatedIntervencionCodigoPaquete($value)
    {
        $this->syncIntervencionPesoDetectado();
    }

    public function registrarIntervencion()
    {
        $this->authorizePermission($this->featurePermission('edit'));
        $validated = $this->validate([
            'intervencionDespachoId' => 'required|integer|exists:despacho,id',
            'intervencionSacaId' => 'required|integer|exists:saca,id',
            'intervencionCodigoPaquete' => 'required|string|max:255',
        ], [
            'intervencionSacaId.required' => 'Selecciona una saca intervenida.',
            'intervencionCodigoPaquete.required' => 'Ingresa el codigo del paquete intervenido.',
        ]);

        $despachoId = (int) $validated['intervencionDespachoId'];
        $sacaId = (int) $validated['intervencionSacaId'];
        $codigoPaquete = strtoupper(trim((string) $validated['intervencionCodigoPaquete']));
        $estadoIntervenirId = $this->getEstadoIdByNombre('INTERVENIR', 20);
        $userOforigen = $this->getOforigenFromUser();

        DB::transaction(function () use ($despachoId, $sacaId, $codigoPaquete, $estadoIntervenirId, $userOforigen) {
            $despacho = DespachoModel::query()
                ->lockForUpdate()
                ->whereKey($despachoId)
                ->where('fk_estado', $estadoIntervenirId)
                ->first();

            if (!$despacho) {
                throw ValidationException::withMessages([
                    'intervencionDespachoId' => 'El despacho ya no se encuentra en estado INTERVENIR.',
                ]);
            }

            if ($userOforigen !== '' && strtoupper(trim((string) $despacho->oforigen)) !== $userOforigen) {
                throw ValidationException::withMessages([
                    'intervencionDespachoId' => 'No puedes intervenir un despacho de otra oficina.',
                ]);
            }

            $saca = SacaModel::query()
                ->lockForUpdate()
                ->whereKey($sacaId)
                ->where('fk_despacho', $despacho->id)
                ->first();

            if (!$saca) {
                throw ValidationException::withMessages([
                    'intervencionSacaId' => 'La saca seleccionada no pertenece al despacho.',
                ]);
            }

            $codEspecial = strtoupper(trim((string) $saca->busqueda));
            if ($codEspecial === '') {
                throw ValidationException::withMessages([
                    'intervencionSacaId' => 'La saca no tiene cod_especial (campo busqueda) para validar el paquete.',
                ]);
            }

            $paqueteIntervenido = $this->resolverPaqueteIntervenido($codigoPaquete, $codEspecial);
            if ($paqueteIntervenido === null) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'El codigo ingresado no pertenece al cod_especial de la saca.',
                ]);
            }
            $pesoIntervenido = (float) $paqueteIntervenido['peso'];
            if ($pesoIntervenido <= 0) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'El paquete encontrado no tiene un peso valido para descontar.',
                ]);
            }

            $pesoSacaActual = round((float) ($saca->peso ?? 0), 3);
            $pesoDespachoActual = round((float) ($despacho->peso ?? 0), 3);
            $paquetesSacaActual = (int) ($saca->paquetes ?? 0);
            $nroEnvaseActual = $this->parseCounterValue($despacho->nro_envase);

            if ($pesoIntervenido > $pesoSacaActual) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'El peso del paquete supera el peso actual de la saca.',
                ]);
            }

            if ($pesoIntervenido > $pesoDespachoActual) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'El peso del paquete supera el peso actual del despacho.',
                ]);
            }

            if ($paquetesSacaActual <= 0) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'La saca no tiene paquetes disponibles para descontar.',
                ]);
            }

            if ($nroEnvaseActual <= 0) {
                throw ValidationException::withMessages([
                    'intervencionCodigoPaquete' => 'El despacho no tiene nro_envase disponible para descontar.',
                ]);
            }

            $this->actualizarEstadoPaqueteIntervenido($paqueteIntervenido, $estadoIntervenirId);

            $nuevoPesoSaca = round($pesoSacaActual - $pesoIntervenido, 3);
            $saca->update([
                'peso' => $nuevoPesoSaca,
                'paquetes' => $paquetesSacaActual - 1,
                'receptaculo' => $this->buildReceptaculoForValues((string) $saca->identificador, $nuevoPesoSaca),
            ]);

            $despacho->update([
                'peso' => round($pesoDespachoActual - $pesoIntervenido, 3),
                'nro_envase' => (string) ($nroEnvaseActual - 1),
                'fk_estado' => $this->getEstadoIdByNombre('EXPEDICION', 19),
            ]);
        });

        $this->dispatch('reimprimirCnDespacho', route('despachos.expedicion.pdf', ['id' => $despachoId], false));
        $this->dispatch('closeIntervencionModal');
        $this->resetIntervencionForm();
        session()->flash('success', 'Intervencion registrada: peso del paquete descontado automaticamente en saca y despacho.');
    }

    public function resetIntervencionForm()
    {
        $this->reset([
            'intervencionDespachoId',
            'intervencionSacaId',
            'intervencionCodigoPaquete',
            'intervencionCodEspecial',
            'intervencionPesoDetectado',
            'intervencionFuentePaquete',
            'intervencionSacas',
        ]);
        $this->resetValidation();
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $userOforigen = $this->getOforigenFromUser();

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->whereHas('estado', function ($query) {
                $query->whereIn('nombre_estado', ['EXPEDICION', 'INTERVENIR']);
            })
            ->when($userOforigen !== '', function ($query) use ($userOforigen) {
                $query->where('oforigen', $userOforigen);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('oforigen', 'ILIKE', "%{$q}%")
                        ->orWhere('ofdestino', 'ILIKE', "%{$q}%")
                        ->orWhere('categoria', 'ILIKE', "%{$q}%")
                        ->orWhere('subclase', 'ILIKE', "%{$q}%")
                        ->orWhere('nro_despacho', 'ILIKE', "%{$q}%")
                        ->orWhere('identificador', 'ILIKE', "%{$q}%")
                        ->orWhere('anio', 'ILIKE', "%{$q}%")
                        ->orWhere('departamento', 'ILIKE', "%{$q}%")
                        ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.despacho-expedicion', [
            'despachos' => $despachos,
            'canDespachoExpPrint' => $this->userCan($this->featurePermission('print')),
            'canDespachoExpConfirm' => $this->userCan($this->featurePermission('confirm')),
            'canDespachoExpRestore' => $this->userCan($this->featurePermission('restore')),
            'canDespachoExpEdit' => $this->userCan($this->featurePermission('edit')),
        ]);
    }

    private function featurePermission(string $action): string
    {
        return 'feature.'.self::ROUTE_PERMISSION.'.'.$action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user ? $user->can($permission) : false;
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }

    protected function getOforigenFromUser()
    {
        $user = Auth::user();
        if (!$user || !$user->ciudad) {
            return '';
        }

        $ciudad = strtoupper(trim($user->ciudad));

        return $this->ciudadToOficina[$ciudad] ?? '';
    }

    protected function getEstadoIdByNombre(string $nombre, int $fallback): int
    {
        if (array_key_exists($nombre, $this->estadoIdCache)) {
            return $this->estadoIdCache[$nombre];
        }

        $estadoId = (int) (EstadoModel::query()
            ->where('nombre_estado', $nombre)
            ->value('id') ?? 0);

        if ($estadoId <= 0) {
            $estadoId = $fallback;
        }

        $this->estadoIdCache[$nombre] = $estadoId;

        return $estadoId;
    }

    protected function registrarEventoDespacho(string $codigo, int $eventoId): void
    {
        $codigo = trim($codigo);
        $userId = (int) optional(Auth::user())->id;

        if ($codigo === '' || $eventoId <= 0 || $userId <= 0) {
            return;
        }

        DB::table('eventos_despacho')->insert([
            'codigo' => $codigo,
            'evento_id' => $eventoId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function syncIntervencionCodEspecial(): void
    {
        $saca = collect($this->intervencionSacas)->firstWhere('id', (int) $this->intervencionSacaId);
        $this->intervencionCodEspecial = trim((string) ($saca['cod_especial'] ?? ''));
    }

    protected function syncIntervencionPesoDetectado(): void
    {
        $codigoPaquete = strtoupper(trim((string) $this->intervencionCodigoPaquete));
        $codEspecial = strtoupper(trim((string) $this->intervencionCodEspecial));

        if ($codigoPaquete === '' || $codEspecial === '') {
            $this->intervencionPesoDetectado = null;
            $this->intervencionFuentePaquete = '';
            return;
        }

        $paquete = $this->resolverPaqueteIntervenido($codigoPaquete, $codEspecial);
        if ($paquete === null) {
            $this->intervencionPesoDetectado = null;
            $this->intervencionFuentePaquete = '';
            return;
        }

        $this->intervencionPesoDetectado = round((float) $paquete['peso'], 3);
        $this->intervencionFuentePaquete = (string) $paquete['fuente'];
    }

    protected function resolverPaqueteIntervenido(string $codigoPaquete, string $codEspecial): ?array
    {
        if ($codigoPaquete === '' || $codEspecial === '') {
            return null;
        }

        $hits = [];

        $ems = PaqueteEms::query()
            ->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigoPaquete])
            ->whereRaw('TRIM(UPPER(cod_especial)) = ?', [$codEspecial])
            ->first(['id', 'peso']);
        if ($ems) {
            $hits[] = [
                'fuente' => 'EMS',
                'tabla' => 'ems',
                'id' => (int) $ems->id,
                'peso' => (float) ($ems->peso ?? 0),
            ];
        }

        $ordi = PaqueteOrdi::query()
            ->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigoPaquete])
            ->whereRaw('TRIM(UPPER(cod_especial)) = ?', [$codEspecial])
            ->first(['id', 'peso']);
        if ($ordi) {
            $hits[] = [
                'fuente' => 'ORDI',
                'tabla' => 'ordi',
                'id' => (int) $ordi->id,
                'peso' => (float) ($ordi->peso ?? 0),
            ];
        }

        $contrato = RecojoModel::query()
            ->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigoPaquete])
            ->whereRaw('TRIM(UPPER(cod_especial)) = ?', [$codEspecial])
            ->first(['id', 'peso']);
        if ($contrato) {
            $hits[] = [
                'fuente' => 'CONTRATO',
                'tabla' => 'contrato',
                'id' => (int) $contrato->id,
                'peso' => (float) ($contrato->peso ?? 0),
            ];
        }

        if (count($hits) !== 1) {
            return null;
        }

        return $hits[0];
    }

    protected function actualizarEstadoPaqueteIntervenido(array $paqueteIntervenido, int $estadoIntervenirId): void
    {
        $tabla = (string) ($paqueteIntervenido['tabla'] ?? '');
        $id = (int) ($paqueteIntervenido['id'] ?? 0);

        if ($estadoIntervenirId <= 0 || $id <= 0) {
            return;
        }

        if ($tabla === 'ems') {
            PaqueteEms::query()->whereKey($id)->update(['estado_id' => $estadoIntervenirId]);
            return;
        }

        if ($tabla === 'ordi') {
            PaqueteOrdi::query()->whereKey($id)->update(['fk_estado' => $estadoIntervenirId]);
            return;
        }

        if ($tabla === 'contrato') {
            RecojoModel::query()->whereKey($id)->update(['estados_id' => $estadoIntervenirId]);
        }
    }

    protected function buildReceptaculoForValues(string $identificador, $peso): string
    {
        $pesoFormateado = $this->formatPesoForReceptaculo($peso);
        $base = $identificador . $pesoFormateado;

        return preg_replace('/[^A-Za-z0-9]/', '', $base);
    }

    protected function formatPesoForReceptaculo($peso): string
    {
        if ($peso === null) {
            return '';
        }

        $raw = str_replace(',', '.', (string) $peso);
        $parts = explode('.', $raw, 2);

        $entero = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
        $decimal = preg_replace('/[^0-9]/', '', $parts[1] ?? '');
        $primerDecimal = $decimal !== '' ? substr($decimal, 0, 1) : '';

        $digits = $entero . $primerDecimal;
        if ($digits === '') {
            return '';
        }

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    protected function parseCounterValue($value): int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 0;
        }

        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        return $digits === '' ? 0 : (int) $digits;
    }
}
