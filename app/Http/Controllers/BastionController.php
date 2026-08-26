<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class BastionController extends Controller
{
    private const TIPOS = [
        'ems' => [
            'bastion' => 'bastion_ems',
            'destino' => 'paquetes_ems',
            'etiqueta' => 'EMS',
            'destinatario' => 'nombre_destinatario',
            'origen' => 'origen',
            'destino_nombre' => 'ciudad',
        ],
        'contratos' => [
            'bastion' => 'bastion_contratos',
            'destino' => 'paquetes_contrato',
            'etiqueta' => 'Contratos',
            'destinatario' => 'nombre_d',
            'origen' => 'origen',
            'destino_nombre' => 'destino',
        ],
        'certificados' => [
            'bastion' => 'bastion_certi',
            'destino' => 'paquetes_certi',
            'etiqueta' => 'Certificados',
            'destinatario' => 'destinatario',
            'origen' => null,
            'destino_nombre' => 'cuidad',
        ],
        'ordinarios' => [
            'bastion' => 'bastion_ordi',
            'destino' => 'paquetes_ordi',
            'etiqueta' => 'Ordinarios',
            'destinatario' => 'destinatario',
            'origen' => null,
            'destino_nombre' => 'ciudad',
        ],
    ];

    public function index(Request $request): View
    {
        $tipo = array_key_exists((string) $request->query('tipo'), self::TIPOS)
            ? (string) $request->query('tipo')
            : 'todos';
        $busqueda = trim((string) $request->query('buscar'));
        $seleccionados = $tipo === 'todos' ? self::TIPOS : [$tipo => self::TIPOS[$tipo]];

        $union = null;
        foreach ($seleccionados as $clave => $configuracion) {
            $consulta = $this->consultaTipo($clave, $configuracion, $busqueda);
            $union = $union ? $union->unionAll($consulta) : $consulta;
        }

        $paquetes = DB::query()
            ->fromSub($union, 'paquetes_bastion')
            ->orderByDesc('created_at')
            ->orderByDesc('bastion_id')
            ->paginate(25)
            ->withQueryString();

        $totales = collect(self::TIPOS)->mapWithKeys(
            fn (array $configuracion, string $clave): array => [
                $clave => DB::table($configuracion['bastion'])->count(),
            ]
        );

        return view('bastiones.index', compact('paquetes', 'totales', 'tipo', 'busqueda'));
    }

    public function recuperar(string $tipo, int $id): RedirectResponse
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);
        $configuracion = self::TIPOS[$tipo];

        try {
            $resultado = DB::transaction(function () use ($configuracion, $id): array {
                $registro = DB::table($configuracion['bastion'])
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($registro, 404);

                $codigo = trim((string) ($registro->codigo ?? ''));
                if ($codigo !== '' && DB::table($configuracion['destino'])->where('codigo', $codigo)->exists()) {
                    return ['duplicado' => true, 'codigo' => $codigo];
                }

                $columnasDestino = Schema::getColumnListing($configuracion['destino']);
                $datos = array_intersect_key((array) $registro, array_flip($columnasDestino));
                unset($datos['id']);

                $idOriginal = (int) ($registro->id_origen ?? 0);
                if ($idOriginal > 0 && in_array('id', $columnasDestino, true)
                    && ! DB::table($configuracion['destino'])->where('id', $idOriginal)->exists()) {
                    $datos['id'] = $idOriginal;
                }

                $nuevoId = DB::table($configuracion['destino'])->insertGetId($datos);
                DB::table($configuracion['bastion'])->where('id', $id)->delete();

                return ['duplicado' => false, 'codigo' => $codigo, 'id' => $nuevoId];
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'recuperar' => 'No se pudo recuperar el paquete. El registro permanece intacto en el bastión.',
            ]);
        }

        if ($resultado['duplicado']) {
            return back()->withErrors([
                'recuperar' => "El código {$resultado['codigo']} ya existe en {$configuracion['etiqueta']}; no se realizó ningún cambio.",
            ]);
        }

        Log::info('Paquete recuperado desde bastión.', [
            'tipo' => $tipo,
            'codigo' => $resultado['codigo'],
            'destino_id' => $resultado['id'],
            'usuario_id' => auth()->id(),
        ]);

        $identificador = $resultado['codigo'] !== '' ? $resultado['codigo'] : '#'.$resultado['id'];

        return back()->with('success', "El paquete {$identificador} fue recuperado en {$configuracion['etiqueta']}.");
    }

    private function consultaTipo(string $tipo, array $configuracion, string $busqueda): Builder
    {
        $origen = $configuracion['origen'] ? $configuracion['origen'] : "''";
        $consulta = DB::table($configuracion['bastion'])->selectRaw(
            'id AS bastion_id, ? AS tipo, ? AS tipo_etiqueta, codigo, cod_especial, '
            .$configuracion['destinatario'].' AS destinatario, '
            .$origen.' AS origen, '
            .$configuracion['destino_nombre'].' AS destino, created_at',
            [$tipo, $configuracion['etiqueta']]
        );

        if ($busqueda !== '') {
            $termino = '%'.mb_strtolower($busqueda).'%';
            $consulta->where(function (Builder $query) use ($configuracion, $termino): void {
                foreach (['codigo', 'cod_especial', $configuracion['destinatario']] as $columna) {
                    $query->orWhereRaw("LOWER(COALESCE({$columna}, '')) LIKE ?", [$termino]);
                }
            });
        }

        return $consulta;
    }
}
