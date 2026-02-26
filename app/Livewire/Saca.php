<?php

namespace App\Livewire;

use App\Models\Despacho;
use App\Models\Estado as EstadoModel;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Saca as SacaModel;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Saca extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nro_saca = '';
    public $identificador = '';
    public $fk_estado = '';
    public $peso = '';
    public $paquetes = '';
    public $busqueda = '';
    public $receptaculo = '';
    public $fk_despacho = '';
    public $codEspecialSugerencias = [];
    public $lockedDespachoId = null;
    public $lockedDespachoLabel = '';
    public $openCreateModalOnLoad = false;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $despachoId = (int) request()->query('despacho_id', 0);

        if ($despachoId <= 0) {
            $this->fk_despacho = $this->getDefaultDespachoId();
            return;
        }

        $despacho = Despacho::query()
            ->find($despachoId, ['id', 'identificador', 'anio', 'nro_despacho']);

        if (!$despacho) {
            return;
        }

        $this->lockedDespachoId = $despacho->id;
        $this->lockedDespachoLabel = "{$despacho->identificador} ({$despacho->anio}-{$despacho->nro_despacho})";
        $this->fk_despacho = $despacho->id;
        $this->openCreateModalOnLoad = true;
    }

    public function rendered()
    {
        if (!$this->openCreateModalOnLoad) {
            return;
        }

        $this->openCreateModalOnLoad = false;
        $this->dispatch('openSacaModal');
    }

    public function searchSacas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $estadoId = $this->getEstadoAperturaId();
        if ($estadoId === null) {
            $this->addError('fk_estado', 'No existe un estado valido para crear la saca.');
            return;
        }
        $this->fk_estado = (string) $estadoId;
        if ($this->lockedDespachoId) {
            $this->fk_despacho = $this->lockedDespachoId;
        } else {
            $this->fk_despacho = $this->getDefaultDespachoId();
        }
        $this->editingId = null;
        $this->dispatch('openSacaModal');
    }

    public function openEditModal($id)
    {
        $saca = SacaModel::query()
            ->when($this->lockedDespachoId, function ($query) {
                $query->where('fk_despacho', $this->lockedDespachoId);
            })
            ->findOrFail($id);

        $this->editingId = $saca->id;
        $this->nro_saca = $saca->nro_saca;
        $this->identificador = $saca->identificador;
        $this->fk_estado = $saca->fk_estado;
        $this->peso = $saca->peso;
        $this->paquetes = $saca->paquetes;
        $this->busqueda = $saca->busqueda;
        $this->receptaculo = $saca->receptaculo;
        $this->fk_despacho = $saca->fk_despacho;
        $this->cargarSugerenciasCodEspecial($this->busqueda);

        $this->dispatch('openSacaModal');
    }

    public function updatedBusqueda($value)
    {
        $this->cargarSugerenciasCodEspecial($value);
    }

    public function seleccionarCodEspecial($codigo)
    {
        $this->busqueda = $codigo;
        $this->codEspecialSugerencias = [];
    }

    public function save()
    {
        if (!$this->editingId) {
            $this->nro_saca = $this->getNextNroSaca();
            $estadoId = $this->getEstadoAperturaId();
            if ($estadoId === null) {
                $this->addError('fk_estado', 'No existe un estado valido para crear la saca.');
                return;
            }
            $this->fk_estado = (string) $estadoId;
            if (empty($this->fk_despacho)) {
                $this->fk_despacho = $this->getDefaultDespachoId();
            }
            if (empty($this->fk_despacho)) {
                $this->addError('fk_despacho', 'No hay un despacho disponible para asociar la saca.');
                return;
            }
        }

        $this->validate($this->rules());

        if (!$this->normalizarBusquedaDesdeCodEspecial()) {
            return;
        }

        $generatedIdentificador = $this->buildIdentificadorFromDespacho();
        if ($generatedIdentificador === null) {
            return;
        }
        $this->identificador = $generatedIdentificador;
        $this->receptaculo = $this->buildReceptaculo();

        if ($this->editingId) {
            $saca = SacaModel::findOrFail($this->editingId);
            $saca->update($this->payload());
            session()->flash('success', 'Saca actualizada correctamente.');
        } else {
            SacaModel::create($this->payload());
            session()->flash('success', 'Saca creada correctamente.');
        }

        $this->dispatch('closeSacaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        DB::transaction(function () use ($id) {
            $saca = SacaModel::query()
                ->when($this->lockedDespachoId, function ($query) {
                    $query->where('fk_despacho', $this->lockedDespachoId);
                })
                ->lockForUpdate()
                ->findOrFail($id);

            $deletedSequence = (int) $saca->nro_saca;
            $deletedDespachoId = (int) $saca->fk_despacho;
            $saca->delete();

            $sacasToReorder = SacaModel::query()
                ->with('despacho:id,identificador')
                ->where('fk_despacho', $deletedDespachoId)
                ->whereRaw('CAST(nro_saca AS INTEGER) > ?', [$deletedSequence])
                ->orderByRaw('CAST(nro_saca AS INTEGER) ASC')
                ->lockForUpdate()
                ->get();

            foreach ($sacasToReorder as $item) {
                $nextSequence = (int) $item->nro_saca - 1;
                $item->nro_saca = str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
                $item->identificador = $this->buildIdentificadorForModel($item);
                $item->receptaculo = $this->buildReceptaculoForValues($item->identificador, $item->peso);
                $item->save();
            }
        });

        session()->flash('success', 'Saca eliminada correctamente.');
    }

    public function cerrarDespacho()
    {
        $despachoId = $this->getCurrentDespachoId();

        if (!$despachoId) {
            session()->flash('error', 'No hay despacho seleccionado para cerrar.');
            return;
        }

        $validationError = $this->getCerrarDespachoValidationError($despachoId);
        if ($validationError !== null) {
            session()->flash('error', $validationError);
            return;
        }

        try {
            DB::transaction(function () use ($despachoId) {
                $sacas = SacaModel::query()
                    ->where('fk_despacho', $despachoId)
                    ->lockForUpdate()
                    ->get(['fk_estado', 'paquetes', 'peso']);

                if ($sacas->isNotEmpty() && $sacas->every(fn($item) => (int) $item->fk_estado === 15)) {
                    throw new \RuntimeException('all_closed');
                }

                $totalPaquetes = (int) $sacas->sum(function ($item) {
                    return (int) ($item->paquetes ?? 0);
                });
                $totalPeso = round((float) $sacas->sum(function ($item) {
                    return (float) ($item->peso ?? 0);
                }), 3);

                SacaModel::query()
                    ->where('fk_despacho', $despachoId)
                    ->update(['fk_estado' => 15]);

                Despacho::query()
                    ->whereKey($despachoId)
                    ->update([
                        'fk_estado' => 14,
                        'nro_envase' => (string) $totalPaquetes,
                        'peso' => $totalPeso,
                    ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'all_closed') {
                throw $e;
            }
            session()->flash('error', 'No se puede cerrar: todas las sacas ya estan en estado 15.');
            return;
        }

        session()->flash('success', 'Despacho cerrado correctamente.');

        return redirect()->route('despachos.abiertos');
    }

    protected function getCurrentDespachoId()
    {
        return $this->lockedDespachoId ?: (int) $this->fk_despacho;
    }

    protected function canCerrarDespacho($despachoId)
    {
        return $this->getCerrarDespachoValidationError($despachoId) === null;
    }

    protected function getCerrarDespachoValidationError($despachoId)
    {
        if (!$despachoId) {
            return 'No hay despacho seleccionado para cerrar.';
        }

        $totalSacas = SacaModel::query()
            ->where('fk_despacho', $despachoId)
            ->count();

        if ($totalSacas === 0) {
            return null;
        }

        $sacasConDatosVacios = SacaModel::query()
            ->where('fk_despacho', $despachoId)
            ->where(function ($query) {
                $query->whereNull('peso')
                    ->orWhereNull('paquetes');
            })
            ->count();

        if ($sacasConDatosVacios > 0) {
            return 'No se puede cerrar: todas las sacas deben tener peso y paquetes.';
        }

        $sacasNoCerradas = SacaModel::query()
            ->where('fk_despacho', $despachoId)
            ->where(function ($query) {
                $query->whereNull('fk_estado')
                    ->orWhere('fk_estado', '!=', 15);
            })
            ->count();

        if ($sacasNoCerradas === 0) {
            return 'No se puede cerrar: todas las sacas ya estan en estado 15.';
        }

        return null;
    }

    public function resetForm()
    {
        $this->reset([
            'nro_saca',
            'identificador',
            'fk_estado',
            'peso',
            'paquetes',
            'busqueda',
            'receptaculo',
            'fk_despacho',
            'codEspecialSugerencias',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        $fkDespachoRule = 'required|integer|exists:despacho,id';

        if ($this->lockedDespachoId) {
            $fkDespachoRule .= '|in:' . $this->lockedDespachoId;
        }

        return [
            'nro_saca' => 'required|string|max:255',
            'identificador' => 'nullable|string|max:255',
            'fk_estado' => 'required|integer|exists:estados,id',
            'peso' => 'nullable|numeric|min:0.001',
            'paquetes' => 'nullable|integer|min:0',
            'busqueda' => 'nullable|string|max:255',
            'receptaculo' => 'nullable|string|max:255',
            'fk_despacho' => $fkDespachoRule,
        ];
    }

    protected function payload()
    {
        return [
            'nro_saca' => $this->nro_saca,
            'identificador' => $this->identificador,
            'fk_estado' => $this->fk_estado,
            'peso' => $this->normalizeNullable($this->peso),
            'paquetes' => $this->normalizeNullable($this->paquetes),
            'busqueda' => $this->normalizeNullable($this->busqueda),
            'receptaculo' => $this->normalizeNullable($this->receptaculo),
            'fk_despacho' => $this->fk_despacho,
        ];
    }

    protected function normalizeNullable($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    protected function cargarSugerenciasCodEspecial($term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            $this->codEspecialSugerencias = [];
            return;
        }

        $ems = PaqueteEms::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', 'ILIKE', '%' . $term . '%')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('cod_especial');

        $ordi = PaqueteOrdi::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', 'ILIKE', '%' . $term . '%')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('cod_especial');

        $this->codEspecialSugerencias = $ems
            ->merge($ordi)
            ->unique()
            ->values()
            ->take(8)
            ->all();
    }

    protected function normalizarBusquedaDesdeCodEspecial()
    {
        $busqueda = trim((string) $this->busqueda);
        if ($busqueda === '') {
            return true;
        }

        $paquetesEms = PaqueteEms::query()
            ->with('estado:id,nombre_estado')
            ->whereRaw('UPPER(cod_especial) = ?', [strtoupper($busqueda)])
            ->get(['id', 'cod_especial', 'estado_id']);

        $paquetesOrdi = PaqueteOrdi::query()
            ->with('estado:id,nombre_estado')
            ->whereRaw('UPPER(cod_especial) = ?', [strtoupper($busqueda)])
            ->get(['id', 'cod_especial', 'fk_estado']);

        if ($paquetesEms->isEmpty() && $paquetesOrdi->isEmpty()) {
            $this->addError('busqueda', 'El codigo no existe en paquetes_ems ni paquetes_ordi.');
            return false;
        }

        $paquetesEmsNoTransito = $paquetesEms->filter(function ($paquete) {
            $estadoNombre = strtoupper(trim((string) optional($paquete->estado)->nombre_estado));
            return $estadoNombre !== 'TRANSITO';
        });

        $paquetesOrdiNoTransito = $paquetesOrdi->filter(function ($paquete) {
            $estadoNombre = strtoupper(trim((string) optional($paquete->estado)->nombre_estado));
            return $estadoNombre !== 'TRANSITO';
        });

        if ($paquetesEmsNoTransito->isNotEmpty() || $paquetesOrdiNoTransito->isNotEmpty()) {
            $this->addError('busqueda', 'Todos los paquetes con ese cod_especial deben estar en estado TRANSITO.');
            return false;
        }

        $this->busqueda = (string) optional($paquetesEms->first())->cod_especial
            ?: (string) optional($paquetesOrdi->first())->cod_especial;

        return true;
    }

    protected function getNextNroSaca()
    {
        if (empty($this->fk_despacho)) {
            return '001';
        }

        $max = SacaModel::query()
            ->where('fk_despacho', $this->fk_despacho)
            ->max(DB::raw('CAST(nro_saca AS INTEGER)'));

        $next = (int) $max + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    protected function getEstadoAperturaId()
    {
        $exists16 = EstadoModel::query()->whereKey(16)->exists();
        if ($exists16) {
            return 16;
        }

        $aperturaId = EstadoModel::query()
            ->where('nombre_estado', 'APERTURA')
            ->value('id');

        return $aperturaId ?: null;
    }

    protected function buildIdentificadorFromDespacho()
    {
        $despachoIdentificador = Despacho::query()
            ->whereKey($this->fk_despacho)
            ->value('identificador');

        if (!$despachoIdentificador) {
            $this->addError('fk_despacho', 'No se encontro el despacho para generar el identificador.');
            return null;
        }

        return $despachoIdentificador . $this->nro_saca;
    }

    protected function getDefaultDespachoId()
    {
        return Despacho::query()->orderByDesc('id')->value('id');
    }

    protected function buildReceptaculo()
    {
        return $this->buildReceptaculoForValues($this->identificador, $this->peso);
    }

    protected function buildIdentificadorForModel(SacaModel $saca)
    {
        $despachoIdentificador = optional($saca->despacho)->identificador;

        return (string) $despachoIdentificador . $saca->nro_saca;
    }

    protected function buildReceptaculoForValues($identificador, $peso)
    {
        $pesoFormateado = $this->formatPesoForReceptaculo($peso);
        $base = (string) $identificador . $pesoFormateado;

        return preg_replace('/[^A-Za-z0-9]/', '', $base);
    }

    protected function formatPesoForReceptaculo($peso)
    {
        $normalized = $this->normalizeNullable($peso);
        if ($normalized === null) {
            return '';
        }

        $raw = str_replace(',', '.', (string) $normalized);
        $parts = explode('.', $raw, 2);

        $entero = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
        $decimal = preg_replace('/[^0-9]/', '', $parts[1] ?? '');
        $primerDecimal = $decimal !== '' ? substr($decimal, 0, 1) : '';

        $digits = $entero . $primerDecimal;

        if ($digits === '') {
            return '';
        }

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $sacasQuery = SacaModel::query()
            ->with('despacho:id,identificador,nro_despacho,anio')
            ->with('estado:id,nombre_estado');

        if ($this->lockedDespachoId) {
            $sacasQuery->where('fk_despacho', $this->lockedDespachoId);
        }

        if ($q !== '') {
            $sacasQuery->where(function ($query) use ($q) {
                $query->where('nro_saca', 'ILIKE', "%{$q}%")
                    ->orWhere('identificador', 'ILIKE', "%{$q}%")
                    ->orWhere('busqueda', 'ILIKE', "%{$q}%")
                    ->orWhere('receptaculo', 'ILIKE', "%{$q}%")
                    ->orWhereHas('estado', function ($estadoQuery) use ($q) {
                        $estadoQuery->where('nombre_estado', 'ILIKE', "%{$q}%");
                    });
            });
        }

        $sacas = $sacasQuery
            ->orderByDesc('id')
            ->paginate(10);

        $despachos = Despacho::query()
            ->orderByDesc('id')
            ->get(['id', 'identificador', 'nro_despacho', 'anio']);

        $currentDespachoId = $this->getCurrentDespachoId();
        $cerrarDespachoError = $this->getCerrarDespachoValidationError($currentDespachoId);
        $canCerrarDespacho = $cerrarDespachoError === null;

        return view('livewire.saca', [
            'sacas' => $sacas,
            'despachos' => $despachos,
            'canCerrarDespacho' => $canCerrarDespacho,
            'cerrarDespachoError' => $cerrarDespachoError,
        ]);
    }
}
