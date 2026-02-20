<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Estado as EstadoModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoExpedicion extends Component
{
    use WithPagination;
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

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function volverApertura($id)
    {
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
        $estadoClausuraId = $this->getEstadoIdByNombre('CLAUSURA', 14);

        $despacho = DespachoModel::query()
            ->where('fk_estado', $estadoExpedicionId)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => $estadoClausuraId]);
        session()->flash('success', 'Despacho devuelto a apertura.');
    }

    public function intervenirDespacho($id)
    {
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
        $estadoIntervenirId = $this->getEstadoIdByNombre('INTERVENIR', 20);

        $despacho = DespachoModel::query()
            ->where('fk_estado', $estadoExpedicionId)
            ->findOrFail($id);

        $despacho->update(['fk_estado' => $estadoIntervenirId]);
        session()->flash('success', 'Despacho enviado a intervencion.');
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
}
