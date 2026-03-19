<?php

namespace App\Livewire;

use App\Models\Estado;
use App\Models\PaqueteOrdi;
use App\Models\Ventanilla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class PaquetesOrdi extends Component
{
    use WithPagination;

    private const ROLE_VENTANILLA_MAP = [
        'auxiliar_urbano_dnd' => ['DND', 'CASILLA'],
        'auxiliar_urbano' => ['DD'],
        'auxiliar_7' => ['DD'],
        'auxiliar_urbano_casilla' => ['CASILLA'],
        'encargado_urbano' => ['DD', 'DND'],
    ];

    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;
    private const EVENTO_ID_PAQUETE_CAMINO_UBICACION_NACIONAL = 296;
    private const EVENTO_ID_PAQUETE_RECIBIDO_DESTINO_TRANSITO = 310;
    private const EVENTO_ID_PAQUETE_RECIBIDO_UBICACION_ESPECIFICA = 313;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;
    private const EVENTO_ID_CORRECCION_DATOS = 173;
    private const EVENTO_ID_PAQUETE_RETENIDO_PUNTO_ENTREGA = 183;
    private const EVENTO_ID_PAQUETE_MARCADO_ELIMINADO = 278;

    public $mode = 'clasificacion';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $zonaEditingId = null;
    public $zonaEditValue = '';
    public $selectedPaquetes = [];
    public $selectAll = false;
    public $selectedCiudadMarcado = '';
    public $reprintCodEspecial = '';
    public $codigoRecibir = '';
    public $previewRecibirIds = [];
    public $previewRecibirZonas = [];
    public $reencaminarCiudad = '';
    public $previewReencaminarIds = [];

    public $codigo = '';
    public $destinatario = '';
    public $telefono = '';
    public $ciudad = '';
    public $zona = '';
    public $peso = '';
    public $aduana = '';
    public $observaciones = '';
    public $cod_especial = '';
    public $fk_ventanilla = '';
    public $fk_estado = '';

    protected $paginationTheme = 'bootstrap';

    private const ESTADO_CLASIFICACION = 'CLASIFICACION';
    private const ESTADO_TRANSITO = 'TRANSITO';
    private const ESTADO_ENVIADO = 'ENVIADO';
    private const ESTADO_RECIBIDO = 'RECIBIDO';
    private const ESTADO_ENTREGADO = 'ENTREGADO';
    private const ESTADO_REZAGO = 'REZAGO';
    private const MODE_ROUTE_PERMISSIONS = [
        'clasificacion' => 'paquetes-ordinarios.index',
        'despacho' => 'paquetes-ordinarios.despacho',
        'almacen' => 'paquetes-ordinarios.almacen',
        'entregado' => 'paquetes-ordinarios.entregado',
        'rezago' => 'paquetes-ordinarios.rezago',
    ];

    public function mount($mode = 'clasificacion')
    {
        $allowedModes = ['clasificacion', 'despacho', 'almacen', 'entregado', 'rezago'];
        $this->mode = in_array($mode, $allowedModes, true) ? $mode : 'clasificacion';
    }

    public function getIsClasificacionProperty()
    {
        return $this->mode === 'clasificacion';
    }

    public function getIsDespachoProperty()
    {
        return $this->mode === 'despacho';
    }

    public function getIsAlmacenProperty()
    {
        return $this->mode === 'almacen';
    }

    public function getIsEntregadoProperty()
    {
        return $this->mode === 'entregado';
    }

    public function getIsRezagoProperty()
    {
        return $this->mode === 'rezago';
    }

    public function searchPaquetes()
    {
        $this->searchQuery = $this->search;
        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->selectedCiudadMarcado = '';
        $this->resetPage();
    }

    public function updatedSearch($value)
    {
        $this->searchQuery = $value;
        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->selectedCiudadMarcado = '';
        $this->resetPage();
    }

    public function openRecibirModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'almacen'));

        if (!$this->isAlmacen) {
            return;
        }

        $this->codigoRecibir = '';
        $this->previewRecibirIds = [];
        $this->previewRecibirZonas = [];
        $this->dispatch('openRecibirModal');
    }

    public function openReencaminarModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('reencaminar', 'almacen'));

        if (! $this->isAlmacen) {
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete para reencaminar.');
            return;
        }

        $this->previewReencaminarIds = $ids;
        $this->reencaminarCiudad = '';
        $this->resetValidation();
        $this->dispatch('openReencaminarModal');
    }

    public function saveReencaminar()
    {
        $this->authorizePermission($this->modeFeaturePermission('reencaminar', 'almacen'));

        if (! $this->isAlmacen) {
            return;
        }

        $this->validate([
            'reencaminarCiudad' => 'required|string|max:255',
        ]);

        $ids = collect($this->previewReencaminarIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'No hay paquetes validos para reencaminar.');
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        if (! $estadoRecibidoId) {
            session()->flash('success', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $ciudad = $this->upper($this->reencaminarCiudad);

        $idsActualizar = PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoRecibidoId)
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($idsActualizar)) {
            session()->flash('success', 'Solo se pueden reencaminar paquetes en estado RECIBIDO.');
            return;
        }

        $paquetesReporte = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereIn('id', $idsActualizar)
            ->orderBy('id')
            ->get()
            ->map(function (PaqueteOrdi $paquete) use ($ciudad) {
                $paquete->ciudad_origen = $paquete->ciudad;
                $paquete->ciudad_destino = $ciudad;

                return $paquete;
            });

        PaqueteOrdi::query()
            ->whereIn('id', $idsActualizar)
            ->update(['ciudad' => $ciudad]);

        $this->registrarEventosOrdiPorIds($idsActualizar, self::EVENTO_ID_CORRECCION_DATOS);

        $cantidad = count($idsActualizar);

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->previewReencaminarIds = [];
        $this->reencaminarCiudad = '';
        $this->dispatch('closeReencaminarModal');
        session()->flash('success', $cantidad === 1
            ? 'Paquete reencaminado correctamente.'
            : 'Paquetes reencaminados correctamente.');
        $this->resetPage();

        $pdf = Pdf::loadView('paquetes_ordi.reporte_reencaminar', [
            'packages' => $paquetesReporte,
            'generatedAt' => now(),
            'generatedBy' => (string) optional(auth()->user())->name,
        ])->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-reencaminar-ordinarios-' . now()->format('Ymd-His') . '.pdf');
    }

    public function addCodigoRecibir()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'almacen'));

        if (!$this->isAlmacen) {
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $codigo = $this->upper($this->codigoRecibir);
        if ($codigo === '') {
            session()->flash('success', 'Ingresa un codigo para recibir.');
            return;
        }

        $estadoEnviadoId = $this->getEstadoIdByNombre(self::ESTADO_ENVIADO);
        if (!$estadoEnviadoId) {
            session()->flash('success', 'No existe el estado ENVIADO en la tabla estados.');
            return;
        }

        $paquete = PaqueteOrdi::query()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigo])
            ->where('fk_estado', $estadoEnviadoId)
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->first();

        $desdeApi = false;
        if (!$paquete) {
            $paquete = $this->buscarYGuardarDesdeApiDespacho($codigo, $estadoEnviadoId);
            $desdeApi = true;
        }

        if (!$paquete) {
            if (!session()->has('success')) {
                session()->flash('success', 'El paquete no existe, no esta ENVIADO o no pertenece a tu ciudad.');
            }
            return;
        }

        $this->previewRecibirIds = collect($this->previewRecibirIds)
            ->push((int) $paquete->id)
            ->unique()
            ->values()
            ->all();

        if (!isset($this->previewRecibirZonas[(string) $paquete->id])) {
            $this->previewRecibirZonas[(string) $paquete->id] = $paquete->zona ?? '';
        }

        $this->codigoRecibir = '';
    }

    public function confirmarRecibir()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'almacen'));

        if (!$this->isAlmacen) {
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $ids = collect($this->previewRecibirIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            session()->flash('success', 'No hay paquetes en la previsualizacion.');
            return;
        }

        $estadoEnviadoId = $this->getEstadoIdByNombre(self::ESTADO_ENVIADO);
        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);

        if (!$estadoEnviadoId || !$estadoRecibidoId) {
            session()->flash('success', 'Faltan estados ENVIADO/RECIBIDO en la tabla estados.');
            return;
        }

        $idsActualizar = PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoEnviadoId)
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($idsActualizar)) {
            session()->flash('success', 'No hay paquetes validos para recibir.');
            return;
        }

        foreach ($idsActualizar as $id) {
            $zonaVal = trim($this->previewRecibirZonas[(string) $id] ?? '');
            PaqueteOrdi::where('id', $id)->update([
                'fk_estado' => $estadoRecibidoId,
                'zona'      => $zonaVal !== '' ? $zonaVal : null,
            ]);
        }

        $this->registrarEventosOrdiPorIds($idsActualizar, self::EVENTO_ID_PAQUETE_RECIBIDO_DESTINO_TRANSITO);

        $this->previewRecibirIds = [];
        $this->previewRecibirZonas = [];
        $this->codigoRecibir = '';
        $this->dispatch('closeRecibirModal');
        session()->flash('success', 'Paquetes recibidos correctamente.');
        $this->resetPage();
    }

    private function buscarYGuardarDesdeApiDespacho(string $codigo, int $estadoEnviadoId): ?PaqueteOrdi
    {
        try {
            $response = Http::withToken('eZMlItx6mQMNZjxoijEvf7K3pYvGGXMvEHmQcqvtlAPOEAPgyKDVOpyF7JP0ilbK')
                ->withoutVerifying()
                ->timeout(10)
                ->get('https://admin.correos.gob.bo:8101/api/despacho/' . urlencode($codigo));

            if (!$response->successful()) {
                session()->flash('success', 'API externa: error HTTP ' . $response->status() . '.');
                return null;
            }

            $json = $response->json();

            if (!($json['success'] ?? false) || empty($json['data'])) {
                session()->flash('success', 'API externa: el codigo no fue encontrado.');
                return null;
            }

            $data = $json['data'];

            if (strtoupper(trim($data['ESTADO'] ?? '')) !== 'ENVIADO') {
                session()->flash('success', 'API externa: el paquete no esta en estado ENVIADO (estado: ' . ($data['ESTADO'] ?? 'desconocido') . ').');
                return null;
            }

            $ventanilla = \App\Models\Ventanilla::whereRaw('trim(upper(nombre_ventanilla)) = ?', ['DD'])->first();
            if (!$ventanilla) {
                session()->flash('success', 'API externa: ventanilla "DD" no existe en el sistema.');
                return null;
            }

            $paquete = PaqueteOrdi::create([
                'codigo'        => strtoupper(trim($data['CODIGO'])),
                'destinatario'  => $data['DESTINATARIO'] ?? '',
                'telefono'      => $data['TELEFONO'] ?? '',
                'ciudad'        => strtoupper(trim($data['CUIDAD'] ?? '')),
                'zona'          => $data['ZONA'] ?? '',
                'peso'          => $data['PESO'] ?? 0,
                'aduana'        => $data['ADUANA'] ?? 'NO',
                'observaciones' => $data['OBSERVACIONES'] ?? null,
                'cod_especial'  => $data['PAIS'] ?? null,
                'fk_ventanilla' => $ventanilla->id,
                'fk_estado'     => $estadoEnviadoId,
            ]);

            return $paquete;
        } catch (\Throwable $e) {
            session()->flash('success', 'API externa: error al procesar (' . $e->getMessage() . ').');
            return null;
        }
    }

    public function bajaPaquetes()
    {
        $this->authorizePermission($this->modeFeaturePermission('dropoff', 'almacen'));

        if (!$this->isAlmacen) {
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        $estadoEntregadoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);

        if (!$estadoRecibidoId || !$estadoEntregadoId) {
            session()->flash('success', 'Faltan estados RECIBIDO/ENTREGADO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $idsActualizar = PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoRecibidoId)
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($idsActualizar)) {
            session()->flash('success', 'No hay paquetes validos para dar de baja.');
            return;
        }

        PaqueteOrdi::query()
            ->whereIn('id', $idsActualizar)
            ->update(['fk_estado' => $estadoEntregadoId]);

        $this->registrarEventosOrdiPorIds($idsActualizar, self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE);

        $packages = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->whereIn('id', $idsActualizar)
            ->orderBy('id')
            ->get();

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes enviados a ENTREGADO correctamente.');
        $this->resetPage();

        $pdf = Pdf::loadView('paquetes_ordi.reporte_baja', [
            'packages' => $packages,
        ])->setPaper('A4');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-baja-ordinarios-' . now()->format('Ymd-His') . '.pdf');
    }

    public function rezagoPaquetes()
    {
        $this->authorizePermission($this->modeFeaturePermission('rezago', 'almacen'));

        if (!$this->isAlmacen) {
            return;
        }

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        $estadoRezagoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);

        if (!$estadoRecibidoId || !$estadoRezagoId) {
            session()->flash('success', 'Faltan estados RECIBIDO/REZAGO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $idsActualizar = PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->where('fk_estado', $estadoRecibidoId)
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($idsActualizar)) {
            session()->flash('success', 'No hay paquetes validos para enviar a REZAGO.');
            return;
        }

        PaqueteOrdi::query()
            ->whereIn('id', $idsActualizar)
            ->update(['fk_estado' => $estadoRezagoId]);

        $this->registrarEventosOrdiPorIds($idsActualizar, self::EVENTO_ID_PAQUETE_RETENIDO_PUNTO_ENTREGA);

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        session()->flash('success', 'Paquetes enviados a REZAGO correctamente.');
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->authorizePermission($this->modeFeaturePermission('create', 'clasificacion'));

        $this->resetForm();
        $this->editingId = null;

        $userCity = trim((string) optional(auth()->user())->ciudad);
        if ($userCity !== '') {
            $this->ciudad = $userCity;
        }

        $this->dispatch('openPaqueteOrdiModal');
    }

    public function openEditModal($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $paquete = $this->findAuthorizedPaqueteOrFail($id);

        $this->editingId = $paquete->id;
        $this->codigo = $paquete->codigo;
        $this->destinatario = $paquete->destinatario;
        $this->telefono = $paquete->telefono;
        $this->ciudad = $paquete->ciudad;
        $this->zona = $paquete->zona;
        $this->peso = $paquete->peso;
        $this->aduana = $paquete->aduana;
        $this->observaciones = $paquete->observaciones ?? '';
        $this->cod_especial = $paquete->cod_especial ?? '';
        $this->fk_ventanilla = $paquete->fk_ventanilla ? (string) $paquete->fk_ventanilla : '';
        $this->fk_estado = $paquete->fk_estado ? (string) $paquete->fk_estado : '';

        $this->dispatch('openPaqueteOrdiModal');
    }

    public function openZonaModal($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $paquete = $this->findAuthorizedPaqueteOrFail($id);

        $this->zonaEditingId = $paquete->id;
        $this->zonaEditValue = $paquete->zona ?? '';

        $this->dispatch('openZonaModal');
    }

    public function saveZona()
    {
        $this->authorizePermission($this->modeFeaturePermission('edit'));

        $paquete = $this->findAuthorizedPaqueteOrFail($this->zonaEditingId);

        $this->validate(['zonaEditValue' => 'nullable|string|max:255'], [], ['zonaEditValue' => 'Zona']);

        $paquete->update(['zona' => $this->zonaEditValue ?: null]);

        $this->dispatch('closeZonaModal');
        session()->flash('success', 'Zona actualizada correctamente.');
        $this->zonaEditingId = null;
        $this->zonaEditValue = '';
    }

    public function updatedDestinatario($value)
    {
        $this->destinatario = strtoupper((string) $value);
        $this->autocompletarDatosDestinatario();
    }

    public function updatedTelefono($value)
    {
        $this->telefono = trim((string) $value);
        $this->autocompletarDatosDestinatario();
    }

    public function save()
    {
        $permission = $this->editingId
            ? $this->modeFeaturePermission('edit')
            : $this->modeFeaturePermission('create', 'clasificacion');
        $this->authorizePermission($permission);

        if (!$this->editingId) {
            $estadoClasificacionId = $this->getClasificacionEstadoId();
            if (!$estadoClasificacionId) {
                session()->flash('success', 'No existe el estado CLASIFICACION en la tabla estados.');
                return;
            }
            $this->fk_estado = (string) $estadoClasificacionId;
        }

        $this->validate($this->rules());

        if (! $this->selectedVentanillaIsAllowed()) {
            $this->addError('fk_ventanilla', 'No puedes asignar esa ventanilla.');
            return;
        }

        if ($this->editingId) {
            $paquete = $this->findAuthorizedPaqueteOrFail($this->editingId);
            $paquete->update($this->payload());
            $this->registrarEventoOrdi((string) $paquete->codigo, self::EVENTO_ID_CORRECCION_DATOS);
            session()->flash('success', 'Paquete ordinario actualizado correctamente.');
        } else {
            $paquete = PaqueteOrdi::create($this->payload());
            $this->registrarEventoOrdi((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE);
            session()->flash('success', 'Paquete ordinario creado correctamente.');
        }

        $this->dispatch('closePaqueteOrdiModal');
        $this->resetForm();
    }

    public function despacharSeleccionados()
    {
        $this->authorizePermission($this->modeFeaturePermission('assign', 'clasificacion'));

        $ids = collect($this->selectedPaquetes)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ids = $this->filterAuthorizedIds($ids);

        if (empty($ids)) {
            session()->flash('success', 'Selecciona al menos un paquete.');
            return;
        }

        $estadoTransitoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        if (!$estadoTransitoId) {
            session()->flash('success', 'No existe el estado TRANSITO en la tabla estados.');
            return;
        }

        DB::transaction(function () use ($ids, $estadoTransitoId) {
            $paquetes = $this->accessiblePaquetesQuery()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $correlativo = $this->nextOrdinarioCorrelative();
            $manifiesto = 'O' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT);

            foreach ($paquetes as $paquete) {
                $paquete->fk_estado = $estadoTransitoId;
                $paquete->cod_especial = $manifiesto;
                $paquete->save();
            }
        });

        $this->registrarEventosOrdiPorIds($ids, self::EVENTO_ID_PAQUETE_CAMINO_UBICACION_NACIONAL);

        $packages = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->selectedCiudadMarcado = '';
        session()->flash('success', 'Paquetes enviados a TRANSITO correctamente.');
        $this->resetPage();

        $manifiesto = (string) optional($packages->first())->cod_especial ?: 'O00000';

        return $this->buildManifiestoResponse($packages, $manifiesto);
    }

    public function reimprimirManifiesto()
    {
        $this->authorizePermission($this->modeFeaturePermission('print', 'despacho'));

        if (!$this->isDespacho) {
            return;
        }

        $codigo = $this->upper($this->reprintCodEspecial);
        if ($codigo === '') {
            session()->flash('success', 'Ingresa un cod_especial para reimprimir.');
            return;
        }

        $packages = PaqueteOrdi::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$codigo])
            ->tap(fn (Builder $query) => $this->applyAccessScope($query))
            ->orderBy('id')
            ->get();

        if ($packages->isEmpty()) {
            session()->flash('success', 'No se encontraron paquetes con ese cod_especial.');
            return;
        }

        $this->reprintCodEspecial = '';

        return $this->buildManifiestoResponse($packages, $codigo);
    }

    protected function nextOrdinarioCorrelative(): int
    {
        $lastCode = PaqueteOrdi::query()
            ->whereRaw("cod_especial ~ '^O[0-9]{5}$'")
            ->lockForUpdate()
            ->orderByDesc('cod_especial')
            ->value('cod_especial');

        if (!$lastCode) {
            return 1;
        }

        $number = (int) substr((string) $lastCode, 1, 5);
        return $number > 0 ? $number + 1 : 1;
    }

    protected function siglasCiudad(string $ciudad): string
    {
        $normalized = $this->upper($ciudad);

        $map = [
            'LA PAZ' => 'LP',
            'COCHABAMBA' => 'CB',
            'SANTA CRUZ' => 'SC',
            'ORURO' => 'OR',
            'POTOSI' => 'PT',
            'SUCRE' => 'SQ',
            'TARIJA' => 'TJ',
            'TRINIDAD' => 'BN',
            'COBIJA' => 'PD',
            'VARIOS' => 'VR',
            'N/A' => 'NA',
        ];

        return $map[$normalized] ?? substr(str_replace(' ', '', $normalized), 0, 2);
    }

    protected function buildManifiestoResponse($packages, string $manifiesto)
    {
        $ciudadOrigen = $this->upper((string) optional(auth()->user())->ciudad ?: 'N/A');
        $ciudadesDestino = $packages->pluck('ciudad')
            ->map(fn ($city) => $this->upper($city))
            ->filter()
            ->unique()
            ->values();

        $ciudadDestino = $ciudadesDestino->count() === 1
            ? (string) $ciudadesDestino->first()
            : ($ciudadesDestino->count() > 1 ? 'VARIOS' : 'N/A');

        $pdf = Pdf::loadView('paquetes_ordi.manifiesto-despacho', [
            'packages' => $packages,
            'manifiesto' => $manifiesto,
            'ciudadOrigen' => $ciudadOrigen,
            'ciudadDestino' => $ciudadDestino,
            'siglasOrigen' => $this->siglasCiudad($ciudadOrigen),
            'siglasDestino' => $this->siglasCiudad($ciudadDestino),
            'anioPaquete' => now()->format('Y'),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'manifiesto-clasificacion-' . $manifiesto . '.pdf');
    }

    public function delete($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('delete', 'clasificacion'));

        $paquete = $this->findAuthorizedPaqueteOrFail($id);
        $codigo = (string) $paquete->codigo;
        $paquete->delete();
        $this->registrarEventoOrdi($codigo, self::EVENTO_ID_PAQUETE_MARCADO_ELIMINADO);
        session()->flash('success', 'Paquete ordinario eliminado correctamente.');
    }

    public function devolverAClasificacion($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('restore', 'despacho'));

        $estadoClasificacionId = $this->getClasificacionEstadoId();
        if (!$estadoClasificacionId) {
            session()->flash('success', 'No existe el estado CLASIFICACION en la tabla estados.');
            return;
        }

        $paquete = $this->findAuthorizedPaqueteOrFail($id);
        $paquete->update([
            'fk_estado' => $estadoClasificacionId,
        ]);
        $this->registrarEventoOrdi((string) $paquete->codigo, self::EVENTO_ID_CORRECCION_DATOS);

        session()->flash('success', 'Paquete devuelto a CLASIFICACION correctamente.');
        $this->resetPage();
    }

    public function altaAAlmacen($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('restore', 'entregado'));

        if (!$this->isEntregado) {
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        if (!$estadoRecibidoId) {
            session()->flash('success', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $paquete = $this->accessiblePaquetesQuery()
            ->where('id', $id)
            ->firstOrFail();

        $paquete->update([
            'fk_estado' => $estadoRecibidoId,
        ]);
        $this->registrarEventoOrdi((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_UBICACION_ESPECIFICA);

        session()->flash('success', 'Paquete dado de alta a ALMACEN correctamente.');
        $this->resetPage();
    }

    public function reimprimirFormularioEntrega($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('print', 'entregado'));

        if (!$this->isEntregado) {
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $paquete = $this->accessiblePaquetesQuery()
            ->with(['estado', 'ventanillaRef'])
            ->where('id', $id)
            ->first();

        if (!$paquete) {
            session()->flash('success', 'No se encontro el paquete para reimprimir.');
            return;
        }

        $pdf = Pdf::loadView('paquetes_ordi.reporte_baja', [
            'packages' => collect([$paquete]),
        ])->setPaper('A4');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'formulario-entrega-' . $paquete->codigo . '.pdf');
    }

    public function devolverRezagoAAlmacen($id)
    {
        $this->authorizePermission($this->modeFeaturePermission('restore', 'rezago'));

        if (!$this->isRezago) {
            return;
        }

        $estadoRecibidoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        if (!$estadoRecibidoId) {
            session()->flash('success', 'No existe el estado RECIBIDO en la tabla estados.');
            return;
        }

        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($userCity === '') {
            session()->flash('success', 'Tu usuario no tiene ciudad configurada.');
            return;
        }

        $paquete = $this->accessiblePaquetesQuery()
            ->where('id', $id)
            ->firstOrFail();

        $paquete->update([
            'fk_estado' => $estadoRecibidoId,
        ]);
        $this->registrarEventoOrdi((string) $paquete->codigo, self::EVENTO_ID_PAQUETE_RECIBIDO_UBICACION_ESPECIFICA);

        session()->flash('success', 'Paquete devuelto a ALMACEN correctamente.');
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset([
            'codigo',
            'destinatario',
            'telefono',
            'ciudad',
            'zona',
            'peso',
            'aduana',
            'observaciones',
            'cod_especial',
            'fk_ventanilla',
            'fk_estado',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'destinatario' => 'required|string|max:255',
            'telefono' => 'required|string|max:30',
            'ciudad' => 'required|string|max:255',
            'zona' => 'nullable|string|max:255',
            'peso' => 'required|numeric|min:0',
            'aduana' => 'required|string|max:50',
            'observaciones' => 'nullable|string|max:1000',
            'cod_especial' => 'nullable|string|max:255',
            'fk_ventanilla' => 'required|exists:ventanilla,id',
            'fk_estado' => 'required|exists:estados,id',
        ];
    }

    protected function autocompletarDatosDestinatario(): void
    {
        if ($this->editingId) {
            return;
        }

        $destinatario = $this->upper($this->destinatario);
        $telefono = trim((string) $this->telefono);

        if ($destinatario === '' && $telefono === '') {
            return;
        }

        $registro = PaqueteOrdi::query()
            ->when($this->upper($this->ciudad) !== '', function (Builder $query) {
                $query->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$this->upper($this->ciudad)]);
            })
            ->when($destinatario !== '' && $telefono !== '', function (Builder $query) use ($destinatario, $telefono) {
                $query->whereRaw('trim(upper(destinatario)) = trim(upper(?))', [$destinatario])
                    ->whereRaw('trim(telefono) = trim(?)', [$telefono]);
            }, function (Builder $query) use ($destinatario, $telefono) {
                $query->where(function (Builder $subQuery) use ($destinatario, $telefono) {
                    if ($destinatario !== '') {
                        $subQuery->orWhereRaw('trim(upper(destinatario)) = trim(upper(?))', [$destinatario]);
                    }

                    if ($telefono !== '') {
                        $subQuery->orWhereRaw('trim(telefono) = trim(?)', [$telefono]);
                    }
                });
            })
            ->orderByDesc('id')
            ->first(['destinatario', 'telefono', 'zona']);

        if (!$registro) {
            return;
        }

        $this->destinatario = $this->upper($registro->destinatario ?: $this->destinatario);
        $this->telefono = trim((string) ($registro->telefono ?: $this->telefono));
        $this->zona = $this->upper($registro->zona ?: $this->zona);
    }

    protected function payload()
    {
        return [
            'codigo' => $this->upper($this->codigo),
            'destinatario' => $this->upper($this->destinatario),
            'telefono' => trim((string) $this->telefono),
            'ciudad' => $this->upper($this->ciudad),
            'zona' => $this->upper($this->zona),
            'peso' => $this->peso,
            'aduana' => $this->upper($this->aduana),
            'observaciones' => $this->emptyToNull($this->observaciones),
            'cod_especial' => $this->emptyToNull($this->cod_especial),
            'fk_ventanilla' => $this->fk_ventanilla,
            'fk_estado' => $this->fk_estado,
        ];
    }

    protected function emptyToNull($value)
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $this->upper($trimmed);
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    protected function registrarEventosOrdiPorIds(array $ids, int $eventoId): void
    {
        $ids = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $codigos = PaqueteOrdi::query()
            ->whereIn('id', $ids)
            ->pluck('codigo')
            ->filter(fn ($codigo) => trim((string) $codigo) !== '')
            ->values()
            ->all();

        $this->registrarEventosOrdi($codigos, $eventoId);
    }

    protected function registrarEventoOrdi(string $codigo, int $eventoId): void
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return;
        }

        $this->registrarEventosOrdi([$codigo], $eventoId);
    }

    protected function registrarEventosOrdi(iterable $codigos, int $eventoId): void
    {
        $userId = (int) optional(auth()->user())->id;
        if ($eventoId <= 0 || $userId <= 0) {
            return;
        }

        $now = now();
        $rows = collect($codigos)
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter()
            ->unique()
            ->map(function (string $codigo) use ($eventoId, $userId, $now) {
                return [
                    'codigo' => $codigo,
                    'evento_id' => $eventoId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if (empty($rows)) {
            return;
        }

        DB::table('eventos_ordi')->insert($rows);
    }

    public function updatedCiudad($value)
    {
        $this->ciudad = $this->upper($value);
        $this->fk_ventanilla = '';
        $this->selectAll = false;
        $this->selectedPaquetes = [];
        $this->resetPage();
    }

    public function changeCiudad($value)
    {
        $this->updatedCiudad($value);
    }

    protected function getClasificacionEstadoId(): ?int
    {
        return $this->getEstadoIdByNombre(self::ESTADO_CLASIFICACION);
    }

    protected function getEstadoIdByNombre(string $estado): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($estado))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function getVentanillasByCiudad()
    {
        $ciudad = $this->upper($this->ciudad);

        return $this->availableVentanillasQuery()
            ->when($ciudad === 'LA PAZ', function ($query) {
                $query->where(function ($sub) {
                    $sub->whereRaw('trim(upper(nombre_ventanilla)) = ?', ['DD'])
                        ->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', ['DND'])
                        ->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', ['CASILLA']);
                });
            })
            ->when($ciudad !== '' && $ciudad !== 'LA PAZ', function ($query) {
                $query->whereRaw('trim(upper(nombre_ventanilla)) = ?', ['UNICA']);
            })
            ->orderBy('nombre_ventanilla')
            ->get();
    }

    public function toggleSelectAll($checked)
    {
        if (!$this->isClasificacion && !$this->isAlmacen) {
            return;
        }

        if ((bool) $checked) {
            $this->selectedPaquetes = collect($this->allFilteredPaqueteIds())
                ->map(fn ($id) => (string) $id)
                ->all();
            $this->selectAll = true;
            return;
        }

        $this->selectAll = false;
        $this->selectedPaquetes = [];
    }

    public function updatedSelectedPaquetes()
    {
        if (!$this->isClasificacion && !$this->isAlmacen) {
            return;
        }

        $allIds = $this->allFilteredPaqueteIds();
        if (empty($allIds)) {
            $this->selectAll = false;
            return;
        }

        $selected = collect($this->selectedPaquetes)
            ->map(fn ($id) => (int) $id);

        $this->selectAll = collect($allIds)->every(
            fn ($id) => $selected->contains((int) $id)
        );
    }

    public function updatedSelectedCiudadMarcado($value)
    {
        if (!$this->isClasificacion) {
            return;
        }

        $ciudad = $this->upper($value);
        $this->selectedCiudadMarcado = $ciudad;

        if ($ciudad === '') {
            $this->selectAll = false;
            $this->selectedPaquetes = [];
            return;
        }

        $this->selectedPaquetes = $this->basePaquetesQuery()
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$ciudad])
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->updatedSelectedPaquetes();
    }

    protected function ciudadesDisponibles(): array
    {
        if ($this->isDespacho) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        } elseif ($this->isAlmacen) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        } elseif ($this->isEntregado) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        } elseif ($this->isRezago) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);
        } else {
            $estadoModoId = $this->getClasificacionEstadoId();
        }
        $userCity = $this->upper((string) optional(auth()->user())->ciudad);

        if (!$estadoModoId || $userCity === '') {
            return [];
        }

        return PaqueteOrdi::query()
            ->where('fk_estado', $estadoModoId)
            ->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity])
            ->whereNotNull('ciudad')
            ->select('ciudad')
            ->distinct()
            ->orderBy('ciudad')
            ->pluck('ciudad')
            ->map(fn ($ciudad) => $this->upper($ciudad))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function allFilteredPaqueteIds(): array
    {
        return $this->basePaquetesQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function basePaquetesQuery(): Builder
    {
        $q = trim($this->searchQuery);
        $userCity = $this->upper((string) optional(auth()->user())->ciudad);
        if ($this->isDespacho) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_TRANSITO);
        } elseif ($this->isAlmacen) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_RECIBIDO);
        } elseif ($this->isEntregado) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_ENTREGADO);
        } elseif ($this->isRezago) {
            $estadoModoId = $this->getEstadoIdByNombre(self::ESTADO_REZAGO);
        } else {
            $estadoModoId = $this->getClasificacionEstadoId();
        }

        return $this->accessiblePaquetesQuery()
            ->with(['estado', 'ventanillaRef'])
            ->when($estadoModoId, function ($query) use ($estadoModoId) {
                $query->where('fk_estado', $estadoModoId);
            })
            ->when(!$estadoModoId, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('destinatario', 'ILIKE', "%{$q}%")
                        ->orWhere('telefono', 'ILIKE', "%{$q}%")
                        ->orWhere('ciudad', 'ILIKE', "%{$q}%")
                        ->orWhere('zona', 'ILIKE', "%{$q}%")
                        ->orWhere('peso', 'ILIKE', "%{$q}%")
                        ->orWhere('aduana', 'ILIKE', "%{$q}%")
                        ->orWhere('observaciones', 'ILIKE', "%{$q}%")
                        ->orWhere('cod_especial', 'ILIKE', "%{$q}%")
                        ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                            $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                        })
                        ->orWhereHas('ventanillaRef', function ($ventanillaQuery) use ($q) {
                            $ventanillaQuery->where('nombre_ventanilla', 'ILIKE', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id');
    }

    public function render()
    {
        $paquetes = $this->basePaquetesQuery()->paginate(10);
        $previewRecibirPaquetes = collect();
        if (!empty($this->previewRecibirIds)) {
            $previewRecibirPaquetes = $this->accessiblePaquetesQuery()
                ->with(['estado', 'ventanillaRef'])
                ->whereIn('id', $this->previewRecibirIds)
                ->orderBy('id')
                ->get();
        }

        $previewReencaminarPaquetes = collect();
        if (! empty($this->previewReencaminarIds)) {
            $previewReencaminarPaquetes = $this->accessiblePaquetesQuery()
                ->with(['estado', 'ventanillaRef'])
                ->whereIn('id', $this->previewReencaminarIds)
                ->orderBy('id')
                ->get();
        }

        return view('livewire.paquetes-ordi', [
            'paquetes' => $paquetes,
            'ventanillas' => $this->getVentanillasByCiudad(),
            'ciudadesDisponibles' => $this->ciudadesDisponibles(),
            'previewRecibirPaquetes' => $previewRecibirPaquetes,
            'previewReencaminarPaquetes' => $previewReencaminarPaquetes,
            'canOrdiAssign' => $this->userCan($this->modeFeaturePermission('assign')),
            'canOrdiDelete' => $this->userCan($this->modeFeaturePermission('delete')),
            'canOrdiDropoff' => $this->userCan($this->modeFeaturePermission('dropoff')),
            'canOrdiRezago' => $this->userCan($this->modeFeaturePermission('rezago')),
            'canOrdiReencaminar' => $this->userCan($this->modeFeaturePermission('reencaminar')),
            'canOrdiCreate' => $this->userCan($this->modeFeaturePermission('create')),
            'canOrdiPrint' => $this->userCan($this->modeFeaturePermission('print')),
            'canOrdiEdit' => $this->userCan($this->modeFeaturePermission('edit')),
            'canOrdiRestore' => $this->userCan($this->modeFeaturePermission('restore')),
        ]);
    }

    private function modeFeaturePermission(string $action, ?string $mode = null): string
    {
        $routePermission = self::MODE_ROUTE_PERMISSIONS[$mode ?? $this->mode] ?? self::MODE_ROUTE_PERMISSIONS['clasificacion'];

        return 'feature.'.$routePermission.'.'.$action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can($permission);
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }

    private function accessiblePaquetesQuery(): Builder
    {
        $query = PaqueteOrdi::query();
        $this->applyAccessScope($query);

        return $query;
    }

    private function findAuthorizedPaqueteOrFail(int $id): PaqueteOrdi
    {
        return $this->accessiblePaquetesQuery()->findOrFail($id);
    }

    private function filterAuthorizedIds(array $ids): array
    {
        $ids = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        return $this->accessiblePaquetesQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function selectedVentanillaIsAllowed(): bool
    {
        if (empty($this->fk_ventanilla)) {
            return false;
        }

        return $this->availableVentanillasQuery()
            ->whereKey($this->fk_ventanilla)
            ->exists();
    }

    private function availableVentanillasQuery(): Builder
    {
        $query = Ventanilla::query();
        $ventanillas = $this->restrictedVentanillaNames();

        if ($ventanillas !== null) {
            $query->where(function ($restrictedQuery) use ($ventanillas) {
                foreach ($ventanillas as $ventanilla) {
                    $restrictedQuery->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', [$ventanilla]);
                }
            });
        }

        return $query;
    }

    private function applyAccessScope(Builder $query): void
    {
        $userCity = $this->upper((string) optional(auth()->user())->ciudad);

        if ($userCity !== '') {
            $query->whereRaw('trim(upper(ciudad)) = trim(upper(?))', [$userCity]);
        } else {
            $query->whereRaw('1 = 0');
        }

        $ventanillas = $this->restrictedVentanillaNames();
        if ($ventanillas === null) {
            return;
        }

        $query->whereHas('ventanillaRef', function (Builder $ventanillaQuery) use ($ventanillas) {
            $ventanillaQuery->where(function (Builder $restrictedQuery) use ($ventanillas) {
                foreach ($ventanillas as $ventanilla) {
                    $restrictedQuery->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', [$ventanilla]);
                }
            });
        });
    }

    private function restrictedVentanillaNames(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return null;
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');
        if ($superAdminRole !== '' && $user->hasRole($superAdminRole)) {
            return null;
        }

        // Roles de Ventanilla Única (sin restricción de ciudad)
        if ($user->hasRole('encargado_unica') || $user->hasRole('auxiliar_unica')) {
            return ['UNICA'];
        }

        $userCity = $this->upper((string) optional($user)->ciudad);
        if ($userCity !== 'LA PAZ') {
            return null;
        }

        foreach (self::ROLE_VENTANILLA_MAP as $role => $ventanillas) {
            if ($user->hasRole($role)) {
                return $ventanillas;
            }
        }

        return null;
    }
}
