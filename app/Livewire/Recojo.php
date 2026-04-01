<?php

namespace App\Livewire;

use App\Models\Recojo as RecojoModel;
use App\Models\Estado as EstadoModel;
use App\Models\CodigoEmpresa as CodigoEmpresaModel;
use App\Models\Empresa as EmpresaModel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Recojo extends Component
{
    use WithPagination;

    private const MODE_ROUTE_PERMISSIONS = [
        'general' => 'paquetes-contrato.index',
        'almacen' => 'paquetes-contrato.almacen',
    ];

    public $mode = 'general';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $userCity = '';
    public $estadoAlmacenId = null;

    public $user_id = '';
    public $codigo = '';
    public $cod_especial = '';
    public $estados_id = null;
    public $origen = '';
    public $destino = '';
    public $nombre_r = '';
    public $telefono_r = '';
    public $contenido = '';
    public $cantidad = '';
    public $direccion_r = '';
    public $nombre_d = '';
    public $telefono_d = '';
    public $direccion_d = '';
    public $mapa = '';
    public $provincia = '';
    public $peso = '';
    public $fecha_recojo = '';
    public $observacion = '';
    public $justificacion = '';
    public $imagen = '';

    protected $paginationTheme = 'bootstrap';

    public function mount($mode = 'general')
    {
        $this->mode = in_array($mode, ['general', 'almacen'], true) ? $mode : 'general';
        $this->fecha_recojo = '';
        $this->user_id = (string) optional(Auth::user())->id;
        $this->userCity = strtoupper(trim((string) optional(Auth::user())->ciudad));
        $this->estadoAlmacenId = (int) (EstadoModel::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id') ?? 0);
    }

    public function getIsAlmacenModeProperty(): bool
    {
        return $this->mode === 'almacen';
    }

    public function searchRecojos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openRecojoModal');
    }

    public function openEditModal($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $recojo = RecojoModel::findOrFail((int) $id);

        $this->editingId = $recojo->id;
        $this->user_id = (string) $recojo->user_id;
        $this->codigo = (string) $recojo->codigo;
        $this->cod_especial = (string) ($recojo->cod_especial ?? '');
        $this->estados_id = $recojo->estados_id ? (int) $recojo->estados_id : null;
        $this->origen = (string) $recojo->origen;
        $this->destino = (string) $recojo->destino;
        $this->nombre_r = (string) $recojo->nombre_r;
        $this->telefono_r = (string) $recojo->telefono_r;
        $this->contenido = (string) $recojo->contenido;
        $this->cantidad = (string) ($recojo->cantidad ?? '');
        $this->direccion_r = (string) $recojo->direccion_r;
        $this->nombre_d = (string) $recojo->nombre_d;
        $this->telefono_d = (string) $recojo->telefono_d;
        $this->direccion_d = (string) $recojo->direccion_d;
        $this->mapa = (string) ($recojo->mapa ?? '');
        $this->provincia = (string) $recojo->provincia;
        $this->peso = (string) $recojo->peso;
        $this->fecha_recojo = optional($recojo->fecha_recojo)->format('Y-m-d\TH:i:s');
        $this->observacion = (string) ($recojo->observacion ?? '');
        $this->justificacion = (string) ($recojo->justificacion ?? '');
        $this->imagen = (string) ($recojo->imagen ?? '');

        $this->dispatch('openRecojoModal');
    }

    public function save()
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $this->validate($this->rules());

        if ($this->editingId) {
            $recojo = RecojoModel::findOrFail((int) $this->editingId);
            $recojo->update($this->payload());
            session()->flash('success', 'Contrato actualizado correctamente.');
        } else {
            RecojoModel::create($this->payload());
            session()->flash('success', 'Contrato creado correctamente.');
        }

        $this->dispatch('closeRecojoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('delete'));

        $recojo = RecojoModel::findOrFail((int) $id);
        $recojo->delete();
        session()->flash('success', 'Contrato eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'cod_especial',
            'estados_id',
            'origen',
            'destino',
            'nombre_r',
            'telefono_r',
            'contenido',
            'cantidad',
            'direccion_r',
            'nombre_d',
            'telefono_d',
            'direccion_d',
            'mapa',
            'provincia',
            'peso',
            'fecha_recojo',
            'observacion',
            'justificacion',
            'imagen',
        ]);

        $this->user_id = (string) optional(Auth::user())->id;
        $this->fecha_recojo = '';
        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'codigo' => ['required', 'string', 'max:255', Rule::unique('paquetes_contrato', 'codigo')->ignore($this->editingId)],
            'cod_especial' => 'nullable|string|max:50',
            'estados_id' => ['nullable', 'integer', Rule::exists('estados', 'id')],
            'origen' => 'required|string|max:255',
            'destino' => 'required|string|max:255',
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:50',
            'contenido' => 'required|string',
            'cantidad' => 'nullable|string|max:255',
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'nullable|string|max:50',
            'direccion_d' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
            'provincia' => 'nullable|string|max:255',
            'peso' => 'required|numeric|min:0',
            'fecha_recojo' => 'nullable|date',
            'observacion' => 'nullable|string',
            'justificacion' => 'nullable|string',
            'imagen' => 'nullable|string|max:500',
        ];
    }

    protected function payload()
    {
        $estadoId = $this->estados_id ?: $this->resolveSolicitudEstadoId();

        return [
            'user_id' => (int) $this->user_id,
            'empresa_id' => $this->resolveEmpresaIdByCodigo($this->codigo),
            'codigo' => $this->normalizeUpper($this->codigo),
            'cod_especial' => $this->nullIfEmpty($this->normalizeUpper($this->cod_especial)),
            'estados_id' => $estadoId,
            'origen' => $this->normalizeUpper($this->origen),
            'destino' => $this->normalizeUpper($this->destino),
            'nombre_r' => $this->normalizeUpper($this->nombre_r),
            'telefono_r' => $this->normalize($this->telefono_r),
            'contenido' => $this->normalize($this->contenido),
            'cantidad' => $this->nullIfEmpty($this->cantidad),
            'direccion_r' => $this->normalizeUpper($this->direccion_r),
            'nombre_d' => $this->normalizeUpper($this->nombre_d),
            'telefono_d' => $this->nullIfEmpty($this->telefono_d),
            'direccion_d' => $this->normalizeUpper($this->direccion_d),
            'mapa' => $this->nullIfEmpty($this->mapa),
            'provincia' => !is_null($this->nullIfEmpty($this->provincia))
                ? strtoupper($this->nullIfEmpty($this->provincia))
                : null,
            'peso' => $this->peso,
            'fecha_recojo' => $this->normalizeFechaRecojo($this->fecha_recojo),
            'observacion' => $this->nullIfEmpty($this->observacion),
            'justificacion' => $this->nullIfEmpty($this->justificacion),
            'imagen' => $this->nullIfEmpty($this->imagen),
        ];
    }

    protected function normalize($value): string
    {
        return trim((string) $value);
    }

    protected function normalizeUpper($value): string
    {
        return strtoupper($this->normalize($value));
    }

    protected function nullIfEmpty($value): ?string
    {
        $clean = $this->normalize($value);
        return $clean === '' ? null : $clean;
    }

    protected function normalizeFechaRecojo($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return Carbon::parse($raw)->format('Y-m-d H:i:s');
    }

    protected function resolveSolicitudEstadoId(): ?int
    {
        $id = EstadoModel::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id');

        return !empty($id) ? (int) $id : null;
    }

    protected function resolveEmpresaIdByCodigo($codigo): ?int
    {
        $codigoNormalizado = strtoupper(trim((string) $codigo));
        if ($codigoNormalizado === '') {
            return null;
        }

        $empresaIdPorCodigo = CodigoEmpresaModel::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigoNormalizado])
            ->value('empresa_id');

        if (!empty($empresaIdPorCodigo)) {
            return (int) $empresaIdPorCodigo;
        }

        if (preg_match('/^C([A-Z0-9]+)A\d{5}BO$/', $codigoNormalizado, $matches)) {
            $codigoCliente = strtoupper(trim((string) ($matches[1] ?? '')));
            if ($codigoCliente !== '') {
                $empresaIdPorCliente = EmpresaModel::query()
                    ->whereRaw('trim(upper(codigo_cliente)) = ?', [$codigoCliente])
                    ->value('id');

                if (!empty($empresaIdPorCliente)) {
                    return (int) $empresaIdPorCliente;
                }
            }
        }

        return null;
    }

   public function render()
{
    $q = trim((string) $this->searchQuery);

    $authUser = Auth::user();
    $authUserId = (int) ($authUser?->id ?? 0);
    $authEmpresaId = (int) ($authUser?->empresa_id ?? 0);

    $recojos = RecojoModel::query()
        ->with([
            'empresa:id,nombre,sigla',
            'user:id,name,empresa_id',
            'user.empresa:id,nombre,sigla',
            'estadoRegistro:id,nombre_estado',
        ])

        // ✅ FILTRO PRINCIPAL: solo registros de mi empresa
        ->when($authEmpresaId > 0, function ($query) use ($authEmpresaId) {
            $query->where('empresa_id', $authEmpresaId);
        }, function ($query) {
            // si el usuario no tiene empresa_id, no mostrar nada
            $query->whereRaw('1 = 0');
        })

        // Tu lógica existente de modos
        ->when(!$this->isAlmacenMode, function ($query) use ($authUserId) {
            if ($authUserId > 0) {
                $query->where('user_id', $authUserId);
                return;
            }
            $query->whereRaw('1 = 0');
        })
        ->when($this->isAlmacenMode, function ($query) {
            $query->where('estados_id', (int) $this->estadoAlmacenId)
                ->when($this->userCity !== '', function ($sub) {
                    $sub->whereRaw('trim(upper(origen)) = ?', [$this->userCity]);
                }, function ($sub) {
                    $sub->whereRaw('1 = 0');
                });
        })

        // Búsqueda
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo', 'like', "%{$q}%")
                    ->orWhere('origen', 'like', "%{$q}%")
                    ->orWhere('destino', 'like', "%{$q}%")
                    ->orWhere('nombre_r', 'like', "%{$q}%")
                    ->orWhere('nombre_d', 'like', "%{$q}%")
                    ->orWhere('cantidad', 'like', "%{$q}%")
                    ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($q) {
                        $estadoQuery->where('nombre_estado', 'like', "%{$q}%");
                    })
                    ->orWhereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('user.empresa', function ($empresaQuery) use ($q) {
                        $empresaQuery->where('nombre', 'like', "%{$q}%")
                            ->orWhere('sigla', 'like', "%{$q}%");
                    })
                    ->orWhereHas('empresa', function ($empresaQuery) use ($q) {
                        $empresaQuery->where('nombre', 'like', "%{$q}%")
                            ->orWhere('sigla', 'like', "%{$q}%");
                    });
            });
        })
        ->orderByDesc('id')
        ->paginate(10);

    return view('livewire.recojo', [
        'recojos' => $recojos,
        'users' => User::query()->orderBy('name')->get(['id', 'name']),
        'canRecojoEdit' => $this->userCan($this->modeFeaturePermission('edit')),
        'canRecojoDelete' => $this->userCan($this->modeFeaturePermission('delete')),
        'canRecojoPrint' => $this->userCan($this->modeFeaturePermission('print')),
        'canRecojoReport' => $this->userCan($this->modeFeaturePermission('report')),
        'canCreateContrato' => $this->userCan($this->modeFeaturePermission('create')) || $this->userCanRoute('paquetes-contrato.create'),
        'canCreateContratoTarifa' => $this->userCan($this->modeFeaturePermission('manage')) || $this->userCanRoute('paquetes-contrato.create-con-tarifa'),
    ]);
}

    private function modeFeaturePermission(string $action, ?string $mode = null): string
    {
        $modeKey = $mode ?? $this->mode;
        $routePermission = self::MODE_ROUTE_PERMISSIONS[$modeKey] ?? self::MODE_ROUTE_PERMISSIONS['general'];

        return 'feature.'.$routePermission.'.'.$action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user ? $user->can($permission) : false;
    }

    private function userCanRoute(string $permission): bool
    {
        return $this->userCan($permission);
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }
}
