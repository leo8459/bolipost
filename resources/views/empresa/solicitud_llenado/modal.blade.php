<div class="modal fade show" id="empresa-location-request-modal" tabindex="-1" role="dialog"
    aria-labelledby="empresa-location-request-title" aria-modal="true" style="display: block; background: rgba(0, 0, 0, .5);"
    data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="empresa-location-request-title">
                    <i class="fas fa-map-marker-alt mr-2"></i>Ubicación de su entidad
                </h5>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Por favor, indique si su entidad pertenece a un municipio o provincia.</p>
                <p class="text-muted">Escriba el nombre en el campo. Se guardará en letras mayúsculas.</p>

                <form method="POST" action="{{ route('empresa.solicitud-llenado.responder') }}" id="empresa-location-answer-form">
                    @csrf
                    <input type="hidden" name="tipo" value="ubicacion">
                    <div class="form-group mb-0">
                        <label for="empresa-location-input">Nombre del municipio o provincia</label>
                        <input type="text" name="ubicacion" id="empresa-location-input"
                            class="form-control text-uppercase @error('ubicacion') is-invalid @enderror"
                            value="{{ old('ubicacion', auth()->user()->provincia_origen) }}"
                            placeholder="Escriba el municipio o provincia" maxlength="255" autocomplete="off" required>
                        @error('ubicacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mt-3">
                        <i class="fas fa-save mr-1"></i>Guardar municipio o provincia
                    </button>
                </form>

                <form method="POST" action="{{ route('empresa.solicitud-llenado.responder') }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="tipo" value="capital">
                    <button type="submit" class="btn btn-outline-secondary btn-block">
                        No pertenezco; mi entidad está en ciudad capital
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('empresa-location-input');
        if (!input) return;

        const uppercase = () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.toLocaleUpperCase('es-BO');
            input.setSelectionRange(start, end);
        };

        input.addEventListener('input', uppercase);
        uppercase();
        input.focus();
    });
</script>
