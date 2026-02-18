<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoExpedicion extends Component
{
    use WithPagination;

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

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function volverApertura($id)
    {
        $despacho = DespachoModel::query()
            ->where('fk_estado', 19)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => 14]);
        session()->flash('success', 'Despacho devuelto a apertura.');
    }

    public function intervenirDespacho($id)
    {
        $despacho = DespachoModel::query()
            ->where('fk_estado', 19)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => 20]);
        session()->flash('success', 'Despacho enviado a intervencion.');
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $userOforigen = $this->getOforigenFromUser();

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->whereIn('fk_estado', [19, 20])
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
        ]);
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
}
