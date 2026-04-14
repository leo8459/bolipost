<?php

namespace App\Livewire;

use App\Models\Malencaminado;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteEmsFormulario;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Malencaminados extends Component
{
    use WithPagination;

    private const DESTINOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    private const ESTADO_MALENCAMINADO = 10;

    public string $search = '';
    public string $searchQuery = '';
    public string $codigoBuscado = '';
    public array $candidatos = [];
    public ?string $selectedTipo = null;
    public ?int $selectedEmsId = null;
    public ?int $selectedContratoId = null;
    public ?int $selectedCertiId = null;
    public ?int $selectedOrdiId = null;
    public string $selectedCodigo = '';
    public string $destinoActual = '';
    public string $destinoNuevo = '';
    public string $observacion = '';
    public int $editingId = 0;
    public string $editObservacion = '';

    protected $paginationTheme = 'bootstrap';

    public function searchRecords(): void
    {
        $this->searchQuery = trim($this->search);
        $this->resetPage('historialPage');
    }

    public function buscarCodigo(): void
    {
        $codigo = $this->normalizeCode($this->codigoBuscado);
        $this->candidatos = [];
        $this->resetPage('paquetesPage');

        if ($codigo === '') {
            return;
        }

        $candidatosEms = PaqueteEms::query()
            ->whereRaw('trim(upper(codigo)) like ?', ["%{$codigo}%"])
            ->orderByRaw("CASE WHEN trim(upper(codigo)) = ? THEN 0 ELSE 1 END", [$codigo])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'codigo', 'ciudad', 'estado_id'])
            ->map(fn (PaqueteEms $row) => [
                'tipo' => 'EMS',
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'destino' => (string) ($row->ciudad ?? ''),
                'estado' => (int) ($row->estado_id ?? 0),
            ])->all();

        $candidatosContrato = Recojo::query()
            ->whereRaw('trim(upper(codigo)) like ?', ["%{$codigo}%"])
            ->orderByRaw("CASE WHEN trim(upper(codigo)) = ? THEN 0 ELSE 1 END", [$codigo])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'codigo', 'destino', 'estados_id'])
            ->map(fn (Recojo $row) => [
                'tipo' => 'CONTRATO',
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'destino' => (string) ($row->destino ?? ''),
                'estado' => (int) ($row->estados_id ?? 0),
            ])->all();

        $candidatosCerti = PaqueteCerti::query()
            ->whereRaw('trim(upper(codigo)) like ?', ["%{$codigo}%"])
            ->orderByRaw("CASE WHEN trim(upper(codigo)) = ? THEN 0 ELSE 1 END", [$codigo])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'codigo', 'cuidad', 'fk_estado'])
            ->map(fn (PaqueteCerti $row) => [
                'tipo' => 'CERTI',
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'destino' => (string) ($row->cuidad ?? ''),
                'estado' => (int) ($row->fk_estado ?? 0),
            ])->all();

        $candidatosOrdi = PaqueteOrdi::query()
            ->whereRaw('trim(upper(codigo)) like ?', ["%{$codigo}%"])
            ->orderByRaw("CASE WHEN trim(upper(codigo)) = ? THEN 0 ELSE 1 END", [$codigo])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'codigo', 'ciudad', 'fk_estado'])
            ->map(fn (PaqueteOrdi $row) => [
                'tipo' => 'ORDI',
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'destino' => (string) ($row->ciudad ?? ''),
                'estado' => (int) ($row->fk_estado ?? 0),
            ])->all();

        $candidatos = collect(array_merge($candidatosEms, $candidatosContrato, $candidatosCerti, $candidatosOrdi))
            ->sort(function (array $a, array $b) use ($codigo) {
                $aExact = strtoupper(trim((string) $a['codigo'])) === $codigo ? 0 : 1;
                $bExact = strtoupper(trim((string) $b['codigo'])) === $codigo ? 0 : 1;
                if ($aExact !== $bExact) {
                    return $aExact <=> $bExact;
                }

                $typeCmp = strcmp((string) $a['tipo'], (string) $b['tipo']);
                if ($typeCmp !== 0) {
                    return $typeCmp;
                }

                return ((int) $b['id']) <=> ((int) $a['id']);
            })
            ->values()
            ->all();

        $this->candidatos = $candidatos;

        if (count($candidatos) === 1) {
            $this->seleccionarCandidato((string) $candidatos[0]['tipo'], (int) $candidatos[0]['id']);
            return;
        }

        $exacto = collect($candidatos)->first(fn (array $item) => strtoupper(trim((string) $item['codigo'])) === $codigo);
        if ($exacto) {
            $this->seleccionarCandidato((string) $exacto['tipo'], (int) $exacto['id']);
        }
    }

    public function seleccionarCandidato(string $tipo, int $id): void
    {
        if ($tipo === 'EMS') {
            $paquete = PaqueteEms::query()->findOrFail($id, ['id', 'codigo', 'ciudad']);
            $this->selectedTipo = 'EMS';
            $this->selectedEmsId = (int) $paquete->id;
            $this->selectedContratoId = null;
            $this->selectedCertiId = null;
            $this->selectedOrdiId = null;
            $this->selectedCodigo = (string) $paquete->codigo;
            $this->destinoActual = strtoupper(trim((string) ($paquete->ciudad ?? '')));
            $this->destinoNuevo = $this->destinoActual;
            return;
        }

        if ($tipo === 'CONTRATO') {
            $contrato = Recojo::query()->findOrFail($id, ['id', 'codigo', 'destino']);
            $this->selectedTipo = 'CONTRATO';
            $this->selectedEmsId = null;
            $this->selectedContratoId = (int) $contrato->id;
            $this->selectedCertiId = null;
            $this->selectedOrdiId = null;
            $this->selectedCodigo = (string) $contrato->codigo;
            $this->destinoActual = strtoupper(trim((string) ($contrato->destino ?? '')));
            $this->destinoNuevo = $this->destinoActual;
            return;
        }

        if ($tipo === 'CERTI') {
            $certi = PaqueteCerti::query()->findOrFail($id, ['id', 'codigo', 'cuidad']);
            $this->selectedTipo = 'CERTI';
            $this->selectedEmsId = null;
            $this->selectedContratoId = null;
            $this->selectedCertiId = (int) $certi->id;
            $this->selectedOrdiId = null;
            $this->selectedCodigo = (string) $certi->codigo;
            $this->destinoActual = strtoupper(trim((string) ($certi->cuidad ?? '')));
            $this->destinoNuevo = $this->destinoActual;
            return;
        }

        $ordi = PaqueteOrdi::query()->findOrFail($id, ['id', 'codigo', 'ciudad']);
        $this->selectedTipo = 'ORDI';
        $this->selectedEmsId = null;
        $this->selectedContratoId = null;
        $this->selectedCertiId = null;
        $this->selectedOrdiId = (int) $ordi->id;
        $this->selectedCodigo = (string) $ordi->codigo;
        $this->destinoActual = strtoupper(trim((string) ($ordi->ciudad ?? '')));
        $this->destinoNuevo = $this->destinoActual;
    }

    public function guardarMalencaminado(): void
    {
        $this->validate([
            'selectedTipo' => ['required', Rule::in(['EMS', 'CONTRATO', 'CERTI', 'ORDI'])],
            'selectedCodigo' => ['required', 'string', 'max:255'],
            'destinoNuevo' => ['required', 'string', Rule::in(self::DESTINOS)],
            'observacion' => ['required', 'string', 'max:1000'],
        ]);

        $tipo = (string) $this->selectedTipo;
        $nuevoDestino = strtoupper(trim($this->destinoNuevo));
        $departamentoActor = strtoupper(trim((string) (Auth::user()->ciudad ?? '')));
        if ($departamentoActor === '') {
            $departamentoActor = 'SIN ORIGEN';
        }

        DB::transaction(function () use ($tipo, $nuevoDestino, $departamentoActor) {
            if ($tipo === 'EMS') {
                $paquete = PaqueteEms::query()->lockForUpdate()->findOrFail((int) $this->selectedEmsId);
                $destinoAnterior = strtoupper(trim((string) ($paquete->ciudad ?? '')));
                $departamentoOrigen = strtoupper(trim((string) ($paquete->origen ?? '')));

                $paquete->ciudad = $nuevoDestino;
                $paquete->estado_id = self::ESTADO_MALENCAMINADO;
                $paquete->save();

                PaqueteEmsFormulario::query()
                    ->where('paquete_ems_id', (int) $paquete->id)
                    ->update(['ciudad' => $nuevoDestino]);

                $contador = (int) Malencaminado::query()->where('paquetes_ems_id', (int) $paquete->id)->count() + 1;

                Malencaminado::query()->create([
                    'codigo' => (string) $paquete->codigo,
                    'departamento_origen' => $departamentoOrigen !== '' ? $departamentoOrigen : $departamentoActor,
                    'observacion' => trim((string) $this->observacion),
                    'malencaminamiento' => $contador,
                    'paquetes_ems_id' => (int) $paquete->id,
                    'paquetes_contrato_id' => null,
                    'paquetes_certi_id' => null,
                    'paquetes_ordi_id' => null,
                    'destino_anterior' => $destinoAnterior,
                    'destino_nuevo' => $nuevoDestino,
                ]);

                return;
            }

            if ($tipo === 'CONTRATO') {
                $contrato = Recojo::query()->lockForUpdate()->findOrFail((int) $this->selectedContratoId);
                $destinoAnterior = strtoupper(trim((string) ($contrato->destino ?? '')));
                $departamentoOrigen = strtoupper(trim((string) ($contrato->origen ?? '')));

                $contrato->destino = $nuevoDestino;
                $contrato->estados_id = self::ESTADO_MALENCAMINADO;
                $contrato->save();

                $contador = (int) Malencaminado::query()->where('paquetes_contrato_id', (int) $contrato->id)->count() + 1;

                Malencaminado::query()->create([
                    'codigo' => (string) $contrato->codigo,
                    'departamento_origen' => $departamentoOrigen !== '' ? $departamentoOrigen : $departamentoActor,
                    'observacion' => trim((string) $this->observacion),
                    'malencaminamiento' => $contador,
                    'paquetes_ems_id' => null,
                    'paquetes_contrato_id' => (int) $contrato->id,
                    'paquetes_certi_id' => null,
                    'paquetes_ordi_id' => null,
                    'destino_anterior' => $destinoAnterior,
                    'destino_nuevo' => $nuevoDestino,
                ]);

                return;
            }

            if ($tipo === 'CERTI') {
                $certi = PaqueteCerti::query()->lockForUpdate()->findOrFail((int) $this->selectedCertiId);
                $destinoAnterior = strtoupper(trim((string) ($certi->cuidad ?? '')));

                $certi->cuidad = $nuevoDestino;
                $certi->fk_estado = self::ESTADO_MALENCAMINADO;
                $certi->save();

                $contador = (int) Malencaminado::query()->where('paquetes_certi_id', (int) $certi->id)->count() + 1;

                Malencaminado::query()->create([
                    'codigo' => (string) $certi->codigo,
                    'departamento_origen' => $departamentoActor,
                    'observacion' => trim((string) $this->observacion),
                    'malencaminamiento' => $contador,
                    'paquetes_ems_id' => null,
                    'paquetes_contrato_id' => null,
                    'paquetes_certi_id' => (int) $certi->id,
                    'paquetes_ordi_id' => null,
                    'destino_anterior' => $destinoAnterior,
                    'destino_nuevo' => $nuevoDestino,
                ]);

                return;
            }

            $ordi = PaqueteOrdi::query()->lockForUpdate()->findOrFail((int) $this->selectedOrdiId);
            $destinoAnterior = strtoupper(trim((string) ($ordi->ciudad ?? '')));

            $ordi->ciudad = $nuevoDestino;
            $ordi->fk_estado = self::ESTADO_MALENCAMINADO;
            $ordi->save();

            $contador = (int) Malencaminado::query()->where('paquetes_ordi_id', (int) $ordi->id)->count() + 1;

            Malencaminado::query()->create([
                'codigo' => (string) $ordi->codigo,
                'departamento_origen' => $departamentoActor,
                'observacion' => trim((string) $this->observacion),
                'malencaminamiento' => $contador,
                'paquetes_ems_id' => null,
                'paquetes_contrato_id' => null,
                'paquetes_certi_id' => null,
                'paquetes_ordi_id' => (int) $ordi->id,
                'destino_anterior' => $destinoAnterior,
                'destino_nuevo' => $nuevoDestino,
            ]);
        });

        session()->flash('success', 'Registro de malencaminado guardado. Se actualizo el destino y estado a 10.');
        $this->resetCreateForm();
    }

    public function openEditModal(int $id): void
    {
        $row = Malencaminado::query()->findOrFail($id);
        $this->editingId = (int) $row->id;
        $this->editObservacion = (string) ($row->observacion ?? '');
        $this->dispatch('openMalencaminadoEditModal');
    }

    public function updateRecord(): void
    {
        $this->validate(['editObservacion' => ['required', 'string', 'max:1000']]);

        $row = Malencaminado::query()->findOrFail((int) $this->editingId);
        $row->update(['observacion' => trim((string) $this->editObservacion)]);

        $this->dispatch('closeMalencaminadoEditModal');
        $this->editingId = 0;
        $this->editObservacion = '';
        session()->flash('success', 'Registro actualizado correctamente.');
    }

    public function delete(int $id): void
    {
        $row = Malencaminado::query()->findOrFail($id);
        $row->delete();
        session()->flash('success', 'Registro eliminado correctamente.');
    }

    public function render()
    {
        $q = trim($this->searchQuery);
        $codigoFiltro = $this->normalizeCode($this->codigoBuscado);

        $paquetesDisponibles = DB::query()
            ->fromSub($this->buildPaquetesUnificadosQuery(), 'p')
            ->when($codigoFiltro !== '', function ($query) use ($codigoFiltro) {
                $query->whereRaw('trim(upper(codigo)) like ?', ["%{$codigoFiltro}%"]);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('source_id')
            ->paginate(15, ['*'], 'paquetesPage');

        $records = Malencaminado::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('observacion', 'ILIKE', "%{$q}%")
                        ->orWhere('destino_anterior', 'ILIKE', "%{$q}%")
                        ->orWhere('destino_nuevo', 'ILIKE', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'historialPage');

        return view('livewire.malencaminados', [
            'records' => $records,
            'paquetesDisponibles' => $paquetesDisponibles,
            'destinos' => self::DESTINOS,
        ]);
    }

    private function buildPaquetesUnificadosQuery(): Builder
    {
        $ems = DB::table('paquetes_ems')
            ->selectRaw("'EMS' as tipo")
            ->selectRaw('id as source_id')
            ->selectRaw('codigo')
            ->selectRaw("coalesce(ciudad, '-') as destino")
            ->selectRaw('coalesce(estado_id, 0) as estado')
            ->selectRaw('created_at');

        $contratos = DB::table('paquetes_contrato')
            ->selectRaw("'CONTRATO' as tipo")
            ->selectRaw('id as source_id')
            ->selectRaw('codigo')
            ->selectRaw("coalesce(destino, '-') as destino")
            ->selectRaw('coalesce(estados_id, 0) as estado')
            ->selectRaw('created_at');

        $certi = DB::table('paquetes_certi')
            ->selectRaw("'CERTI' as tipo")
            ->selectRaw('id as source_id')
            ->selectRaw('codigo')
            ->selectRaw("coalesce(cuidad, '-') as destino")
            ->selectRaw('coalesce(fk_estado, 0) as estado')
            ->selectRaw('created_at');

        $ordi = DB::table('paquetes_ordi')
            ->selectRaw("'ORDI' as tipo")
            ->selectRaw('id as source_id')
            ->selectRaw('codigo')
            ->selectRaw("coalesce(ciudad, '-') as destino")
            ->selectRaw('coalesce(fk_estado, 0) as estado')
            ->selectRaw('created_at');

        return $ems->unionAll($contratos)->unionAll($certi)->unionAll($ordi);
    }

    private function normalizeCode(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', '', $value) ?? $value;
        return trim($value);
    }

    private function resetCreateForm(): void
    {
        $this->codigoBuscado = '';
        $this->candidatos = [];
        $this->selectedTipo = null;
        $this->selectedEmsId = null;
        $this->selectedContratoId = null;
        $this->selectedCertiId = null;
        $this->selectedOrdiId = null;
        $this->selectedCodigo = '';
        $this->destinoActual = '';
        $this->destinoNuevo = '';
        $this->observacion = '';
        $this->resetValidation(['selectedTipo', 'selectedCodigo', 'destinoNuevo', 'observacion']);
    }
}
