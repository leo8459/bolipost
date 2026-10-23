@extends('adminlte::page')
@section('title', 'Solicitar cambios')
@section('content_header')
    <h1><i class="fas fa-file-signature text-primary mr-2" aria-hidden="true"></i>Solicitar cambios</h1>
    <p class="text-muted mb-0">Registra una necesidad de los sistemas institucionales y conserva tu ficha de requerimiento.</p>
@stop
@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Revisa los campos indicados.</strong><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @can('feature.solicitudes-cambio.create')
    <form method="POST" action="{{ route('solicitudes-cambio.store') }}" id="solicitud-form">
        @csrf
        <div class="card card-outline card-primary">
            <div class="card-header"><h2 class="card-title">Nueva solicitud al Área de Sistemas</h2><span class="float-right text-muted small">* Campos obligatorios</span></div>
            <div class="card-body">
                <div class="alert alert-light border">El número de requerimiento se asignará al registrar la ficha. Después podrás imprimirla para firma y sello.</div>
                <fieldset><legend class="h5 text-primary">1. Datos del informe</legend>
                    <div class="row">
                        <div class="form-group col-md-4"><label for="fecha">Fecha *</label><input class="form-control" type="date" id="fecha" name="fecha" required max="{{ now('America/La_Paz')->toDateString() }}" value="{{ old('fecha', now('America/La_Paz')->toDateString()) }}"></div>
                        @foreach (['ciudad' => ['Ciudad', auth()->user()->ciudad ?: 'La Paz'], 'solicitante' => ['De / solicitante', auth()->user()->name], 'cargo' => ['Cargo', ''], 'unidad' => ['Unidad o área', '']] as $field => [$label, $default])
                            <div class="form-group col-md-4"><label for="{{ $field }}">{{ $label }} *</label><input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" maxlength="255" required value="{{ old($field, $default) }}">@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @endforeach
                    </div>
                </fieldset>
                <hr>
                <fieldset><legend class="h5 text-primary">2. Identificación del requerimiento</legend>
                    <div class="row">
                        @foreach (['sistema' => ['Sistema', 'Ej.: Bolipost'], 'modulo' => ['Módulo o servicio', 'Ej.: Admisión de paquetes']] as $field => [$label, $placeholder])
                        <div class="form-group col-md-4"><label for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" required maxlength="255" value="{{ old($field) }}" placeholder="{{ $placeholder }}"></div>
                        @endforeach
                        <div class="form-group col-md-4"><label for="ambiente">Ambiente *</label><select class="form-control" id="ambiente" name="ambiente" required><option value="">Selecciona el ambiente</option>@foreach (['Producción', 'Pruebas', 'Desarrollo'] as $ambiente)<option @selected(old('ambiente') === $ambiente)>{{ $ambiente }}</option>@endforeach</select><small class="text-muted">Producción es el sistema que utilizas en tu trabajo diario.</small></div>
                    </div>
                    <div class="form-group"><label for="tipo">Tipo de requerimiento *</label><select class="form-control" id="tipo" name="tipo" required><option value="">Selecciona el tipo</option>@foreach (\App\Models\SolicitudCambio::TIPOS as $tipo)<option @selected(old('tipo') === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
                    <div class="form-group" id="otro-grupo"><label for="otro_tipo">Especifica el otro tipo *</label><input class="form-control" id="otro_tipo" name="otro_tipo" maxlength="255" value="{{ old('otro_tipo') }}"></div>
                </fieldset>
                <hr>
                <fieldset><legend class="h5 text-primary">3. Describe lo que necesitas</legend>
                    @foreach (['antecedentes' => ['Antecedentes o situación actual', 'Explica qué ocurre actualmente, en qué pantalla y cómo afecta tu trabajo. Si es un error, indica los pasos para reproducirlo.'], 'detalle' => ['Requerimiento solicitado', 'Describe qué necesitas que se realice y cuál sería el resultado esperado.']] as $field => [$label, $help])
                    <div class="form-group"><label for="{{ $field }}">{{ $label }} *</label><p class="small text-muted mb-1" id="{{ $field }}-help">{{ $help }}</p><textarea class="form-control" id="{{ $field }}" name="{{ $field }}" rows="5" required maxlength="10000" aria-describedby="{{ $field }}-help">{{ old($field) }}</textarea><small class="text-muted" data-counter="{{ $field }}"></small></div>
                    @endforeach
                    <fieldset class="form-group"><legend class="h6 font-weight-bold">Prioridad de atención *</legend>
                        @foreach (['Alta' => 'Impide trabajar', 'Media' => 'Dificulta una tarea', 'Baja' => 'Puede programarse'] as $priority => $help)
                        <label class="border rounded p-3 mr-2"><input type="radio" name="prioridad" value="{{ $priority }}" required @checked(old('prioridad', 'Media') === $priority)> {{ $priority }} <span class="small text-muted">— {{ $help }}</span></label>
                        @endforeach
                    </fieldset>
                </fieldset>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center justify-content-between"><span class="text-muted mb-2">Por lo expuesto, solicito la evaluación y atención del requerimiento descrito.</span><button class="btn btn-primary" type="submit" id="registrar"><i class="fas fa-paper-plane mr-1" aria-hidden="true"></i> Registrar solicitud</button></div>
        </div>
    </form>
    @endcan
    <div class="card"><div class="card-header"><h2 class="card-title">{{ auth()->user()->can('feature.solicitudes-cambio.manage') ? 'Solicitudes registradas' : 'Mis solicitudes' }}</h2></div>
        <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0"><thead><tr><th>N.º</th><th>Fecha</th><th>Solicitante</th><th>Sistema / módulo</th><th>Tipo</th><th>Prioridad</th><th>Ficha</th></tr></thead><tbody>
        @forelse ($solicitudes as $solicitud)
            <tr><td>{{ $solicitud->numero }}</td><td>{{ $solicitud->fecha->format('d/m/Y') }}</td><td>{{ $solicitud->solicitante }}</td><td>{{ $solicitud->sistema }} / {{ $solicitud->modulo }}</td><td>{{ $solicitud->tipo }}</td><td><span class="badge badge-{{ ['Alta' => 'danger', 'Media' => 'warning', 'Baja' => 'info'][$solicitud->prioridad] }}">{{ $solicitud->prioridad }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('solicitudes-cambio.show', $solicitud) }}">Ver ficha</a></td></tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay solicitudes registradas.</td></tr> @endforelse
        </tbody></table></div><div class="card-footer">{{ $solicitudes->links() }}</div>
    </div>
@stop
@section('js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('solicitud-form');
    if (!form) return;
    const tipo = document.getElementById('tipo');
    const otro = document.getElementById('otro_tipo');
    const toggle = () => { const visible = tipo.value === 'Otro'; document.getElementById('otro-grupo').hidden = !visible; otro.required = visible; otro.disabled = !visible; };
    tipo.addEventListener('change', toggle); toggle();
    document.querySelectorAll('[data-counter]').forEach(counter => {
        const field = document.getElementById(counter.dataset.counter);
        const update = () => counter.textContent = `${field.value.length.toLocaleString('es')} / 10.000 caracteres`;
        field.addEventListener('input', update); update();
    });
    form.addEventListener('submit', () => { const button = document.getElementById('registrar'); button.disabled = true; button.textContent = 'Registrando…'; });
    window.addEventListener('pageshow', () => { document.getElementById('registrar').disabled = false; document.getElementById('registrar').textContent = 'Registrar solicitud'; });
});
</script>
@stop
