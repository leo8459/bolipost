<?php

namespace App\Livewire;

use App\Models\Estado;
use App\Models\Recojo as RecojoModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RecojoCartero extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $searchExactCodigo = false;
    public $userEmpresaId = null;
    public $estadoCarteroId = null;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->userEmpresaId = (int) (optional(Auth::user())->empresa_id ?? 0);
        $this->estadoCarteroId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CARTERO'])
            ->value('id') ?? 0);
    }

    public function searchRecojos($exactCodigo = false)
    {
        $this->searchQuery = trim((string) $this->search);
        $this->searchExactCodigo = (bool) $exactCodigo;
        $this->resetPage();

        if ($this->searchExactCodigo && $this->searchQuery !== '') {
            $found = $this->baseQuery()
                ->where(function ($query) {
                    $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$this->searchQuery])
                        ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$this->searchQuery]);
                })
                ->exists();

            if (!$found) {
                session()->flash('error', 'No se encontro contrato CARTERO para ese codigo en tu empresa.');
                return;
            }

            session()->flash('success', 'Mostrando resultado para codigo: ' . strtoupper($this->searchQuery));
        }
    }

    protected function baseQuery()
    {
        return RecojoModel::query()
            ->with([
                'empresa:id,nombre,sigla',
                'estadoRegistro:id,nombre_estado',
                'user:id,name',
            ])
            ->when($this->estadoCarteroId > 0, function ($query) {
                $query->where('estados_id', (int) $this->estadoCarteroId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($this->userEmpresaId > 0, function ($query) {
                $query->where('empresa_id', (int) $this->userEmpresaId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            });
    }

    public function render()
    {
        $q = trim((string) $this->searchQuery);

        $recojos = $this->baseQuery()
            ->when($q !== '', function ($query) use ($q) {
                if ($this->searchExactCodigo) {
                    $query->where(function ($sub) use ($q) {
                        $sub->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$q])
                            ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$q]);
                    });
                    return;
                }

                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('cod_especial', 'like', "%{$q}%")
                        ->orWhere('origen', 'like', "%{$q}%")
                        ->orWhere('destino', 'like', "%{$q}%")
                        ->orWhere('nombre_r', 'like', "%{$q}%")
                        ->orWhere('nombre_d', 'like', "%{$q}%")
                        ->orWhere('telefono_r', 'like', "%{$q}%")
                        ->orWhere('telefono_d', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->simplePaginate(100);

        return view('livewire.recojo-cartero', [
            'recojos' => $recojos,
        ]);
    }
}

