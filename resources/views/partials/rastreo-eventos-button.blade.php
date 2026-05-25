@php
    $trackingTipo = strtolower(trim((string) ($tipo ?? 'ems')));
    $trackingCodigo = trim((string) ($codigo ?? ''));
    $trackingRoute = match ($trackingTipo) {
        'contrato' => 'eventos-contrato.index',
        'certi', 'certificado' => 'eventos-certi.index',
        'ordi', 'ordinario' => 'eventos-ordi.index',
        'tiktoker', 'solicitud' => 'eventos-tiktoker.index',
        'despacho' => 'eventos-despacho.index',
        default => 'eventos-ems.index',
    };
    $trackingClass = $class ?? 'btn btn-sm btn-outline-azul rastreo-action-btn';
    $trackingText = $text ?? 'Rastreo';
@endphp

@if ($trackingCodigo !== '')
    <a href="{{ route($trackingRoute, ['q' => $trackingCodigo], false) }}"
        class="{{ $trackingClass }}"
        style="display:inline-flex;align-items:center;justify-content:center;gap:6px;"
        title="Rastreo"
        target="_blank"
        rel="noopener">
        <i class="fas fa-route"></i>
        @if ($trackingText !== '')
            <span>{{ $trackingText }}</span>
        @endif
    </a>
@endif
