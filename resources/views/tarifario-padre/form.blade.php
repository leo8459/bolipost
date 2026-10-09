<div class="form-group">
    <label for="nombre">Nombre del tarifario padre</label>
    <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $tarifarioPadre->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" maxlength="255" required placeholder="Ej.: Tarifario padre 1">
    @error('nombre')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="d-flex justify-content-between">
    <a href="{{ route('tarifario-padre.index') }}" class="btn btn-secondary">Volver</a>
    @aclcan($tarifarioPadre->exists ? 'edit' : 'create', null, 'tarifario-padre')
        <button type="submit" class="btn btn-primary">Guardar</button>
    @endaclcan
</div>
