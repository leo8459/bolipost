<?php

namespace App\Livewire;

use App\Models\Estado;
use App\Models\Recojo as RecojoModel;
use App\Services\ContratoPickupService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class RecojoRecogerEnvios extends Component
{
    use WithPagination;

    public $search = '';

    public $searchQuery = '';

    public $userCity = '';

    public $estadoSolicitudId = null;

    public $estadoAlmacenId = null;

    public $selectedRecojos = [];

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->userCity = strtoupper(trim((string) optional(Auth::user())->ciudad));
        $this->estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);
        $this->estadoAlmacenId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id') ?? 0);
    }

    public function mandarSeleccionadosAlmacen(ContratoPickupService $pickupService)
    {
        $this->authorizePermission('feature.paquetes-contrato.recoger-envios.assign');
        $actor = Auth::user();
        $ids = collect($this->selectedRecojos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('error', 'Selecciona al menos un envio para mandar a ALMACEN.');

            return;
        }

        if (! $actor) {
            session()->flash('error', 'Usuario no autenticado para registrar evento.');

            return;
        }

        try {
            $resultado = $pickupService->recogerPorIds($actor, $ids);
            $actualizados = $resultado['actualizados'];
        } catch (RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->selectedRecojos = [];
        $this->resetPage();

        if ($actualizados <= 0) {
            session()->flash('error', 'No se actualizo ningun envio. Verifica estado y ciudad.');

            return;
        }

        session()->flash('success', $actualizados.' envio(s) enviado(s) a ALMACEN.');
    }

    public function searchRecojos($seleccionarPorCodigo = false)
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
        $hasGlobalDepartmentAccess = (bool) optional(Auth::user())->hasGlobalDepartmentAccess();

        if (! $seleccionarPorCodigo) {
            return;
        }

        $codigo = trim((string) $this->search);
        if ($codigo === '') {
            return;
        }

        $recojo = RecojoModel::query()
            ->when(! $hasGlobalDepartmentAccess && $this->userCity !== '', function ($query) {
                $query->whereRaw('trim(upper(origen)) = ?', [$this->userCity]);
            }, function ($query) use ($hasGlobalDepartmentAccess) {
                if ($hasGlobalDepartmentAccess) {
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->when(! empty($this->estadoSolicitudId), function ($query) {
                $query->where('estados_id', (int) $this->estadoSolicitudId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
            ->first(['id', 'codigo']);

        if (! $recojo) {
            $this->search = '';
            $this->searchQuery = '';

            return;
        }

        $this->selectedRecojos = collect($this->selectedRecojos)
            ->map(fn ($id) => (string) $id)
            ->push((string) $recojo->id)
            ->unique()
            ->values()
            ->all();

        $this->search = '';
        $this->searchQuery = '';
        $this->resetPage();
        session()->flash('success', 'Paquete '.$recojo->codigo.' autoseleccionado.');
    }

    public function render()
    {
        $q = trim((string) $this->searchQuery);
        $hasGlobalDepartmentAccess = (bool) optional(Auth::user())->hasGlobalDepartmentAccess();
        $selectedIds = collect($this->selectedRecojos)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $recojos = RecojoModel::query()
            ->with([
                'empresa:id,nombre,sigla',
                'user:id,name,ciudad,empresa_id',
                'user.empresa:id,nombre,sigla',
                'estadoRegistro:id,nombre_estado',
            ])
            ->when(! $hasGlobalDepartmentAccess && $this->userCity !== '', function ($query) {
                $query->whereRaw('trim(upper(origen)) = ?', [$this->userCity]);
            }, function ($query) use ($hasGlobalDepartmentAccess) {
                if ($hasGlobalDepartmentAccess) {
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->when(! empty($this->estadoSolicitudId), function ($query) {
                $query->where('estados_id', (int) $this->estadoSolicitudId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('destino', 'like', "%{$q}%")
                        ->orWhere('nombre_r', 'like', "%{$q}%")
                        ->orWhere('nombre_d', 'like', "%{$q}%")
                        ->orWhere('telefono_r', 'like', "%{$q}%")
                        ->orWhere('telefono_d', 'like', "%{$q}%")
                        ->orWhereHas('user.empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'like', "%{$q}%")
                                ->orWhere('sigla', 'like', "%{$q}%");
                        })
                        ->orWhereHas('empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'like', "%{$q}%")
                                ->orWhere('sigla', 'like', "%{$q}%");
                        })
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        $selectedPreview = collect();
        if ($selectedIds->isNotEmpty()) {
            $selectedPreview = RecojoModel::query()
                ->with([
                    'empresa:id,nombre,sigla',
                    'user:id,name,ciudad,empresa_id',
                    'user.empresa:id,nombre,sigla',
                    'estadoRegistro:id,nombre_estado',
                ])
                ->whereIn('id', $selectedIds->all())
                ->when(! $hasGlobalDepartmentAccess && $this->userCity !== '', function ($query) {
                    $query->whereRaw('trim(upper(origen)) = ?', [$this->userCity]);
                }, function ($query) use ($hasGlobalDepartmentAccess) {
                    if ($hasGlobalDepartmentAccess) {
                        return;
                    }

                    $query->whereRaw('1 = 0');
                })
                ->when(! empty($this->estadoSolicitudId), function ($query) {
                    $query->where('estados_id', (int) $this->estadoSolicitudId);
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->get([
                    'id',
                    'codigo',
                    'origen',
                    'destino',
                    'nombre_r',
                    'nombre_d',
                    'telefono_r',
                    'telefono_d',
                    'estados_id',
                    'empresa_id',
                    'user_id',
                    'created_at',
                ])
                ->sortBy(fn ($item) => $selectedIds->search((int) $item->id))
                ->values();
        }

        return view('livewire.recojo-recoger-envios', [
            'recojos' => $recojos,
            'selectedPreview' => $selectedPreview,
            'canContratoRecogerAssign' => $this->userCan('feature.paquetes-contrato.recoger-envios.assign'),
            'canContratoRecogerPrint' => $this->userCan('feature.paquetes-contrato.recoger-envios.print'),
        ]);
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
