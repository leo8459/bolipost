<div class="col-lg-4">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i>Envío del {{ $reportLabel }}</h3>
            <span class="badge {{ $automaticEnabled ? 'badge-success' : 'badge-secondary' }} float-right">{{ $automaticEnabled ? 'Activo' : 'Inactivo' }}</span>
        </div>
        <div class="card-body">
            <p>El envío automático se realiza a las <strong>{{ $sendAt }}, hora de Bolivia</strong>, con las operaciones del día.</p>
            <p class="text-muted">El envío manual y su Excel corresponden a la fecha seleccionada.</p>

            <form method="GET" action="{{ route($routePrefix.'.index') }}" class="mb-3">
                <label for="preview-date">Día que desea revisar</label>
                <div class="input-group">
                    <input type="date" id="preview-date" name="date" value="{{ $selectedDate }}" max="{{ now('America/La_Paz')->toDateString() }}" class="form-control" required>
                    <div class="input-group-append">
                        <button class="btn btn-outline-info"><i class="fas fa-search mr-1"></i>Ver</button>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route($routePrefix.'.send') }}" class="mb-3">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">
                <button class="btn btn-success btn-block" @disabled(empty($recipients))>
                    <i class="fas fa-envelope mr-1"></i>Enviar {{ $reportLabel }} del {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}
                </button>
            </form>

            <form method="POST" action="{{ route($routePrefix.'.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $automaticEnabled ? 0 : 1 }}">
                <button class="btn btn-outline-primary btn-block">{{ $automaticEnabled ? 'Desactivar envío automático' : 'Activar envío automático' }}</button>
            </form>
            <small class="text-muted d-block mt-3">El envío automático de las {{ $sendAt }} se mantiene después de un envío manual.</small>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Agregar destinatario</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route($routePrefix.'.recipients.store') }}">
                @csrf
                <label for="recipient">Correo electrónico</label>
                <input type="email" id="recipient" name="recipient" value="{{ old('recipient') }}" class="form-control @error('recipient') is-invalid @enderror" maxlength="254" placeholder="nombre@correos.gob.bo" required>
                @error('recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary btn-block mt-3"><i class="fas fa-plus mr-1"></i>Agregar</button>
            </form>
            <small class="text-muted d-block mt-2">Hasta 50 correos. Esta lista es exclusiva de {{ $mailTitle }}.</small>
        </div>
    </div>

    <div class="card card-info card-outline">
        <div class="card-header"><h3 class="card-title">Correos guardados</h3><span class="badge badge-info float-right">{{ count($recipients) }}</span></div>
        <div class="card-body p-0">
            @forelse ($recipients as $recipient)
                <div class="d-flex align-items-center p-3 border-bottom">
                    <span class="text-break mr-2">{{ $recipient }}</span>
                    <form method="POST" action="{{ route($routePrefix.'.recipients.destroy') }}" class="ml-auto">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="recipient" value="{{ $recipient }}">
                        <button class="btn btn-outline-danger btn-sm" aria-label="Quitar {{ $recipient }}"><i class="fas fa-trash-alt"></i></button>
                    </form>
                </div>
            @empty
                <p class="text-muted p-3 mb-0">Agregue un correo para poder enviar el {{ $reportLabel }}.</p>
            @endforelse
        </div>
    </div>
</div>
