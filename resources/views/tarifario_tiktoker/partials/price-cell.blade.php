@php
    $priceValue = $tarifa->{$field};
    $priceLabel = $priceLabels[$field] ?? 'precio';
@endphp
<td data-price-cell data-field="{{ $field }}" data-price-value="{{ $priceValue }}" data-update-url="{{ route('tarifario-tiktoker.price.update', $tarifa->id) }}">
    <div data-price-editor>
        <button type="button" class="tarifario-price-trigger" data-price-edit aria-label="Editar {{ $priceLabel }} del tarifario {{ $tarifa->id }}" title="Editar {{ $priceLabel }}">
            <span data-price-display>{{ $priceValue !== null ? \App\Support\BolivianNumber::format((float) $priceValue, 2) : '-' }}</span>
            <i class="fa fa-edit tarifario-price-pencil" aria-hidden="true"></i>
        </button>
        <div class="d-none" data-price-controls>
            <div class="d-flex align-items-center justify-content-center" style="gap:3px;">
                <input type="number" min="0" step="0.01" required class="form-control form-control-sm tarifario-price-input" data-price-input value="{{ $priceValue }}" aria-label="{{ $priceLabel }} en bolivianos">
                <button type="button" class="btn btn-sm btn-primary" data-price-save title="Guardar precio" aria-label="Guardar precio">
                    <i class="fa fa-check" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-price-cancel title="Cancelar" aria-label="Cancelar">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <small class="d-none text-danger" data-price-error></small>
        </div>
    </div>
</td>
