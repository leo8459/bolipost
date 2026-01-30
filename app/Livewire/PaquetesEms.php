<?php

namespace App\Livewire;

use App\Models\PaqueteEms;
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

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $this->setOrigenFromUser();
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
        $this->editingId = null;
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

        $this->dispatch('openPaqueteModal');
    }

    public function save()
    {
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Usuario no autenticado.');
            return;
        }

        if ($this->editingId) {
            $this->validate($this->rules());
            $paquete = PaqueteEms::findOrFail($this->editingId);
            $paquete->update($this->payload());
            session()->flash('success', 'Paquete actualizado correctamente.');
        } else {
            $this->setOrigenFromUser();
            $this->validate($this->rules());
            PaqueteEms::create($this->payload($user->id));
            session()->flash('success', 'Paquete creado correctamente.');
        }

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
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'origen' => 'required|string|max:255',
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
            'ciudad' => ['required', 'string', 'max:255', Rule::in($this->ciudades)],
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
}
