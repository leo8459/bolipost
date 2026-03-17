<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class DriverManager extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $search = '';

    #[Validate('required|string|max:255')]
    public string $nombre = '';

    public ?int $user_id = null;
    public string $licencia = '';
    public string $tipo_licencia = '';
    public ?string $fecha_vencimiento_licencia = null;
    public string $telefono = '';
    public string $email = '';
    public string $memorandum_path = '';
    public $memorandum_file = null;
    public bool $activo = true;

    public bool $isEdit = false;
    public ?int $editingDriverId = null;
    public bool $showForm = false;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);

        if (auth()->user()?->role !== 'conductor' && (bool) request()->boolean('create_driver')) {
            $this->resetForm();
            $this->showForm = true;

            $userId = request()->integer('user_id');
            if ($userId > 0) {
                $this->user_id = $userId;
                $user = User::find($userId);
                if ($user) {
                    $this->nombre = (string) $user->name;
                    $this->email = (string) $user->email;
                }
            }

            $nombre = trim((string) request()->query('nombre', ''));
            $email = trim((string) request()->query('email', ''));
            if ($nombre !== '') {
                $this->nombre = $nombre;
            }
            if ($email !== '') {
                $this->email = $email;
            }
        }
    }

    public function render()
    {
        $query = Driver::with('user')->orderBy('nombre');
        $driverProfile = null;

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('licencia', 'like', "%{$search}%")
                    ->orWhere('tipo_licencia', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(activo AS TEXT) ILIKE ?', ["%{$search}%"])
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (auth()->user()?->role === 'conductor') {
            $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
            if ($driverId > 0) {
                $query->whereKey($driverId);
                $driverProfile = Driver::with('user')->find($driverId);
            } else {
                $query->whereRaw('1=0');
            }
        }

        $drivers = $query->paginate(10);
        $users = auth()->user()?->role === 'conductor'
            ? collect()
            : User::all(['id', 'name', 'email']);
        
        return view('livewire.driver-manager', [
            'drivers' => $drivers,
            'users' => $users,
            'driverProfile' => $driverProfile,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save()
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $this->validate();
        $this->validate([
            'memorandum_file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        if ($this->memorandum_file) {
            $this->memorandum_path = (string) $this->memorandum_file->store('memorandum-conductores', 'public');
        }

        $data = [
            'nombre' => $this->nombre,
            'user_id' => $this->user_id,
            'licencia' => $this->licencia,
            'tipo_licencia' => $this->tipo_licencia,
            'fecha_vencimiento_licencia' => $this->fecha_vencimiento_licencia,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'memorandum_path' => $this->memorandum_path !== '' ? $this->memorandum_path : null,
            'activo' => $this->activo,
        ];

        if ($this->isEdit && $this->editingDriverId) {
            $driver = Driver::find($this->editingDriverId);
            if ($driver) {
                $driver->update($data);
                session()->flash('message', 'Conductor actualizado correctamente.');
            }
        } else {
            Driver::create($data);
            session()->flash('message', 'Conductor creado correctamente.');
        }

        $this->resetForm();
    }

    public function edit(Driver $driver)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $this->showForm = true;
        $this->isEdit = true;
        $this->editingDriverId = $driver->id;
        $this->nombre = $driver->nombre;
        $this->user_id = $driver->user_id;
        $this->licencia = $driver->licencia;
        $this->tipo_licencia = $driver->tipo_licencia;
        $this->fecha_vencimiento_licencia = optional($driver->fecha_vencimiento_licencia)->format('Y-m-d');
        $this->telefono = $driver->telefono;
        $this->email = $driver->email;
        $this->memorandum_path = (string) ($driver->memorandum_path ?? '');
        $this->memorandum_file = null;
        $this->activo = $driver->activo;
    }

    public function delete(Driver $driver)
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $driver->delete();
        session()->flash('message', 'Conductor eliminado correctamente.');
    }

    public function resetForm()
    {
        $this->nombre = '';
        $this->user_id = null;
        $this->licencia = '';
        $this->tipo_licencia = '';
        $this->fecha_vencimiento_licencia = null;
        $this->telefono = '';
        $this->email = '';
        $this->memorandum_path = '';
        $this->memorandum_file = null;
        $this->activo = true;
        $this->isEdit = false;
        $this->editingDriverId = null;
        $this->showForm = false;
        $this->resetPage();
    }

    public function create()
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function cancelForm()
    {
        $this->resetForm();
    }

    public function updatedUserId($value): void
    {
        if (!$value) {
            return;
        }

        $user = User::find((int) $value);
        if (!$user) {
            return;
        }

        if (trim($this->nombre) === '') {
            $this->nombre = (string) $user->name;
        }
        if (trim($this->email) === '') {
            $this->email = (string) $user->email;
        }
    }

}
