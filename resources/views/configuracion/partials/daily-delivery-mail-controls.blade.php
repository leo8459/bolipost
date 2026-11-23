<div class="col-lg-4">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-paper-plane mr-2"></i>Envío de reportes</h3>
            <span class="badge {{ $automaticEnabled ? 'badge-success' : 'badge-secondary' }} float-right">{{ $automaticEnabled ? 'Activo' : 'Inactivo' }}</span>
        </div>
        <div class="card-body">
            <p>Envío automático diario a las <strong>{{ $sendAt }}, hora de Bolivia</strong>.</p>

            <form method="GET" action="{{ route('daily-deliveries-email.index') }}" class="mb-3">
                <div class="form-group">
                    <label for="preview-date">Día del reporte</label>
                    <input type="date" id="preview-date" name="date" value="{{ $selectedDate }}" max="{{ now('America/La_Paz')->toDateString() }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="preview-regional">Regional de la vista previa</label>
                    <select id="preview-regional" name="regional" class="form-control">
                        <option value="">Consolidado nacional</option>
                        @foreach ($regionals as $regional)
                            <option value="{{ $regional }}" @selected($selectedRegional === $regional)>{{ $regional }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-outline-info btn-block"><i class="fas fa-search mr-1"></i>Ver reporte</button>
            </form>

            <form method="POST" action="{{ route('daily-deliveries-email.send') }}" class="mb-3">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">
                <button class="btn btn-success btn-block" @disabled($regionalRecipients === [] && $nationalRecipients === [])>
                    <i class="fas fa-envelope mr-1"></i>Enviar reportes del {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}
                </button>
                <small class="text-muted d-block mt-2">Envía los reportes de esa fecha a los destinatarios regionales y nacionales. El envío automático se mantiene.</small>
            </form>

            <form method="POST" action="{{ route('daily-deliveries-email.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $automaticEnabled ? 0 : 1 }}">
                <button class="btn btn-outline-primary btn-block">{{ $automaticEnabled ? 'Desactivar envío automático' : 'Activar envío automático' }}</button>
            </form>
        </div>
    </div>
</div>
