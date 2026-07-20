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

    protected string $paginationTheme = 'bootstrap';

    private const LICENSE_TYPES = [
        'M',
        'P',
        'A',
        'B',
        'C',
        'T',
    ];

    private const FIXED_EMAIL_DOMAIN = '@correos.gob.bo';
    private const DRIVER_ROLE_NAME = 'conductor';
    private const WEB_GUARD = 'web';

    public string $search = '';
    public string $statusFilter = 'activos';

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
    public ?int $viewingDriverId = null;
    public bool $showDetailModal = false;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            in_array($user?->role, ['admin', 'recepcion', 'conductor'], true)
                || (method_exists($user, 'can') && $user->can('livewire.drivers')),
            403
        );

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
        $this->syncExpiredDriverStatuses();

        if ($this->isRegionalUser() && !in_array($this->statusFilter, ['activos', 'licencia_vencida'], true)) {
            $this->statusFilter = 'activos';
        }

        $query = Driver::query()->with('user')->orderBy('nombre');
        $driverProfile = null;

        if (auth()->user()?->role !== 'conductor') {
            $today = now()->toDateString();

            if ($this->statusFilter === 'activos') {
                $query->where('activo', true)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('fecha_vencimiento_licencia')
                            ->orWhereDate('fecha_vencimiento_licencia', '>', $today);
                    });
            } elseif ($this->statusFilter === 'inactivos') {
                $query->where('activo', false)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('fecha_vencimiento_licencia')
                            ->orWhereDate('fecha_vencimiento_licencia', '>', $today);
                    });
            } elseif ($this->statusFilter === 'licencia_vencida') {
                $query->whereDate('fecha_vencimiento_licencia', '<=', $today);
            }
        } else {
            $query->where('activo', true);
        }

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
            : $this->resolveSelectableUsers();
        
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

    public function updatedStatusFilter(string $value): void
    {
        if ($this->isRegionalUser()) {
            $this->statusFilter = in_array($value, ['activos', 'licencia_vencida'], true)
                ? $value
                : 'activos';
            $this->resetPage();

            return;
        }

        if (!in_array($value, ['activos', 'inactivos', 'licencia_vencida', 'todos'], true)) {
            $this->statusFilter = 'activos';
        }

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
        if (auth()->user()?->role === 'conductor' || $this->isRegionalUser()) {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $this->nombre = $this->sanitizeFreeText($this->nombre);
        $this->licencia = $this->sanitizeLicense($this->licencia);
        $this->telefono = $this->sanitizePhone($this->telefono);
        $this->email = $this->sanitizeEmailLocalPart($this->email);

        $this->validate(
            [
                'nombre' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s\.\,\-\/\(\)]+$/u'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'licencia' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\s\/]+$/'],
                'tipo_licencia' => ['required', Rule::in(self::LICENSE_TYPES)],
                'fecha_vencimiento_licencia' => ['required', 'date'],
                'telefono' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
                'email' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
                'activo' => ['required', 'boolean'],
            ],
            [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.string' => 'El nombre debe ser texto.',
                'nombre.max' => 'El nombre no debe superar :max caracteres.',
                'nombre.regex' => 'El nombre contiene caracteres no permitidos.',
                'user_id.required' => 'Debe seleccionar un usuario.',
                'user_id.integer' => 'El usuario seleccionado no es valido.',
                'user_id.exists' => 'El usuario seleccionado no existe.',
                'licencia.required' => 'La licencia es obligatoria.',
                'licencia.string' => 'La licencia debe ser texto.',
                'licencia.max' => 'La licencia no debe superar :max caracteres.',
                'licencia.regex' => 'La licencia solo puede contener letras, numeros, espacios, guiones y diagonales.',
                'tipo_licencia.required' => 'El tipo de licencia es obligatorio.',
                'tipo_licencia.in' => 'El tipo de licencia seleccionado no es valido.',
                'fecha_vencimiento_licencia.required' => 'La fecha de vencimiento es obligatoria.',
                'fecha_vencimiento_licencia.date' => 'La fecha de vencimiento no es valida.',
                'telefono.required' => 'El telefono es obligatorio.',
                'telefono.string' => 'El telefono debe ser texto.',
                'telefono.max' => 'El telefono no debe superar :max caracteres.',
                'telefono.regex' => 'El telefono solo puede contener numeros.',
                'email.required' => 'El email institucional es obligatorio.',
                'email.string' => 'El email institucional debe ser texto.',
                'email.max' => 'El email institucional no debe superar :max caracteres.',
                'email.regex' => 'El correo solo puede contener letras, numeros, punto, guion y guion bajo.',
                'activo.required' => 'Debe definir el estado activo/inactivo.',
                'activo.boolean' => 'El estado activo no es valido.',
            ]
        );

        if (!$this->isUserSelectableForDriver((int) $this->user_id)) {
            $this->addError('user_id', 'Debe seleccionar un usuario con rol conductor que no este vinculado a otro conductor.');
            session()->flash('error', 'No se pudo registrar: el usuario no tiene rol conductor o ya esta vinculado.');
            return;
        }

        if (trim($this->memorandum_path) === '' && !$this->memorandum_file) {
            $this->addError('memorandum_file', 'El memorandum es obligatorio.');
            session()->flash('error', 'No se pudo registrar: el memorandum es obligatorio.');
            return;
        }

        if ($this->memorandum_file) {
            $this->validate(
                [
                    'memorandum_file' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
                ],
                [
                    'memorandum_file.file' => 'El memorandum debe ser un archivo valido.',
                    'memorandum_file.max' => 'El memorandum no debe superar 10 MB.',
                    'memorandum_file.mimes' => 'El memorandum debe ser PDF o imagen (jpg, jpeg, png, webp).',
                ]
            );
        }

        if ($this->driverExistsByField('licencia', $this->licencia)) {
            $this->addError('licencia', 'Esta licencia ya esta registrada.');
            session()->flash('error', 'No se pudo registrar: la licencia ya esta registrada.');
            return;
        }

        if ($this->driverExistsByField('telefono', $this->telefono)) {
            $this->addError('telefono', 'Este numero de telefono ya esta registrado.');
            session()->flash('error', 'No se pudo registrar: el numero de telefono ya esta registrado.');
            return;
        }

        $fullEmail = $this->composeEmail($this->email);
        if ($fullEmail === null) {
            $this->addError('email', 'El email institucional es obligatorio.');
            session()->flash('error', 'No se pudo registrar: el email institucional es obligatorio.');
            return;
        }

        if ($this->driverExistsByField('email', $fullEmail)) {
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
            'telefono' => $this->telefono,
            'email' => $fullEmail,
            'memorandum_path' => $this->memorandum_path,
            'activo' => !$this->isLicenseExpiredOnSelectedDate() && $this->activo,
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
        if (auth()->user()?->role === 'conductor' || $this->isRegionalUser()) {
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
        if (auth()->user()?->role === 'conductor' || $this->isRegionalUser()) {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        $driver->update(['activo' => false]);
        session()->flash('message', 'Conductor inactivado correctamente.');
    }

    public function reactivate(Driver $driver): void
    {
        if (auth()->user()?->role === 'conductor' || $this->isRegionalUser()) {
            session()->flash('error', 'Solo puede visualizar su perfil de conductor.');
            return;
        }

        if ($driver->fecha_vencimiento_licencia && $driver->fecha_vencimiento_licencia->toDateString() <= now()->toDateString()) {
            session()->flash('error', 'No se puede reactivar mientras la licencia este vencida o venza hoy.');
            return;
        }

        $driver->update(['activo' => true]);
        session()->flash('message', 'Conductor reactivado correctamente.');
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
        if (auth()->user()?->role === 'conductor' || $this->isRegionalUser()) {
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

    public function view(Driver $driver): void
    {
        $this->viewingDriverId = (int) $driver->id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingDriverId = null;
    }

    public function updatedUserId($value): void
    {
        if (!$value) {
            return;
        }

        $user = User::find((int) $value);
        if (!$user || !$this->isDriverRoleUser($user)) {
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

    private function syncExpiredDriverStatuses(): void
    {
        Driver::query()
            ->where('activo', true)
            ->whereDate('fecha_vencimiento_licencia', '<=', now()->toDateString())
            ->update(['activo' => false]);
    }

    private function isLicenseExpiredOnSelectedDate(): bool
    {
        if (!$this->fecha_vencimiento_licencia) {
            return false;
        }

        try {
            return $this->fecha_vencimiento_licencia <= now()->toDateString();
        } catch (\Throwable) {
            return false;
        }
    }

    private function isRegionalUser(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            mb_strtolower(trim((string) ($user->role ?? ''))) === 'regional'
            || (method_exists($user, 'hasRole') && $user->hasRole('regional'))
        );
    }

    public function getViewingDriverProperty(): ?Driver
    {
        if (!$this->viewingDriverId) {
            return null;
        }

        return Driver::query()
            ->with('user')
            ->find($this->viewingDriverId);
    }

    private function resolveSelectableUsers()
    {
        $query = User::query()
            ->select(['id', 'name', 'email'])
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery
                    ->where('name', self::DRIVER_ROLE_NAME)
                    ->where('guard_name', self::WEB_GUARD);
            })
            ->whereNotIn('id', function ($driverQuery) {
                $driverQuery->select('user_id')
                    ->from('drivers')
                    ->whereNotNull('user_id');

                if ($this->isEdit && $this->editingDriverId) {
                    $driverQuery->where('id', '!=', $this->editingDriverId);
                }
            })
            ->orderBy('name');

        // Si estamos editando y el usuario actual no cumple filtro, lo mantenemos visible.
        if ($this->isEdit && $this->user_id) {
            $query->orWhere('id', (int) $this->user_id);
        }

        return $query->get();
    }

    private function isUserSelectableForDriver(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::query()->find($userId);
        if (!$user) {
            return false;
        }

        $hasDriverRole = $user->roles()
            ->where('name', self::DRIVER_ROLE_NAME)
            ->where('guard_name', self::WEB_GUARD)
            ->exists();

        if (!$hasDriverRole) {
            return false;
        }

        return !Driver::query()
            ->where('user_id', $userId)
            ->when($this->isEdit && $this->editingDriverId, fn ($query) => $query->where('id', '!=', $this->editingDriverId))
            ->exists();
    }
}
