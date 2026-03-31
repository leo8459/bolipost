<?php

namespace App\Livewire;

use App\Models\Driver;
use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;
use Illuminate\Validation\Rule;

class DriverManager extends Component
{
    use WithPagination;
    use WithFileUploads;

    private const LICENSE_TYPES = [
        'M',
        'P',
        'A',
        'B',
        'C',
        'T',
    ];

    private const FIXED_EMAIL_DOMAIN = '@correos.gob.bo';

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
                    $this->email = $this->extractEmailLocalPart((string) $user->email);
                }
            }

            $nombre = trim((string) request()->query('nombre', ''));
            $email = trim((string) request()->query('email', ''));
            if ($nombre !== '') {
                $this->nombre = $this->sanitizeFreeText($nombre);
            }
            if ($email !== '') {
                $this->email = $this->extractEmailLocalPart($email);
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
            'licenseTypes' => self::LICENSE_TYPES,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->search = trim((string) preg_replace('/[^\pL\pN@\.\-\_\s]/u', '', $this->search));
        $this->resetPage();
    }

    public function updatedNombre($value): void
    {
        $this->nombre = $this->sanitizeFreeText((string) $value);
    }

    public function updatedLicencia($value): void
    {
        $this->licencia = $this->sanitizeLicense((string) $value);

        if ($this->licencia !== '' && $this->driverExistsByField('licencia', $this->licencia)) {
            $this->addError('licencia', 'Esta licencia ya esta registrada.');
            return;
        }

        $this->resetValidation('licencia');
    }

    public function updatedTelefono($value): void
    {
        $this->telefono = $this->sanitizePhone((string) $value);

        if ($this->telefono !== '' && $this->driverExistsByField('telefono', $this->telefono)) {
            $this->addError('telefono', 'Este numero de telefono ya esta registrado.');
            return;
        }

        $this->resetValidation('telefono');
    }

    public function updatedEmail($value): void
    {
        $raw = (string) $value;
        $this->email = $this->sanitizeEmailLocalPart($raw);

        if (str_contains($raw, '@')) {
            $this->addError('email', 'No ingrese arroba; el dominio @correos.gob.bo se agrega automaticamente.');
            return;
        }

        $fullEmail = $this->composeEmail($this->email);
        if ($fullEmail !== null && $this->driverExistsByField('email', $fullEmail)) {
            $this->addError('email', 'Este correo ya esta registrado.');
            return;
        }

        $this->resetValidation('email');
    }

    public function save()
    {
        if (auth()->user()?->role === 'conductor') {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $this->nombre = $this->sanitizeFreeText($this->nombre);
        $this->licencia = $this->sanitizeLicense($this->licencia);
        $this->telefono = $this->sanitizePhone($this->telefono);
        $this->email = $this->sanitizeEmailLocalPart($this->email);

        $this->validate([
            'nombre' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s\.\,\-\/\(\)]+$/u'],
            'licencia' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\s\/]+$/'],
            'tipo_licencia' => ['nullable', Rule::in(self::LICENSE_TYPES)],
            'fecha_vencimiento_licencia' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'email' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activo' => ['boolean'],
        ], [
            'nombre.regex' => 'El nombre contiene caracteres no permitidos.',
            'licencia.regex' => 'La licencia solo puede contener letras, numeros, espacios, guiones y diagonales.',
            'telefono.regex' => 'El telefono solo puede contener numeros.',
            'email.regex' => 'El correo solo puede contener letras, numeros, punto, guion y guion bajo.',
        ]);
        $this->validate([
            'memorandum_file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        if ($this->licencia !== '' && $this->driverExistsByField('licencia', $this->licencia)) {
            $this->addError('licencia', 'Esta licencia ya esta registrada.');
            session()->flash('error', 'No se pudo registrar: la licencia ya esta registrada.');
            return;
        }

        if ($this->telefono !== '' && $this->driverExistsByField('telefono', $this->telefono)) {
            $this->addError('telefono', 'Este numero de telefono ya esta registrado.');
            session()->flash('error', 'No se pudo registrar: el numero de telefono ya esta registrado.');
            return;
        }

        $fullEmail = $this->composeEmail($this->email);
        if ($fullEmail !== null && $this->driverExistsByField('email', $fullEmail)) {
            $this->addError('email', 'Este correo ya esta registrado.');
            session()->flash('error', 'No se pudo registrar: el correo ya esta registrado.');
            return;
        }

        if ($this->memorandum_file) {
            $this->memorandum_path = (string) $this->memorandum_file->store('memorandum-conductores', 'public');
        }

        $data = [
            'nombre' => $this->nombre,
            'user_id' => $this->user_id,
            'licencia' => $this->licencia,
            'tipo_licencia' => $this->tipo_licencia,
            'fecha_vencimiento_licencia' => $this->fecha_vencimiento_licencia,
            'telefono' => $this->telefono !== '' ? $this->telefono : null,
            'email' => $fullEmail,
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
        $this->nombre = $this->sanitizeFreeText((string) $driver->nombre);
        $this->user_id = $driver->user_id;
        $this->licencia = $this->sanitizeLicense((string) $driver->licencia);
        $this->tipo_licencia = $driver->tipo_licencia;
        $this->fecha_vencimiento_licencia = optional($driver->fecha_vencimiento_licencia)->format('Y-m-d');
        $this->telefono = $this->sanitizePhone((string) $driver->telefono);
        $this->email = $this->extractEmailLocalPart((string) $driver->email);
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
            $this->nombre = $this->sanitizeFreeText((string) $user->name);
        }
        if (trim($this->email) === '') {
            $this->email = $this->extractEmailLocalPart((string) $user->email);
        }
    }

    private function sanitizeFreeText(string $value): string
    {
        $value = trim(preg_replace('/[^\pL\pN\s\.\,\-\/\(\)]/u', '', $value) ?? '');
        return mb_substr($value, 0, 255);
    }

    private function sanitizeLicense(string $value): string
    {
        $value = strtoupper(trim(preg_replace('/[^A-Za-z0-9\-\s\/]/', '', $value) ?? ''));
        return mb_substr($value, 0, 50);
    }

    private function sanitizePhone(string $value): string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';
        return mb_substr($value, 0, 20);
    }

    private function sanitizeEmailLocalPart(string $value): string
    {
        $value = trim((string) preg_replace('/@.*$/', '', $value));
        $value = strtolower((string) preg_replace('/[^A-Za-z0-9._-]/', '', $value));

        return mb_substr($value, 0, 100);
    }

    private function extractEmailLocalPart(string $email): string
    {
        $localPart = trim((string) strtok($email, '@'));

        return $this->sanitizeEmailLocalPart($localPart);
    }

    private function composeEmail(string $localPart): ?string
    {
        $localPart = $this->sanitizeEmailLocalPart($localPart);

        if ($localPart === '') {
            return null;
        }

        return $localPart . self::FIXED_EMAIL_DOMAIN;
    }

    private function driverExistsByField(string $field, string $value): bool
    {
        return Driver::query()
            ->where($field, $value)
            ->when($this->editingDriverId, fn ($query) => $query->whereKeyNot($this->editingDriverId))
            ->exists();
    }
}
