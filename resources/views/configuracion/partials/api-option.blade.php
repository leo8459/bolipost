@php
    $isChecked = in_array($ability, old('abilities', []), true);
    $firstEndpoint = $api['endpoints'][0];
    $inputId = 'api-ability-'.md5($ability);
@endphp

<div class="api-option {{ $isChecked ? 'is-selected' : '' }}">
    <input type="checkbox" id="{{ $inputId }}" class="api-option-check js-api-ability" name="abilities[]" value="{{ $ability }}" @checked($isChecked)>
    <span class="api-option-icon"><i class="{{ $api['icon'] }}"></i></span>
    <label for="{{ $inputId }}" class="api-option-title d-block">{{ $api['name'] }}</label>
    <div class="api-option-description">{{ $api['description'] }}</div>
    <span class="badge badge-{{ $api['color'] }} mb-2">{{ $api['access'] }}</span>

    @foreach ($api['endpoints'] as $endpoint)
        <div class="api-endpoint">
            <span class="api-method {{ $endpoint['method'] !== 'GET' ? 'is-write' : '' }}">{{ $endpoint['method'] }}</span>
            <code>{{ $endpoint['path'] }}{{ $endpoint['example'] }}</code>
        </div>
    @endforeach

    <details class="api-example mt-2">
        <summary>Ver ejemplo para Postman</summary>
        <pre>{{ $firstEndpoint['method'] }} {{ url($firstEndpoint['path']) }}{{ $firstEndpoint['example'] }}
Authorization: Bearer TU_TOKEN
Accept: application/json
@if ($firstEndpoint['method'] !== 'GET')
Content-Type: application/json

{
  "direccion_destino": "Av. Principal 123",
  "ciudad": "LA PAZ",
  "telefono_destinatario": "71234567"
}
@endif
</pre>
    </details>
</div>
