<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Estado as EstadoModel;
use App\Models\Saca as SacaModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Despacho extends Component
{
    use WithPagination;
    private const EVENTO_ID_DESPACHO_CREADO_SALIDA = 222;
    private const EVENTO_ID_DESPACHO_CERRADO_SALIDA = 223;
    private const EVENTO_ID_DESPACHO_REABIERTO_SALIDA = 224;
    private const EVENTO_ID_DESPACHO_ENVIADO_EXTRANJERO = 263;
    private const EVENTO_ID_DESPACHO_MARCADO_ELIMINADO = 228;
    private const EVENTO_ID_DESPACHO_ACTUALIZADO_SALIDA = 229;
    private const ROUTE_PERMISSION = 'despachos.abiertos';
    private const SACAS_ROUTE_PERMISSION = 'sacas.index';
    protected array $estadoIdCache = [];

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $oforigen = '';
    public $ofdestino = '';
    public $categoria = '';
    public $subclase = '';
    public $nro_despacho = '';
    public $nro_envase = '';
    public $peso = '';
    public $identificador = '';
    public $anio = '';
    public $departamento = '';
    public $fk_estado = '';

    public $oficinas = [
        'BOLPZ' => 'BOLPZ - LA PAZ',
        'BOTJA' => 'BOTJA - TARIJA',
        'BOPOI' => 'BOPOI - POTOSI',
        'BOCIJ' => 'BOCIJ - COBIJA',
        'BOCBB' => 'BOCBB - COCHABAMBA',
        'BOORU' => 'BOORU - ORURO',
        'BOTDD' => 'BOTDD - TRINIDAD',
        'BOSRE' => 'BOSRE - SUCRE',
        'BOSRZ' => 'BOSRZ - SANTA CRUZ',
        'PELIM' => 'PELIM - PERU/LIMA',
    ];

    public $ciudadToOficina = [
        'LA PAZ' => 'BOLPZ',
        'TARIJA' => 'BOTJA',
        'POTOSI' => 'BOPOI',
        'COBIJA' => 'BOCIJ',
        'COCHABAMBA' => 'BOCBB',
        'ORURO' => 'BOORU',
        'TRINIDAD' => 'BOTDD',
        'SUCRE' => 'BOSRE',
        'SANTA CRUZ' => 'BOSRZ',
        'PERU/LIMA' => 'PELIM',
    ];

    public $categorias = [
        'A' => 'A - Aéreo',
        'B' => 'B - S.A.L.',
        'C' => 'C - Superficie',
        'D' => 'D - Prioritario por superficie',
    ];

    public $subclases = [
        'EN' => 'EMS',
        'UN' => 'LC/AO',
        'UR' => 'CERTIFICADOS',
        'CN' => 'ENCOMIENDAS',
        'UM' => 'SACAS M',
        'UA' => 'CARTAS',
        'MN' => 'MIXTO',
    ];

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->fk_estado = $this->getEstadoAperturaId();
        $this->anio = $this->getCurrentYear();
        $this->oforigen = $this->getOforigenFromUser();
        $this->departamento = $this->getDepartamentoFromUser();
    }

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->authorizePermission($this->featurePermission('create'));
        $this->resetForm();
        $this->fk_estado = $this->getEstadoAperturaId();
        $this->anio = $this->getCurrentYear();
        $this->oforigen = $this->getOforigenFromUser();
        $this->departamento = $this->getDepartamentoFromUser();
        $this->editingId = null;
        $this->dispatch('openDespachoModal');
    }

    public function openEditModal($id)
    {
        $this->authorizePermission($this->featurePermission('edit'));
        $despacho = DespachoModel::findOrFail($id);
        $this->editingId = $despacho->id;
        $this->oforigen = $despacho->oforigen;
        $this->ofdestino = $despacho->ofdestino;
        $this->categoria = $despacho->categoria;
        $this->subclase = $despacho->subclase;
        $this->nro_despacho = $despacho->nro_despacho;
        $this->nro_envase = $despacho->nro_envase;
        $this->peso = $despacho->peso;
        $this->identificador = $despacho->identificador;
        $this->anio = $despacho->anio;
        $this->departamento = $despacho->departamento;
        $this->fk_estado = $despacho->fk_estado;

        $this->dispatch('openDespachoModal');
    }

    public function save()
    {
        $this->authorizePermission($this->featurePermission($this->editingId ? 'edit' : 'create'));

        if (!$this->editingId) {
            $this->anio = $this->getCurrentYear();
            $this->nro_despacho = $this->getNextNroDespachoForYear($this->anio);
        }

        $this->identificador = $this->buildIdentificador();

        $this->validate($this->rules());

        if ($this->editingId) {
            $despacho = DespachoModel::findOrFail($this->editingId);
            $despacho->update($this->payload());
            $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_ACTUALIZADO_SALIDA);
            session()->flash('success', 'Despacho actualizado correctamente.');
        } else {
            $this->fk_estado = $this->getEstadoAperturaId();
            $this->anio = $this->getCurrentYear();
            $this->oforigen = $this->getOforigenFromUser() ?: $this->oforigen;
            $this->departamento = $this->getDepartamentoFromUser() ?: $this->departamento;
            $despacho = DespachoModel::create($this->payload());
            $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_CREADO_SALIDA);
            session()->flash('success', 'Despacho creado correctamente.');
        }

        $this->dispatch('closeDespachoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $this->authorizePermission($this->featurePermission('delete'));
        $despacho = DespachoModel::findOrFail($id);
        $identificador = (string) $despacho->identificador;
        $despacho->delete();
        $this->registrarEventoDespacho($identificador, self::EVENTO_ID_DESPACHO_MARCADO_ELIMINADO);
        session()->flash('success', 'Despacho eliminado correctamente.');
    }

    public function reaperturaSaca($id)
    {
        $this->authorizePermission($this->featurePermission('restore'));
        $estadoAperturaId = $this->getEstadoIdByNombre('APERTURA', 11);
        $estadoSacaAperturaId = $this->getEstadoIdByNombre('ASIGNADO', 16);

        DB::transaction(function () use ($id, $estadoAperturaId, $estadoSacaAperturaId) {
            $despacho = DespachoModel::query()->findOrFail($id);

            $despacho->update(['fk_estado' => $estadoAperturaId]);

            SacaModel::query()
                ->where('fk_despacho', $despacho->id)
                ->update(['fk_estado' => $estadoSacaAperturaId]);
        });
        $despacho = DespachoModel::query()->findOrFail($id);
        $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_REABIERTO_SALIDA);

        session()->flash('success', 'Reapertura realizada correctamente.');

        return redirect()->route('sacas.index', ['despacho_id' => $id]);
    }

    public function expedicion($id)
    {
        $this->authorizePermission($this->featurePermission('confirm'));
        $estadoClausuraId = $this->getEstadoIdByNombre('CLAUSURA', 14);
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);

        $despacho = DespachoModel::query()->findOrFail($id);
        if ((int) $despacho->fk_estado !== (int) $estadoClausuraId) {
            session()->flash('error', 'Solo despachos en CLAUSURA pueden pasar a EXPEDICION.');
            return;
        }
        $despacho->update(['fk_estado' => $estadoExpedicionId]);
        $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_ENVIADO_EXTRANJERO);

        $this->dispatch('printDespachoExpedicion', [
            'url' => route('despachos.expedicion.pdf', ['id' => $despacho->id], false),
        ]);

        session()->flash('success', 'Despacho enviado a expedicion.');
    }

    public function registrarCierreDespachoEvento(int $despachoId): void
    {
        $despacho = DespachoModel::query()->find($despachoId);
        if (!$despacho) {
            return;
        }

        $this->registrarEventoDespacho((string) $despacho->identificador, self::EVENTO_ID_DESPACHO_CERRADO_SALIDA);
    }

    public function resetForm()
    {
        $this->reset([
            'oforigen',
            'ofdestino',
            'categoria',
            'subclase',
            'nro_despacho',
            'nro_envase',
            'peso',
            'identificador',
            'anio',
            'departamento',
            'fk_estado',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'oforigen' => 'required|string|max:255|in:' . implode(',', array_keys($this->oficinas)),
            'ofdestino' => 'required|string|max:255|in:' . implode(',', array_keys($this->oficinas)),
            'categoria' => 'required|string|max:255|in:' . implode(',', array_keys($this->categorias)),
            'subclase' => 'required|string|max:255|in:' . implode(',', array_keys($this->subclases)),
            'nro_despacho' => 'required|string|max:255',
            'nro_envase' => 'nullable|string|max:255',
            'peso' => 'nullable|numeric|min:0.001',
            'identificador' => 'required|string|max:255',
            'anio' => 'required|integer|min:1900|max:2100',
            'departamento' => 'required|string|max:255',
            'fk_estado' => 'required|integer|exists:estados,id',
        ];
    }

    protected function payload()
    {
        return [
            'oforigen' => $this->oforigen,
            'ofdestino' => $this->ofdestino,
            'categoria' => $this->categoria,
            'subclase' => $this->subclase,
            'nro_despacho' => $this->nro_despacho,
            'nro_envase' => $this->normalizeNullable($this->nro_envase),
            'peso' => $this->normalizeNullable($this->peso),
            'identificador' => $this->normalizeNullable($this->identificador),
            'anio' => $this->anio,
            'departamento' => $this->departamento,
            'fk_estado' => $this->fk_estado,
        ];
    }

    protected function getEstadoAperturaId()
    {
        return $this->getEstadoIdByNombre('APERTURA', 11);
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

    protected function getCurrentYear()
    {
        return now()->year;
    }

    protected function buildIdentificador()
    {
        $anioLastDigit = substr((string) $this->anio, -1);

        return (string) (
            $this->oforigen .
            $this->ofdestino .
            $this->categoria .
            $this->subclase .
            $anioLastDigit .
            $this->nro_despacho
        );
    }

    protected function normalizeNullable($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = is_string($value) ? trim($value) : $value;

        return $trimmed === '' ? null : $trimmed;
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

    protected function getNextNroDespachoForYear($year)
    {
        $max = DespachoModel::query()
            ->where('anio', $year)
            ->max(DB::raw('CAST(nro_despacho AS INTEGER)'));

        $next = (int) $max + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
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

    protected function getDepartamentoFromUser()
    {
        $user = Auth::user();
        if (!$user || !$user->ciudad) {
            return '';
        }

        return strtoupper(trim($user->ciudad));
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $userOforigen = $this->getOforigenFromUser();

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->whereHas('estado', function ($query) {
                $query->whereIn('nombre_estado', ['APERTURA', 'CLAUSURA']);
            })
            ->when($userOforigen !== '', function ($query) use ($userOforigen) {
                $query->where('oforigen', $userOforigen);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where('oforigen', 'ILIKE', "%{$q}%")
                    ->orWhere('ofdestino', 'ILIKE', "%{$q}%")
                    ->orWhere('categoria', 'ILIKE', "%{$q}%")
                    ->orWhere('subclase', 'ILIKE', "%{$q}%")
                    ->orWhere('nro_despacho', 'ILIKE', "%{$q}%")
                    ->orWhere('nro_envase', 'ILIKE', "%{$q}%")
                    ->orWhere('peso', 'ILIKE', "%{$q}%")
                    ->orWhere('identificador', 'ILIKE', "%{$q}%")
                    ->orWhere('anio', 'ILIKE', "%{$q}%")
                    ->orWhere('departamento', 'ILIKE', "%{$q}%")
                    ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                        $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                    });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.despacho', [
            'despachos' => $despachos,
            'canDespachoCreate' => $this->userCan($this->featurePermission('create')),
            'canDespachoEdit' => $this->userCan($this->featurePermission('edit')),
            'canDespachoDelete' => $this->userCan($this->featurePermission('delete')),
            'canDespachoAssign' => $this->userCan($this->featurePermission('assign')),
            'canDespachoConfirm' => $this->userCan($this->featurePermission('confirm')),
            'canDespachoRestore' => $this->userCan($this->featurePermission('restore')),
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
}
