@props([
    'model' => 'peso',
    'inputId' => null,
    'label' => 'Peso',
    'required' => false,
    'useScale' => false,
    'showClear' => false,
    'live' => false,
    'step' => '0.001',
    'min' => '0',
    'placeholder' => null,
])

@php
    $resolvedInputId = $inputId ?: 'peso-' . \Illuminate\Support\Str::slug((string) $model, '-');
    $resolvedPlaceholder = $placeholder;

    if ($useScale && $resolvedPlaceholder === null) {
        $resolvedPlaceholder = 'Esperando balanza...';
    }
    $useInputGroup = $showClear;
@endphp

<label>
    {{ $label }}
    @if($required)
        <span class="required-star">*</span>
    @endif
</label>

@if($useInputGroup)
    <div class="input-group">
@endif

<input
    type="number"
    id="{{ $resolvedInputId }}"
    @if($live)
        wire:model.live.debounce.300ms="{{ $model }}"
    @else
        wire:model.defer="{{ $model }}"
    @endif
    class="form-control"
    step="{{ $step }}"
    min="{{ $min }}"
    @if($resolvedPlaceholder !== null && $resolvedPlaceholder !== '') placeholder="{{ $resolvedPlaceholder }}" @endif
    @if($useScale) data-cas-peso-input @endif
    @if($required) required @endif
>

@if($useInputGroup)
        <div class="input-group-append">
            @if($showClear)
                <button type="button" class="btn btn-outline-azul" wire:click="$set('{{ $model }}', null)">Limpiar peso</button>
            @endif
        </div>
    </div>
@endif

@if($errors->has($model))
    <small class="text-danger">{{ $errors->first($model) }}</small>
@endif

@if($useScale)
    <div class="peso-cas-panel" wire:ignore>
        <div class="peso-cas-status-line">
            <span class="status-pill status-warn" data-cas-qz-pill>PENDIENTE</span>
            <small class="text-muted" data-cas-qz-text>Esperando QZ Tray...</small>
        </div>
        <div class="peso-cas-status-line mt-1">
            <span class="status-pill status-warn" data-cas-port-pill>SIN PUERTO</span>
            <small class="text-muted" data-cas-port-text>Sin conectar</small>
            <button type="button" class="btn btn-outline-azul btn-sm py-0 px-2" data-cas-reconnect>Reconectar</button>
        </div>
        <div class="peso-cas-frame" data-cas-frame-text>Sin datos</div>
    </div>

    <x-peso-qz-assets />
@endif
