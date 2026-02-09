<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Estado as EstadoModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Despacho extends Component
{
    use WithPagination;

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
    public $estado = '';

    public $oficinas = [
        'BOLPZ' => 'BOLPZ - LA PAZ',
        'BOTJA' => 'BOTJA - TARIJA',
        'BOPOI' => 'BOPOI - POTOSI',
        'BOCIJ' => 'BOCIJ - PANDO',
        'BOCBB' => 'BOCBB - COCHABAMBA',
        'BOORU' => 'BOORU - ORURO',
        'BOTDD' => 'BOTDD - BENI',
        'BOSRE' => 'BOSRE - SUCRE',
        'BOSRZ' => 'BOSRZ - SANTA CRUZ',
        'PELIM' => 'PELIM - PERU/LIMA',
    ];

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
        $this->estado = $this->getEstadoApertura();
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
        $this->resetForm();
        $this->estado = $this->getEstadoApertura();
        $this->anio = $this->getCurrentYear();
        $this->oforigen = $this->getOforigenFromUser();
        $this->departamento = $this->getDepartamentoFromUser();
        $this->editingId = null;
        $this->dispatch('openDespachoModal');
    }

    public function openEditModal($id)
    {
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
        $this->estado = $despacho->estado;

        $this->dispatch('openDespachoModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        if ($this->editingId) {
            $despacho = DespachoModel::findOrFail($this->editingId);
            $despacho->update($this->payload());
            session()->flash('success', 'Despacho actualizado correctamente.');
        } else {
            $this->estado = $this->getEstadoApertura();
            $this->anio = $this->getCurrentYear();
            $this->oforigen = $this->getOforigenFromUser() ?: $this->oforigen;
            $this->departamento = $this->getDepartamentoFromUser() ?: $this->departamento;
            DespachoModel::create($this->payload());
            session()->flash('success', 'Despacho creado correctamente.');
        }

        $this->dispatch('closeDespachoModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $despacho = DespachoModel::findOrFail($id);
        $despacho->delete();
        session()->flash('success', 'Despacho eliminado correctamente.');
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
            'estado',
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
            'nro_envase' => 'required|string|max:255',
            'peso' => 'required|numeric|min:0.001',
            'identificador' => 'required|string|max:255',
            'anio' => 'required|integer|min:1900|max:2100',
            'departamento' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
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
            'nro_envase' => $this->nro_envase,
            'peso' => $this->peso,
            'identificador' => $this->identificador,
            'anio' => $this->anio,
            'departamento' => $this->departamento,
            'estado' => $this->estado,
        ];
    }

    protected function getEstadoApertura()
    {
        $estado = EstadoModel::query()
            ->where('nombre_estado', 'APERTURA')
            ->value('nombre_estado');

        return $estado ?: 'APERTURA';
    }

    protected function getCurrentYear()
    {
        return now()->year;
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

        $despachos = DespachoModel::query()
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
                    ->orWhere('estado', 'ILIKE', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.despacho', [
            'despachos' => $despachos,
        ]);
    }
}
