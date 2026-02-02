<?php

namespace App\Livewire;

use App\Models\Destino;
use App\Models\Origen;
use App\Models\PaqueteEms;
use App\Models\Servicio;
use App\Models\Tarifario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PaquetesEms extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $ciudades = [
        'LA PAZ',
        'SANTA CRUZ',
        'PANDO',
        'BENI',
        'TARIJA',
        'CHUQUISACA',
        'ORURO',
        'COCHABAMBA',
        'POTOSI',
    ];

    public $origen = '';
    public $tipo_correspondencia = '';
    public $contenido = '';
    public $cantidad = '';
    public $peso = '';
    public $codigo = '';
    public $precio = '';
    public $nombre_remitente = '';
    public $nombre_envia = '';
    public $carnet = '';
    public $telefono_remitente = '';
    public $nombre_destinatario = '';
    public $telefono_destinatario = '';
    public $ciudad = '';
    public $servicio_id = '';
    public $tarifario_id = '';
    public $destino_id = '';
    public $is_ems = false;
    public $user_origen_id = null;

    protected $paginationTheme = 'bootstrap';

    public $servicios = [];
    public $destinos = [];

    public function mount()
    {
        $this->setOrigenFromUser();
        $this->servicios = Servicio::orderBy('nombre_servicio')->get();
        $this->destinos = Destino::orderBy('nombre_destino')->get();
        $this->setUserOrigenId();
    }

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->setOrigenFromUser();
        $this->setUserOrigenId();
        $this->editingId = null;
        $this->is_ems = false;
        $this->dispatch('openPaqueteModal');
    }

    public function openEditModal($id)
    {
        $paquete = PaqueteEms::findOrFail($id);

        $this->editingId = $paquete->id;
        $this->origen = $paquete->origen;
        $this->tipo_correspondencia = $paquete->tipo_correspondencia;
        $this->contenido = $paquete->contenido;
        $this->cantidad = $paquete->cantidad;
        $this->peso = $paquete->peso;
        $this->codigo = $paquete->codigo;
        $this->precio = $paquete->precio;
        $this->nombre_remitente = $paquete->nombre_remitente;
        $this->nombre_envia = $paquete->nombre_envia;
        $this->carnet = $paquete->carnet;
        $this->telefono_remitente = $paquete->telefono_remitente;
        $this->nombre_destinatario = $paquete->nombre_destinatario;
        $this->telefono_destinatario = $paquete->telefono_destinatario;
        $this->ciudad = $paquete->ciudad;
        $this->tarifario_id = $paquete->tarifario_id;
        $this->servicio_id = optional($paquete->tarifario)->servicio_id;
        $this->destino_id = optional($paquete->tarifario)->destino_id;

        $this->refreshEmsState();
        $this->setUserOrigenId();
        $this->applyTarifarioMatch();

        $this->dispatch('openPaqueteModal');
    }

    public function save()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        $this->setOrigenFromUser();

        $this->applyTarifarioMatch();
        if (!$this->tarifario_id) {
            $this->addError('peso', 'No existe tarifario para este servicio, destino y peso.');
            return;
        }

        $this->validate($this->rules());
        $this->dispatch('openPaqueteConfirm');
    }

    public function saveConfirmed()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        if ($this->editingId) {
            $paquete = PaqueteEms::findOrFail($this->editingId);
            $paquete->update($this->payload());
            session()->flash('success', 'Paquete actualizado correctamente.');
        } else {
            $this->setOrigenFromUser();
            PaqueteEms::create($this->payload($user->id));
            session()->flash('success', 'Paquete creado correctamente.');
        }

        $this->dispatch('closePaqueteConfirm');
        $this->dispatch('closePaqueteModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $paquete = PaqueteEms::findOrFail($id);
        $paquete->delete();
        session()->flash('success', 'Paquete eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'origen',
            'tipo_correspondencia',
            'contenido',
            'cantidad',
            'peso',
            'codigo',
            'precio',
            'nombre_remitente',
            'nombre_envia',
            'carnet',
            'telefono_remitente',
            'nombre_destinatario',
            'telefono_destinatario',
            'ciudad',
            'servicio_id',
            'tarifario_id',
            'destino_id',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'origen' => 'nullable|string|max:255',
            'tipo_correspondencia' => 'required|string|max:255',
            'contenido' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'peso' => 'required|numeric|min:0',
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('paquetes_ems', 'codigo')->ignore($this->editingId),
            ],
            'precio' => 'required|numeric|min:0',
            'nombre_remitente' => 'required|string|max:255',
            'nombre_envia' => 'required|string|max:255',
            'carnet' => 'required|string|max:255',
            'telefono_remitente' => 'required|string|max:50',
            'nombre_destinatario' => 'required|string|max:255',
            'telefono_destinatario' => 'required|string|max:50',
            'ciudad' => ['nullable', 'string', 'max:255'],
            'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
            'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
        ];
    }

    protected function payload($userId = null)
    {
        $payload = [
            'origen' => $this->origen,
            'tipo_correspondencia' => $this->tipo_correspondencia,
            'contenido' => $this->contenido,
            'cantidad' => $this->cantidad,
            'peso' => $this->peso,
            'codigo' => $this->codigo,
            'precio' => $this->precio,
            'nombre_remitente' => $this->nombre_remitente,
            'nombre_envia' => $this->nombre_envia,
            'carnet' => $this->carnet,
            'telefono_remitente' => $this->telefono_remitente,
            'nombre_destinatario' => $this->nombre_destinatario,
            'telefono_destinatario' => $this->telefono_destinatario,
            'ciudad' => $this->ciudad,
            'tarifario_id' => $this->tarifario_id,
        ];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        return $payload;
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $columns = [
            'origen',
            'tipo_correspondencia',
            'contenido',
            'cantidad',
            'peso',
            'codigo',
            'precio',
            'nombre_remitente',
            'nombre_envia',
            'carnet',
            'telefono_remitente',
            'nombre_destinatario',
            'telefono_destinatario',
            'ciudad',
        ];

        $paquetes = PaqueteEms::query()
            ->with(['tarifario.servicio', 'tarifario.destino', 'tarifario.peso', 'tarifario.origen'])
            ->when($q !== '', function ($query) use ($q, $columns) {
                $query->where(function ($sub) use ($q, $columns) {
                    foreach ($columns as $column) {
                        $sub->orWhere($column, 'like', "%{$q}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.paquetes-ems', [
            'paquetes' => $paquetes,
        ]);
    }

    protected function setOrigenFromUser()
    {
        $user = Auth::user();
        if ($user && !empty($user->ciudad)) {
            $this->origen = $user->ciudad;
        }
    }

    protected function setUserOrigenId()
    {
        $this->user_origen_id = null;
        $user = Auth::user();
        if (!$user || empty($user->ciudad)) {
            return;
        }

        $origen = Origen::query()
            ->whereRaw('trim(upper(nombre_origen)) = trim(upper(?))', [$user->ciudad])
            ->first();

        if ($origen) {
            $this->user_origen_id = $origen->id;
        }
    }

    public function updated($name, $value)
    {
        if ($name === 'servicio_id') {
            $this->tarifario_id = '';
            $this->destino_id = '';
            $this->peso = '';
            $this->precio = '';
            $this->refreshEmsState();
            return;
        }

        if ($name === 'destino_id') {
            if ($this->destino_id) {
                $destino = $this->destinos->firstWhere('id', (int) $this->destino_id);
                if ($destino) {
                    $this->ciudad = $destino->nombre_destino;
                }
            }
            $this->applyTarifarioMatch();
            return;
        }

        if ($name === 'peso') {
            $this->applyTarifarioMatch();
        }
    }

    protected function refreshEmsState()
    {
        $this->is_ems = false;
        if (!$this->servicio_id) {
            return;
        }

        $servicio = $this->servicios->firstWhere('id', (int) $this->servicio_id);
        if ($servicio && strtoupper(trim($servicio->nombre_servicio)) === 'EMS') {
            $this->is_ems = true;
        }
    }

    protected function applyTarifarioMatch()
    {
        $this->setUserOrigenId();

        if (
            !$this->servicio_id ||
            !$this->destino_id ||
            !$this->user_origen_id ||
            $this->peso === '' ||
            $this->peso === null
        ) {
            $this->tarifario_id = '';
            $this->precio = '';
            return;
        }

        $peso = (float) $this->peso;

        $tarifario = Tarifario::query()
            ->with(['peso'])
            ->where('servicio_id', $this->servicio_id)
            ->where('destino_id', $this->destino_id)
            ->where('origen_id', $this->user_origen_id)
            ->whereHas('peso', function ($query) use ($peso) {
                $query->where('peso_inicial', '<=', $peso)
                    ->where('peso_final', '>=', $peso);
            })
            ->orderBy('id')
            ->first();

        if (!$tarifario) {
            $this->tarifario_id = '';
            $this->precio = '';
            return;
        }

        $this->tarifario_id = $tarifario->id;
        $this->precio = $tarifario->precio;
    }
}
