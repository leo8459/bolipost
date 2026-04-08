<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndicadorController extends Controller
{
    private const EVENTO_CONTRATO_ENTREGADO_ID = 316;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const EVENTO_EMS_ENTREGADO_ID = 316;
    private const EVENTO_CERTI_ENTREGADO_ID = 316;
    private const EVENTO_ORDI_ENTREGADO_ID = 316;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const CERTI_ORDI_RED_DAYS = 30;
    private const DESTINOS_LARGA_DISTANCIA = [
        'SANTA CRUZ',
        'TRINIDAD',
        'TARIJA',
    ];
    private const DESTINOS_BASE = [
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
    private const DESTINOS_CAPITALES = [
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

    public function contratosEntregados(Request $request)
    {
        return $this->buildContratos($request, true);
    }

    public function contratosInventario(Request $request)
    {
        return $this->buildContratos($request, false);
    }

    public function emsEntregados(Request $request)
    {
        return $this->buildEms($request, true);
    }

    public function emsInventario(Request $request)
    {
        return $this->buildEms($request, false);
    }

    public function certificadosEntregados(Request $request)
    {
        return $this->buildCertificados($request, true);
    }

    public function certificadosInventario(Request $request)
    {
        return $this->buildCertificados($request, false);
    }

    public function ordinariosEntregados(Request $request)
    {
        return $this->buildOrdinarios($request, true);
    }

    public function ordinariosInventario(Request $request)
    {
        return $this->buildOrdinarios($request, false);
    }

    private function buildContratos(Request $request, bool $entregados)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = $this->getEstadoEntregadoId();
        $entregadoSub = DB::table('eventos_contrato')
            ->select('codigo', DB::raw('MIN(created_at) as entregado_evento_at'))
            ->where('evento_id', self::EVENTO_CONTRATO_ENTREGADO_ID)
            ->groupBy('codigo');

        $rows = DB::table('paquetes_contrato')
            ->leftJoin('estados', 'estados.id', '=', 'paquetes_contrato.estados_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'paquetes_contrato.empresa_id')
            ->leftJoin('users', 'users.id', '=', 'paquetes_contrato.user_id')
            ->leftJoinSub($entregadoSub, 'ev_entregado', function ($join) {
                $join->on('ev_entregado.codigo', '=', 'paquetes_contrato.codigo');
            })
            ->select([
                'paquetes_contrato.codigo',
                DB::raw("coalesce(estados.nombre_estado, '-') as estado"),
                DB::raw("coalesce(paquetes_contrato.origen, '-') as origen"),
                DB::raw("coalesce(paquetes_contrato.destino, '-') as destino"),
                DB::raw("coalesce(paquetes_contrato.provincia, '') as provincia"),
                DB::raw("coalesce(paquetes_contrato.nombre_r, '-') as remitente"),
                DB::raw("coalesce(paquetes_contrato.nombre_d, '-') as destinatario"),
                DB::raw('coalesce(paquetes_contrato.peso, 0) as peso'),
                DB::raw("coalesce(empresa.nombre, '-') as empresa"),
                DB::raw("coalesce(users.name, '-') as usuario"),
                'paquetes_contrato.created_at as fecha_registro',
                'paquetes_contrato.updated_at as fecha_actualizacion',
                'paquetes_contrato.fecha_recojo as fecha_recojo',
                'ev_entregado.entregado_evento_at',
            ]);

        $this->applyEstadoScope($rows, 'paquetes_contrato.estados_id', $estadoEntregadoId, $entregados);

        if ($search !== '') {
            $rows->where(function (Builder $query) use ($search) {
                $query->where('paquetes_contrato.codigo', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.cod_especial', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.origen', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.destino', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.provincia', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.nombre_r', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_contrato.nombre_d', 'like', '%' . $search . '%')
                    ->orWhere('estados.nombre_estado', 'like', '%' . $search . '%')
                    ->orWhere('empresa.nombre', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%');
            });
        }

        $slaResumen = $this->buildSlaResumen((clone $rows), [$this, 'decorateContratoSlaRow'], $entregados);
        $rows = $rows->orderByDesc('paquetes_contrato.id')->paginate(20)->withQueryString();
        $rows->through(function ($row) use ($entregados) {
            return $this->decorateContratoSlaRow($row, $entregados);
        });

        return $this->renderListado(
            $rows,
            'Indicador Contratos - ' . ($entregados ? 'Entregados' : 'Inventario'),
            'CONTRATOS',
            $entregados ? 'ENTREGADOS' : 'INVENTARIO (NO ENTREGADOS)',
            $search,
            $entregados ? 'indicadores.contratos.entregados' : 'indicadores.contratos.inventario',
            (bool) $estadoEntregadoId,
            $entregados,
            true,
            $slaResumen
        );
    }

    private function buildEms(Request $request, bool $entregados)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = $this->getEstadoEntregadoId();
        $solicitudSub = DB::table('eventos_ems')
            ->select('codigo', DB::raw('MIN(created_at) as solicitud_at'))
            ->where('evento_id', self::EVENTO_EMS_SOLICITUD_ID)
            ->groupBy('codigo');
        $entregadoSub = DB::table('eventos_ems')
            ->select('codigo', DB::raw('MIN(created_at) as entregado_evento_at'))
            ->where('evento_id', self::EVENTO_EMS_ENTREGADO_ID)
            ->groupBy('codigo');

        $rows = DB::table('paquetes_ems')
            ->leftJoin('estados', 'estados.id', '=', 'paquetes_ems.estado_id')
            ->leftJoin('users', 'users.id', '=', 'paquetes_ems.user_id')
            ->leftJoinSub($solicitudSub, 'ev_solicitud', function ($join) {
                $join->on('ev_solicitud.codigo', '=', 'paquetes_ems.codigo');
            })
            ->leftJoinSub($entregadoSub, 'ev_entregado', function ($join) {
                $join->on('ev_entregado.codigo', '=', 'paquetes_ems.codigo');
            })
            ->select([
                'paquetes_ems.codigo',
                DB::raw("coalesce(estados.nombre_estado, '-') as estado"),
                DB::raw("coalesce(paquetes_ems.origen, '-') as origen"),
                DB::raw("coalesce(paquetes_ems.ciudad, '-') as destino"),
                DB::raw("coalesce(paquetes_ems.nombre_remitente, '-') as remitente"),
                DB::raw("coalesce(paquetes_ems.nombre_destinatario, '-') as destinatario"),
                DB::raw('coalesce(paquetes_ems.peso, 0) as peso'),
                DB::raw("'-' as empresa"),
                DB::raw("coalesce(users.name, '-') as usuario"),
                'paquetes_ems.created_at as fecha_registro',
                'paquetes_ems.updated_at as fecha_actualizacion',
                'ev_solicitud.solicitud_at',
                'ev_entregado.entregado_evento_at',
            ]);

        $this->applyEstadoScope($rows, 'paquetes_ems.estado_id', $estadoEntregadoId, $entregados);

        if ($search !== '') {
            $rows->where(function (Builder $query) use ($search) {
                $query->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ems.cod_especial', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ems.origen', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ems.nombre_remitente', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                    ->orWhere('estados.nombre_estado', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%');
            });
        }

        $slaResumen = $this->buildSlaResumen((clone $rows), [$this, 'decorateEmsSlaRow'], $entregados);
        $rows = $rows->orderByDesc('paquetes_ems.id')->paginate(20)->withQueryString();
        $rows->through(function ($row) use ($entregados) {
            return $this->decorateEmsSlaRow($row, $entregados);
        });

        return $this->renderListado(
            $rows,
            'Indicador EMS - ' . ($entregados ? 'Entregados' : 'Inventario'),
            'EMS',
            $entregados ? 'ENTREGADOS' : 'INVENTARIO (NO ENTREGADOS)',
            $search,
            $entregados ? 'indicadores.ems.entregados' : 'indicadores.ems.inventario',
            (bool) $estadoEntregadoId,
            $entregados,
            true,
            $slaResumen
        );
    }

    private function buildCertificados(Request $request, bool $entregados)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = $this->getEstadoEntregadoId();
        $inicioSub = DB::table('eventos_certi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'))
            ->groupBy('codigo');
        $entregadoSub = DB::table('eventos_certi')
            ->select('codigo', DB::raw('MIN(created_at) as entregado_evento_at'))
            ->where('evento_id', self::EVENTO_CERTI_ENTREGADO_ID)
            ->groupBy('codigo');

        $rows = DB::table('paquetes_certi')
            ->leftJoin('estados', 'estados.id', '=', 'paquetes_certi.fk_estado')
            ->leftJoinSub($inicioSub, 'ev_inicio', function ($join) {
                $join->on('ev_inicio.codigo', '=', 'paquetes_certi.codigo');
            })
            ->leftJoinSub($entregadoSub, 'ev_entregado', function ($join) {
                $join->on('ev_entregado.codigo', '=', 'paquetes_certi.codigo');
            })
            ->select([
                'paquetes_certi.codigo',
                DB::raw("coalesce(estados.nombre_estado, '-') as estado"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(paquetes_certi.cuidad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(paquetes_certi.destinatario, '-') as destinatario"),
                DB::raw('coalesce(paquetes_certi.peso, 0) as peso'),
                DB::raw("'-' as empresa"),
                DB::raw("'-' as usuario"),
                'paquetes_certi.created_at as fecha_registro',
                'paquetes_certi.updated_at as fecha_actualizacion',
                'ev_inicio.primer_evento_at',
                'ev_entregado.entregado_evento_at',
            ]);

        $this->applyEstadoScope($rows, 'paquetes_certi.fk_estado', $estadoEntregadoId, $entregados);

        if ($search !== '') {
            $rows->where(function (Builder $query) use ($search) {
                $query->where('paquetes_certi.codigo', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_certi.cuidad', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_certi.destinatario', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_certi.telefono', 'like', '%' . $search . '%')
                    ->orWhere('estados.nombre_estado', 'like', '%' . $search . '%');
            });
        }

        $slaResumen = $this->buildSlaResumen((clone $rows), [$this, 'decorateCertiOrdiSlaRow'], $entregados);
        $rows = $rows->orderByDesc('paquetes_certi.id')->paginate(20)->withQueryString();
        $rows->through(function ($row) use ($entregados) {
            return $this->decorateCertiOrdiSlaRow($row, $entregados);
        });

        return $this->renderListado(
            $rows,
            'Indicador Certificados - ' . ($entregados ? 'Entregados' : 'Inventario'),
            'CERTIFICADOS',
            $entregados ? 'ENTREGADOS' : 'INVENTARIO (NO ENTREGADOS)',
            $search,
            $entregados ? 'indicadores.certificados.entregados' : 'indicadores.certificados.inventario',
            (bool) $estadoEntregadoId,
            $entregados,
            true,
            $slaResumen
        );
    }

    private function buildOrdinarios(Request $request, bool $entregados)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = $this->getEstadoEntregadoId();
        $inicioSub = DB::table('eventos_ordi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'))
            ->groupBy('codigo');
        $entregadoSub = DB::table('eventos_ordi')
            ->select('codigo', DB::raw('MIN(created_at) as entregado_evento_at'))
            ->where('evento_id', self::EVENTO_ORDI_ENTREGADO_ID)
            ->groupBy('codigo');

        $rows = DB::table('paquetes_ordi')
            ->leftJoin('estados', 'estados.id', '=', 'paquetes_ordi.fk_estado')
            ->leftJoinSub($inicioSub, 'ev_inicio', function ($join) {
                $join->on('ev_inicio.codigo', '=', 'paquetes_ordi.codigo');
            })
            ->leftJoinSub($entregadoSub, 'ev_entregado', function ($join) {
                $join->on('ev_entregado.codigo', '=', 'paquetes_ordi.codigo');
            })
            ->select([
                'paquetes_ordi.codigo',
                DB::raw("coalesce(estados.nombre_estado, '-') as estado"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(paquetes_ordi.ciudad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(paquetes_ordi.destinatario, '-') as destinatario"),
                DB::raw('coalesce(paquetes_ordi.peso, 0) as peso'),
                DB::raw("'-' as empresa"),
                DB::raw("'-' as usuario"),
                'paquetes_ordi.created_at as fecha_registro',
                'paquetes_ordi.updated_at as fecha_actualizacion',
                'ev_inicio.primer_evento_at',
                'ev_entregado.entregado_evento_at',
            ]);

        $this->applyEstadoScope($rows, 'paquetes_ordi.fk_estado', $estadoEntregadoId, $entregados);

        if ($search !== '') {
            $rows->where(function (Builder $query) use ($search) {
                $query->where('paquetes_ordi.codigo', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ordi.ciudad', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ordi.destinatario', 'like', '%' . $search . '%')
                    ->orWhere('paquetes_ordi.telefono', 'like', '%' . $search . '%')
                    ->orWhere('estados.nombre_estado', 'like', '%' . $search . '%');
            });
        }

        $slaResumen = $this->buildSlaResumen((clone $rows), [$this, 'decorateCertiOrdiSlaRow'], $entregados);
        $rows = $rows->orderByDesc('paquetes_ordi.id')->paginate(20)->withQueryString();
        $rows->through(function ($row) use ($entregados) {
            return $this->decorateCertiOrdiSlaRow($row, $entregados);
        });

        return $this->renderListado(
            $rows,
            'Indicador Ordinarios - ' . ($entregados ? 'Entregados' : 'Inventario'),
            'ORDINARIOS',
            $entregados ? 'ENTREGADOS' : 'INVENTARIO (NO ENTREGADOS)',
            $search,
            $entregados ? 'indicadores.ordinarios.entregados' : 'indicadores.ordinarios.inventario',
            (bool) $estadoEntregadoId,
            $entregados,
            true,
            $slaResumen
        );
    }

    private function applyEstadoScope(Builder $query, string $stateColumn, ?int $estadoEntregadoId, bool $entregados): void
    {
        if ($entregados) {
            if ($estadoEntregadoId) {
                $query->where($stateColumn, $estadoEntregadoId);
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        if ($estadoEntregadoId) {
            $query->where(function (Builder $sub) use ($stateColumn, $estadoEntregadoId) {
                $sub->whereNull($stateColumn)
                    ->orWhere($stateColumn, '<>', $estadoEntregadoId);
            });
        }
    }

    private function getEstadoEntregadoId(): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function renderListado(
        $rows,
        string $pageTitle,
        string $modulo,
        string $filtro,
        string $search,
        string $searchRouteName,
        bool $estadoEntregadoDisponible,
        bool $isEntregados,
        bool $showSla = false,
        array $slaResumen = []
    ) {
        return view('indicadores.listado', [
            'rows' => $rows,
            'pageTitle' => $pageTitle,
            'modulo' => $modulo,
            'filtro' => $filtro,
            'search' => $search,
            'searchRouteName' => $searchRouteName,
            'estadoEntregadoDisponible' => $estadoEntregadoDisponible,
            'isEntregados' => $isEntregados,
            'showSla' => $showSla,
            'slaResumen' => $slaResumen,
        ]);
    }

    private function buildSlaResumen(Builder $query, callable $decorateRow, bool $entregados): array
    {
        $resumen = [
            'correcto' => 0,
            'retraso' => 0,
            'rezago' => 0,
            'sin_datos' => 0,
        ];

        foreach ($query->cursor() as $row) {
            $row = $decorateRow($row, $entregados);
            $color = strtoupper((string) ($row->sla_color ?? ''));

            if ($color === 'VERDE') {
                $resumen['correcto']++;
            } elseif ($color === 'AMARILLO') {
                $resumen['retraso']++;
            } elseif ($color === 'ROJO') {
                $resumen['rezago']++;
            } else {
                $resumen['sin_datos']++;
            }
        }

        return $resumen;
    }

    private function decorateEmsSlaRow(object $row, bool $entregados): object
    {
        $inicio = $this->safeCarbon($row->solicitud_at ?? null)
            ?? $this->safeCarbon($row->fecha_registro ?? null);
        $fin = $entregados
            ? ($this->safeCarbon($row->entregado_evento_at ?? null)
                ?? $this->safeCarbon($row->fecha_actualizacion ?? null))
            : now();

        $destino = (string) ($row->destino ?? '');
        $esProvincia = $this->isEmsProvincia($destino);
        $umbrales = $this->resolveEmsThresholdDays($destino, $esProvincia);

        $row->sla_is_provincia = $esProvincia;
        $row->sla_green_days = $umbrales['green'];
        $row->sla_yellow_days = $umbrales['yellow'];
        $row->sla_red_from_days = $umbrales['yellow'] + 1;

        if (!$inicio || !$fin || $fin->lessThan($inicio)) {
            $row->sla_texto = '-';
            $row->sla_color = 'SIN DATOS';
            $row->sla_color_class = 'sla-gray';
            return $row;
        }

        $horas = $inicio->diffInHours($fin);
        $dias = intdiv($horas, 24);
        $horasResto = $horas % 24;

        $row->sla_total_horas = $horas;
        $row->sla_texto = $dias . 'd ' . $horasResto . 'h';

        $limiteVerdeHoras = $umbrales['green'] * 24;
        $limiteAmarilloHoras = $umbrales['yellow'] * 24;

        if ($horas <= $limiteVerdeHoras) {
            $row->sla_color = 'VERDE';
            $row->sla_color_class = 'sla-green';
            return $row;
        }

        if ($horas <= $limiteAmarilloHoras) {
            $row->sla_color = 'AMARILLO';
            $row->sla_color_class = 'sla-yellow';
            return $row;
        }

        $row->sla_color = 'ROJO';
        $row->sla_color_class = 'sla-red';
        return $row;
    }

    private function decorateContratoSlaRow(object $row, bool $entregados): object
    {
        $inicio = $this->safeCarbon($row->fecha_recojo ?? null);
        $fin = $entregados
            ? ($this->safeCarbon($row->entregado_evento_at ?? null)
                ?? $this->safeCarbon($row->fecha_actualizacion ?? null))
            : now();

        $destino = (string) ($row->destino ?? '');
        $provincia = trim((string) ($row->provincia ?? ''));
        $esProvincia = $provincia !== '';
        $umbrales = $this->resolveEmsThresholdDays($destino, $esProvincia);

        $row->sla_is_provincia = $esProvincia;
        $row->sla_green_days = $umbrales['green'];
        $row->sla_yellow_days = $umbrales['yellow'];
        $row->sla_red_from_days = $umbrales['yellow'] + 1;

        if (!$inicio || !$fin || $fin->lessThan($inicio)) {
            $row->sla_texto = '-';
            $row->sla_color = 'SIN DATOS';
            $row->sla_color_class = 'sla-gray';
            return $row;
        }

        $horas = $inicio->diffInHours($fin);
        $dias = intdiv($horas, 24);
        $horasResto = $horas % 24;

        $row->sla_total_horas = $horas;
        $row->sla_texto = $dias . 'd ' . $horasResto . 'h';

        $limiteVerdeHoras = $umbrales['green'] * 24;
        $limiteAmarilloHoras = $umbrales['yellow'] * 24;

        if ($horas <= $limiteVerdeHoras) {
            $row->sla_color = 'VERDE';
            $row->sla_color_class = 'sla-green';
            return $row;
        }

        if ($horas <= $limiteAmarilloHoras) {
            $row->sla_color = 'AMARILLO';
            $row->sla_color_class = 'sla-yellow';
            return $row;
        }

        $row->sla_color = 'ROJO';
        $row->sla_color_class = 'sla-red';
        return $row;
    }

    private function decorateCertiOrdiSlaRow(object $row, bool $entregados): object
    {
        $inicio = $this->safeCarbon($row->primer_evento_at ?? null)
            ?? $this->safeCarbon($row->fecha_registro ?? null);
        $fin = $entregados
            ? ($this->safeCarbon($row->entregado_evento_at ?? null)
                ?? $this->safeCarbon($row->fecha_actualizacion ?? null))
            : now();

        $row->sla_is_provincia = false;
        $row->sla_green_days = self::CERTI_ORDI_GREEN_DAYS;
        $row->sla_yellow_days = self::CERTI_ORDI_YELLOW_DAYS;
        $row->sla_red_from_days = self::CERTI_ORDI_RED_DAYS;

        if (!$inicio || !$fin || $fin->lessThan($inicio)) {
            $row->sla_texto = '-';
            $row->sla_color = 'SIN DATOS';
            $row->sla_color_class = 'sla-gray';
            return $row;
        }

        $horas = $inicio->diffInHours($fin);
        $dias = intdiv($horas, 24);
        $horasResto = $horas % 24;

        $row->sla_total_horas = $horas;
        $row->sla_texto = $dias . 'd ' . $horasResto . 'h';

        $limiteVerdeHoras = self::CERTI_ORDI_GREEN_DAYS * 24;
        $limiteAmarilloHoras = self::CERTI_ORDI_YELLOW_DAYS * 24;

        if ($horas <= $limiteVerdeHoras) {
            $row->sla_color = 'VERDE';
            $row->sla_color_class = 'sla-green';
            return $row;
        }

        if ($horas <= $limiteAmarilloHoras) {
            $row->sla_color = 'AMARILLO';
            $row->sla_color_class = 'sla-yellow';
            return $row;
        }

        $row->sla_color = 'ROJO';
        $row->sla_color_class = 'sla-red';
        return $row;
    }

    private function resolveEmsThresholdDays(string $destino, bool $esProvincia): array
    {
        $baseDestino = $this->resolveEmsBaseDestino($destino);
        $verde = in_array($baseDestino, self::DESTINOS_LARGA_DISTANCIA, true) ? 2 : 1;
        $amarillo = $verde + 1;

        if ($esProvincia) {
            $verde += 1;
            $amarillo += 1;
        }

        return [
            'green' => $verde,
            'yellow' => $amarillo,
        ];
    }

    private function resolveEmsBaseDestino(string $destino): string
    {
        $normalized = $this->normalizeDestino($destino);

        if (str_contains($normalized, 'SANTA CRUZ')) {
            return 'SANTA CRUZ';
        }

        if (str_contains($normalized, 'TARIJA')) {
            return 'TARIJA';
        }

        if (str_contains($normalized, 'TRINIDAD') || str_contains($normalized, 'TRINIDAD')) {
            return 'TRINIDAD';
        }

        foreach (self::DESTINOS_BASE as $base) {
            if (str_contains($normalized, $base)) {
                return $base;
            }
        }

        return $normalized;
    }

    private function isEmsProvincia(string $destino): bool
    {
        $normalized = $this->normalizeDestino($destino);
        if ($normalized === '' || $normalized === '-') {
            return false;
        }

        if (str_contains($normalized, 'PROV')) {
            return true;
        }

        if (in_array($normalized, self::DESTINOS_BASE, true) || in_array($normalized, self::DESTINOS_CAPITALES, true)) {
            return false;
        }

        foreach (self::DESTINOS_BASE as $base) {
            if ($normalized === $base) {
                return false;
            }

            if (
                str_starts_with($normalized, $base . ' ') ||
                str_starts_with($normalized, $base . '-') ||
                str_starts_with($normalized, $base . ',') ||
                str_starts_with($normalized, $base . '/')
            ) {
                return true;
            }
        }

        return true;
    }

    private function normalizeDestino(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }

    private function safeCarbon($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

