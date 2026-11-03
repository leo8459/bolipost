@php
    $jsonBody = old('json_body', $registroApi->json_body);
@endphp

@csrf
<div class="card-body">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-row">
        <div class="form-group col-md-3">
            <label for="method">Método HTTP <span class="text-danger">*</span></label>
            <select id="method" name="method" class="form-control @error('method') is-invalid @enderror" required>
                @foreach ($methods as $method)
                    <option value="{{ $method }}" @selected(old('method', $registroApi->method) === $method)>{{ $method }}</option>
                @endforeach
            </select>
            @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="form-group col-md-9">
            <label for="url">URL <span class="text-danger">*</span></label>
            <input id="url" name="url" type="text" maxlength="2048" value="{{ old('url', $registroApi->url) }}" class="form-control @error('url') is-invalid @enderror" placeholder="https://api.ejemplo.com/recurso" required>
            @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="form-group">
        <label for="token">Token <span class="text-danger">*</span></label>
        <div class="input-group">
            <input id="token" name="token" type="password" maxlength="10000" value="{{ old('token', $registroApi->token) }}" class="form-control @error('token') is-invalid @enderror" autocomplete="new-password" spellcheck="false" @required(! $registroApi->exists)>
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary" id="toggle-token" aria-label="Mostrar u ocultar token">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        @error('token') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @if ($registroApi->exists)
            <small class="form-text text-muted">El token se guarda cifrado. Déjalo como está para conservarlo o reemplázalo por uno nuevo.</small>
        @else
            <small class="form-text text-muted">El token se guardará cifrado en la base de datos.</small>
        @endif
    </div>

    <div class="form-group">
        <label for="description">Descripción <span class="text-danger">*</span></label>
        <textarea id="description" name="description" rows="3" maxlength="5000" class="form-control @error('description') is-invalid @enderror" placeholder="¿Para qué sirve esta API? ¿Quién la envió?" required>{{ old('description', $registroApi->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group mb-0">
        <label for="json_body">JSON (opcional)</label>
        <textarea id="json_body" name="json_body" rows="8" class="form-control font-monospace @error('json_body') is-invalid @enderror" placeholder="{&#10;  &quot;ejemplo&quot;: &quot;valor&quot;&#10;}" spellcheck="false">{{ $jsonBody }}</textarea>
        @error('json_body') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="form-text text-muted">Se valida que el contenido sea JSON correcto antes de guardar.</small>
    </div>
</div>

<div class="card-footer d-flex justify-content-between">
    <a href="{{ route('registros-apis.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar API</button>
</div>

@push('js')
    <script>
        document.getElementById('toggle-token')?.addEventListener('click', function () {
            const token = document.getElementById('token');
            const icon = this.querySelector('i');
            const showing = token.type === 'password';
            token.type = showing ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !showing);
            icon.classList.toggle('fa-eye-slash', showing);
        });
    </script>
@endpush
