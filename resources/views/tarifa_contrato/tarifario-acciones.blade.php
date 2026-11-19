@php
    $editando = (string) old('formulario_tarifario') === (string) $item->id;
    $asignando = (string) old('formulario_asignacion') === (string) $item->id;
    $urlTarifas = route('tarifa-contrato.index', ['tarifario_id' => $item->id]);
@endphp
<dialog id="tarifario-acciones-{{ $item->id }}" class="tarifario-modal" aria-labelledby="tarifario-acciones-titulo-{{ $item->id }}" @if($editando || $asignando) data-acciones-reabrir @endif>
    <header class="tarifario-modal-cabecera">
        <div>
            <h2 id="tarifario-acciones-titulo-{{ $item->id }}">Acciones del tarifario</h2>
            <p>{{ $item->etiquetaSeleccion }}</p>
        </div>
        <form method="dialog">
            <button type="submit" class="btn tarifario-modal-cerrar" aria-label="Cerrar acciones" autofocus>
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </form>
    </header>
    <div class="tarifario-panel-contenido">
        @if(($editando || $asignando) && $errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="tarifario-opciones">
            <a class="tarifario-opcion" href="{{ $urlTarifas }}">
                <i class="fas fa-list tarifario-panel-icono" aria-hidden="true"></i>
                <span><strong>Ver tarifas</strong><small>Consulta los precios, las rutas y los plazos.</small></span>
            </a>
            @if(!$item->publicado_en)
                @aclcan('create', null, 'tarifa-contrato')
                    <a class="tarifario-opcion" href="{{ route('tarifa-contrato.create', ['tarifario_id' => $item->id]) }}">
                        <i class="fas fa-file-upload tarifario-panel-icono" aria-hidden="true"></i>
                        <span><strong>Cargar tarifas</strong><small>Completa la hoja o importa un Excel.</small></span>
                    </a>
                @endaclcan
            @endif
            @aclcan('save', null, 'tarifa-contrato')
                <a class="tarifario-opcion" href="{{ route('tarifa-contrato.tarifarios', ['copiar_de_id' => $item->id]) }}#nuevo-tarifario">
                    <i class="far fa-copy tarifario-panel-icono" aria-hidden="true"></i>
                    <span><strong>Renovar o copiar</strong><small>Usa estos precios como base de otro tarifario.</small></span>
                </a>
                @if(!$item->publicado_en && $item->tarifas_count)
                    <form method="POST" action="{{ route('tarifa-contrato.tarifarios-aprobar', $item) }}">
                        @csrf
                        <button class="tarifario-opcion tarifario-opcion-destacada" type="button" aria-haspopup="dialog"
                            data-aprobar-tarifario
                            data-nombre="{{ $item->empresaTitular?->nombre ?? $item->nombre }}"
                            data-anio="{{ $item->anio ?? 'Por definir' }}"
                            data-cantidad="{{ number_format($item->tarifas_count, 0, ',', '.') }}"
                            data-revisar-url="{{ $urlTarifas }}"
                            data-tiene-titular="{{ (int) (bool) $item->empresa_titular_id }}">
                            <i class="fas fa-check tarifario-panel-icono" aria-hidden="true"></i>
                            <span><strong>Aprobar uso</strong><small>Revisa y confirma el uso oficial de estos precios.</small></span>
                        </button>
                    </form>
                @endif
            @endaclcan
        </div>

        @aclcan('save', null, 'tarifa-contrato')
            <div class="tarifario-opciones-secundarias">
                @if($item->tarifas_count && ($item->publicado_en || !$item->empresaTitular))
                    <details class="tarifario-desplegable" @if($asignando) open @endif>
                        <summary>Asignar a empresas</summary>
                        <form method="POST" class="mt-2" action="{{ route('tarifa-contrato.tarifarios-asignar', $item) }}">
                            @csrf
                            <input type="hidden" name="formulario_asignacion" value="{{ $item->id }}">
                            <fieldset data-tarifario-lista>
                                <legend class="h6 mb-2">Selecciona las empresas</legend>
                                <div class="mb-2" data-tarifario-controles hidden>
                                    <label class="sr-only" for="buscar-asignacion-{{ $item->id }}">Buscar empresa por nombre o código</label>
                                    <input type="search" id="buscar-asignacion-{{ $item->id }}" class="form-control form-control-sm" placeholder="Buscar por nombre o código" data-tarifario-busqueda>
                                    <p class="small text-muted mb-0 mt-1" role="status" data-tarifario-resultados></p>
                                </div>
                                <div class="tarifario-empresas">
                                    @foreach($empresas as $empresa)
                                        <label class="tarifario-empresa" data-tarifario-fila="{{ $empresa->nombre }} {{ $empresa->codigo_cliente }}">
                                            <input type="checkbox" name="empresas[]" value="{{ $empresa->id }}" @checked($asignando && in_array((string) $empresa->id, (array) old('empresas', []), true))>
                                            <span>{{ $empresa->nombre }}@if(filled($empresa->codigo_cliente)) <small class="text-muted">{{ $empresa->codigo_cliente }}</small>@endif</span>
                                        </label>
                                    @endforeach
                                    <p class="small text-muted m-2" data-tarifario-sin-resultados hidden>No se encontraron empresas.</p>
                                </div>
                            </fieldset>
                            <p class="small text-muted my-2">Las nuevas guías de las empresas seleccionadas aplicarán estos precios. @if(!$item->publicado_en) Al asignarlo también se aprueba su uso. @endif</p>
                            <button type="submit" class="btn btn-sm tarifario-boton-azul">Asignar tarifario</button>
                        </form>
                    </details>
                @endif
                <details class="tarifario-desplegable" @if($editando) open @endif>
                    <summary>{{ $item->publicado_en ? 'Editar año' : 'Editar empresa y año' }}</summary>
                    <form method="POST" class="mt-2" action="{{ route('tarifa-contrato.tarifarios-update', $item) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="formulario_tarifario" value="{{ $item->id }}">
                        @if(!$item->publicado_en)
                            <label for="titular-{{ $item->id }}">Empresa titular</label>
                            <select class="form-control form-control-sm mb-2" id="titular-{{ $item->id }}" name="empresa_titular_id" required>
                                <option value="">Elige una empresa registrada…</option>
                                @foreach($empresas as $empresa)
                                    <option value="{{ $empresa->id }}" @selected((string) ($editando ? old('empresa_titular_id') : $item->empresa_titular_id) === (string) $empresa->id)>{{ $empresa->nombre }}@if(filled($empresa->codigo_cliente)) · {{ $empresa->codigo_cliente }}@endif</option>
                                @endforeach
                            </select>
                        @endif
                        <label for="anio-{{ $item->id }}">Año</label>
                        <input type="number" class="form-control form-control-sm mb-2" id="anio-{{ $item->id }}" name="anio" min="2000" max="2100" value="{{ $editando ? old('anio') : $item->anio }}" required>
                        <button type="submit" class="btn btn-sm tarifario-boton-azul">Guardar cambios</button>
                    </form>
                </details>
            </div>
        @endaclcan
    </div>
</dialog>
