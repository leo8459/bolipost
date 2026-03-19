@props([
    'model' => 'peso',
    'name' => null,
    'value' => null,
    'inputId' => null,
    'label' => 'Peso',
    'required' => false,
    'useScale' => false,
    'showClear' => false,
    'livewire' => true,
    'live' => false,
    'statusToggle' => true,
    'statusCollapsed' => false,
    'statusToggleShowText' => 'Mostrar estado de balanza',
    'statusToggleHideText' => 'Ocultar estado de balanza',
    'step' => '0.001',
    'min' => '0',
    'placeholder' => null,
])

@php
    $parsedLivewire = filter_var($livewire, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $usesLivewire = $parsedLivewire ?? (bool) $livewire;
    $resolvedInputId = $inputId ?: 'peso-' . \Illuminate\Support\Str::slug((string) $model, '-');
    $resolvedPanelId = $resolvedInputId . '-cas-status-panel';
    $resolvedPlaceholder = $placeholder;
    $resolvedName = $name ?: ($usesLivewire ? null : $model);
    $resolvedValue = $value;
    $parsedStatusToggle = filter_var($statusToggle, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $usesStatusToggle = $parsedStatusToggle ?? (bool) $statusToggle;
    $parsedStatusCollapsed = filter_var($statusCollapsed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $startsCollapsed = $parsedStatusCollapsed ?? (bool) $statusCollapsed;
    $panelHiddenAtStart = $usesStatusToggle && $startsCollapsed;

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
    @if($usesLivewire)
        @if($live)
            wire:model.live.debounce.300ms="{{ $model }}"
        @else
            wire:model.defer="{{ $model }}"
        @endif
    @endif
    @if(!$usesLivewire && $resolvedName)
        name="{{ $resolvedName }}"
    @endif
    @if(!$usesLivewire && $resolvedValue !== null)
        value="{{ $resolvedValue }}"
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
                <button
                    type="button"
                    class="btn btn-outline-azul"
                    data-cas-clear
                    data-cas-clear-target="{{ $resolvedInputId }}"
                    @if($usesLivewire)
                        wire:click="$set('{{ $model }}', null)"
                    @endif
                >
                    Limpiar peso
                </button>
            @endif
        </div>
    </div>
@endif

@php
    $errorKey = $usesLivewire ? $model : $resolvedName;
    $errorBag = (isset($errors) && $errors instanceof \Illuminate\Support\ViewErrorBag)
        ? $errors
        : new \Illuminate\Support\ViewErrorBag();
@endphp
@if($errorKey && $errorBag->has($errorKey))
    <small class="text-danger">{{ $errorBag->first($errorKey) }}</small>
@endif

@if($useScale)
    @if($usesStatusToggle)
        <button
            type="button"
            class="btn btn-link peso-cas-toggle px-0 py-1 mt-2"
            data-cas-toggle-panel
            data-cas-target="{{ $resolvedPanelId }}"
            data-cas-text-show="{{ $statusToggleShowText }}"
            data-cas-text-hide="{{ $statusToggleHideText }}"
            aria-expanded="{{ $panelHiddenAtStart ? 'false' : 'true' }}"
        >
            {{ $panelHiddenAtStart ? $statusToggleShowText : $statusToggleHideText }}
        </button>
    @endif

    <div
        id="{{ $resolvedPanelId }}"
        class="peso-cas-panel @if($panelHiddenAtStart) d-none @endif"
        @if($usesLivewire) wire:ignore @endif
    >
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
