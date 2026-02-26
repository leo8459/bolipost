<?php

namespace App\Livewire;

use App\Models\Despacho as DespachoModel;
use App\Models\Estado as EstadoModel;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Saca as SacaModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class DespachoAdmitido extends Component
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
    public $receptaculosInput = '';
    public $receptaculoScanInput = '';
    public $previewSacas = [];
    public $previewDespachoIds = [];
    public $receptaculosNoEncontrados = [];
    public $receptaculosEscaneados = [];
    public $receptaculosResultado = [];
    public $receptaculosEscaneadosCount = 0;
    public $receptaculosEncontradosCount = 0;

    protected $paginationTheme = 'bootstrap';

    public function searchDespachos()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openAdmitirModal()
    {
        $this->resetAdmitirForm();
        $this->dispatch('openAdmitirDespachoModal');
    }

    public function enqueueReceptaculo()
    {
        $codigo = $this->normalizeReceptaculo($this->receptaculoScanInput);
        $this->receptaculoScanInput = '';

        if ($codigo === '') {
            return;
        }

        if (in_array($codigo, $this->receptaculosEscaneados, true)) {
            return;
        }

        $this->receptaculosEscaneados[] = $codigo;
        $this->receptaculosEscaneadosCount = count($this->receptaculosEscaneados);
    }

    public function scanAndSearch()
    {
        $this->enqueueReceptaculo();
        $this->previewAdmitir();
    }

    public function removeScanned($codigo)
    {
        $codigo = $this->normalizeReceptaculo($codigo);
        $this->receptaculosEscaneados = collect($this->receptaculosEscaneados)
            ->reject(fn ($item) => $item === $codigo)
            ->values()
            ->all();

        $this->receptaculosEscaneadosCount = count($this->receptaculosEscaneados);

        if ($this->receptaculosEscaneadosCount === 0) {
            $this->previewSacas = [];
            $this->previewDespachoIds = [];
            $this->receptaculosNoEncontrados = [];
            $this->receptaculosResultado = [];
            $this->receptaculosEncontradosCount = 0;
            $this->resetValidation();
            return;
        }

        $this->previewAdmitir();
    }

    public function previewAdmitir()
    {
        if (!empty(trim((string) $this->receptaculoScanInput))) {
            $this->enqueueReceptaculo();
        }

        $receptaculosEscaneados = collect($this->receptaculosEscaneados);
        $this->receptaculosEscaneados = $receptaculosEscaneados->values()->all();
        $this->receptaculosEscaneadosCount = $receptaculosEscaneados->count();

        // Query with unique values, but keep full scanned list for UI.
        $receptaculos = $receptaculosEscaneados->unique()->values();

        if ($receptaculos->isEmpty()) {
            $this->addError('receptaculosInput', 'Ingresa al menos un receptaculo.');
            $this->previewSacas = [];
            $this->previewDespachoIds = [];
            $this->receptaculosNoEncontrados = [];
            $this->receptaculosEscaneados = [];
            $this->receptaculosResultado = [];
            $this->receptaculosEncontradosCount = 0;
            return;
        }

        $normalizedReceptaculoSql = "REGEXP_REPLACE(UPPER(COALESCE(receptaculo, '')), '[^A-Z0-9]', '', 'g')";

        $sacasCandidatas = SacaModel::query()
            ->with('despacho:id,identificador,nro_despacho,anio,fk_estado')
            ->whereHas('despacho', function ($query) {
                $query->where('ofdestino', $this->getOfdestinoFromUser());
            })
            ->whereIn(DB::raw($normalizedReceptaculoSql), $receptaculos->all())
            ->select('*')
            ->selectRaw($normalizedReceptaculoSql . ' as receptaculo_normalizado')
            ->get();

        $sacas = $sacasCandidatas
            ->filter(function ($saca) {
                $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
                return (int) $saca->fk_estado === 15
                    && (int) optional($saca->despacho)->fk_estado === $estadoExpedicionId;
            })
            ->values();

        $this->previewSacas = $sacas->map(function ($saca) {
            return [
                'id' => $saca->id,
                'receptaculo' => $saca->receptaculo,
                'identificador' => $saca->identificador,
                'busqueda' => $saca->busqueda,
                'fk_despacho' => $saca->fk_despacho,
                'despacho' => optional($saca->despacho)->identificador,
            ];
        })->values()->all();

        $this->previewDespachoIds = collect($this->previewSacas)
            ->pluck('fk_despacho')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $mostrados = $receptaculosEscaneados;

        $sacasMostradas = SacaModel::query()
            ->with('despacho:id,fk_estado')
            ->whereHas('despacho', function ($query) {
                $query->where('ofdestino', $this->getOfdestinoFromUser());
            })
            ->whereIn(DB::raw($normalizedReceptaculoSql), $mostrados->all())
            ->select('*')
            ->selectRaw($normalizedReceptaculoSql . ' as receptaculo_normalizado')
            ->get();

        $validasMostradas = $sacasMostradas
            ->filter(function ($saca) {
                $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
                return (int) $saca->fk_estado === 15
                    && (int) optional($saca->despacho)->fk_estado === $estadoExpedicionId;
            })
            ->values();

        $encontrados = $validasMostradas->pluck('receptaculo_normalizado')
            ->filter()
            ->unique()
            ->values();
        $this->receptaculosEncontradosCount = $encontrados->count();

        $this->receptaculosResultado = $mostrados->map(function ($codigo) use ($sacasMostradas, $validasMostradas) {
            $validas = $validasMostradas->where('receptaculo_normalizado', $codigo);
            if ($validas->isNotEmpty()) {
                return [
                    'codigo' => $codigo,
                    'ok' => true,
                    'detalle' => 'Valido para recibir',
                ];
            }

            $candidatas = $sacasMostradas->where('receptaculo_normalizado', $codigo);
            if ($candidatas->isEmpty()) {
                return [
                    'codigo' => $codigo,
                    'ok' => false,
                    'detalle' => 'No encontrado',
                ];
            }

            $primera = $candidatas->first();
            $estadoSaca = (int) $primera->fk_estado;
            $estadoDespacho = (int) optional($primera->despacho)->fk_estado;

            return [
                'codigo' => $codigo,
                'ok' => false,
                'detalle' => "No valido: saca {$estadoSaca}, despacho {$estadoDespacho}",
            ];
        })->values()->all();

        $this->receptaculosNoEncontrados = collect($this->receptaculosResultado)
            ->filter(fn ($item) => !$item['ok'])
            ->pluck('codigo')
            ->unique()
            ->values()
            ->all();
    }

    public function admitirDespachos()
    {
        if (empty($this->previewSacas)) {
            $this->previewAdmitir();
        }

        $sacaIds = collect($this->previewSacas)->pluck('id')->filter()->values();
        $despachoIds = collect($this->previewDespachoIds)->filter()->values();
        $codEspeciales = collect($this->previewSacas)
            ->pluck('busqueda')
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->unique()
            ->values();

        if ($sacaIds->isEmpty() || $despachoIds->isEmpty()) {
            $this->addError('receptaculosInput', 'No hay sacas validas para admitir.');
            return;
        }

        $despachosActualizados = collect();
        $estadoExpedicionId = $this->getEstadoIdByNombre('EXPEDICION', 19);
        $estadoIncorporacionId = $this->getEstadoIdByNombre('INCORPORACION', 21);
        $estadoEnviadoId = (int) (EstadoModel::query()
            ->whereRaw('TRIM(UPPER(nombre_estado)) = ?', ['ENVIADO'])
            ->value('id') ?? 0);

        if ($estadoEnviadoId <= 0) {
            $this->addError('receptaculosInput', 'No existe el estado ENVIADO en la tabla estados.');
            return;
        }

        DB::transaction(function () use ($sacaIds, $despachoIds, $codEspeciales, $estadoExpedicionId, $estadoIncorporacionId, $estadoEnviadoId, &$despachosActualizados) {
            SacaModel::query()
                ->whereIn('id', $sacaIds->all())
                ->where('fk_estado', 15)
                ->whereHas('despacho', function ($query) use ($estadoExpedicionId) {
                    $query->where('fk_estado', $estadoExpedicionId);
                })
                ->update(['fk_estado' => 22]);

            if ($codEspeciales->isNotEmpty()) {
                $paqueteIds = PaqueteEms::query()
                    ->whereNotNull('cod_especial')
                    ->where(function ($query) use ($codEspeciales) {
                        foreach ($codEspeciales as $codigo) {
                            $query->orWhereRaw('TRIM(UPPER(cod_especial)) = ?', [$codigo]);
                        }
                    })
                    ->pluck('id');

                if ($paqueteIds->isNotEmpty()) {
                    PaqueteEms::query()
                        ->whereIn('id', $paqueteIds->all())
                        ->update(['estado_id' => $estadoEnviadoId]);
                }

                $paqueteOrdiIds = PaqueteOrdi::query()
                    ->whereNotNull('cod_especial')
                    ->where(function ($query) use ($codEspeciales) {
                        foreach ($codEspeciales as $codigo) {
                            $query->orWhereRaw('TRIM(UPPER(cod_especial)) = ?', [$codigo]);
                        }
                    })
                    ->pluck('id');

                if ($paqueteOrdiIds->isNotEmpty()) {
                    PaqueteOrdi::query()
                        ->whereIn('id', $paqueteOrdiIds->all())
                        ->update(['fk_estado' => $estadoEnviadoId]);
                }
            }

            $despachosCompletos = DespachoModel::query()
                ->whereIn('id', $despachoIds->all())
                ->where('fk_estado', $estadoExpedicionId)
                ->whereDoesntHave('sacas', function ($query) {
                    $query->where('fk_estado', '!=', 22);
                })
                ->pluck('id');

            if ($despachosCompletos->isNotEmpty()) {
                DespachoModel::query()
                    ->whereIn('id', $despachosCompletos->all())
                    ->update(['fk_estado' => $estadoIncorporacionId]);
            }

            $despachosActualizados = $despachosCompletos;
        });

        $this->dispatch('closeAdmitirDespachoModal');
        $this->resetAdmitirForm();
        if ($despachosActualizados->isEmpty()) {
            session()->flash('success', 'Sacas recibidas. Ningun despacho completo para cambiar a estado 21.');
        } else {
            session()->flash('success', 'Sacas recibidas y despachos completos cambiados a estado 21.');
        }
    }

    public function resetAdmitirForm()
    {
        $this->reset([
            'receptaculosInput',
            'receptaculoScanInput',
            'previewSacas',
            'previewDespachoIds',
            'receptaculosNoEncontrados',
            'receptaculosEscaneados',
            'receptaculosResultado',
            'receptaculosEscaneadosCount',
            'receptaculosEncontradosCount',
        ]);

        $this->resetValidation();
    }

    protected function parseReceptaculos($raw)
    {
        // One-by-one scan mode: each code must come in a new line (Enter).
        return collect(preg_split('/\r\n|\r|\n/', strtoupper((string) $raw)))
            ->map(fn ($item) => $this->normalizeReceptaculo($item))
            ->filter(fn ($item) => $item !== '')
            ->values();
    }

    protected function normalizeReceptaculo($value)
    {
        $normalized = strtoupper(trim((string) $value));

        return preg_replace('/[^A-Z0-9]/', '', $normalized);
    }

    protected function getOfdestinoFromUser()
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

    public function render()
    {
        $q = trim($this->searchQuery);
        $userOfdestino = $this->getOfdestinoFromUser();

        $despachos = DespachoModel::query()
            ->with('estado:id,nombre_estado')
            ->whereHas('estado', function ($query) {
                $query->whereIn('nombre_estado', ['EXPEDICION', 'INCORPORACION']);
            })
            ->when($userOfdestino !== '', function ($query) use ($userOfdestino) {
                $query->where('ofdestino', $userOfdestino);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->withCount([
                'sacas as sacas_totales',
                'sacas as sacas_recibidas' => function ($query) {
                    $query->where('fk_estado', 22);
                },
            ])
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

        return view('livewire.despacho-admitido', [
            'despachos' => $despachos,
        ]);
    }
}
