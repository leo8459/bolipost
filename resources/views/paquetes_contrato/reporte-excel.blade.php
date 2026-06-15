@php
    $codigo = (string) ($contrato->codigo ?? '');
    $verificationUrl = $verificationUrl ?? '';
    $departamentoDestino = (string) ($contrato->destino ?? '-');
    $provincia = trim((string) ($contrato->provincia ?? ''));
    $departamentoDetalle = $departamentoDestino;
    if ($provincia !== '') {
        $departamentoDetalle .= ' - PROVINCIA: ' . strtoupper($provincia);
    }
    $fechaRecojo = optional($contrato->fecha_recojo ?? null)->format('d/m/Y H:i') ?: optional($contrato->created_at ?? null)->format('d/m/Y H:i');
    $empresaNombre = trim((string) (optional($contrato->empresa)->nombre ?? optional(optional($contrato->user)->empresa)->nombre ?? ''));
    $copias = ['ORIGINAL', 'COPIA 1', 'COPIA 2'];
@endphp

<table>
    @foreach($copias as $index => $copia)
        <tr>
            <td colspan="2">Correos de Bolivia</td>
            <td colspan="4">{{ $copia }}</td>
            <td colspan="2">CN-33: {{ $codigo }}</td>
        </tr>
        <tr>
            <td colspan="8">GUIA CONTRATO CN-33</td>
        </tr>
        <tr>
            <td colspan="4">Remitente: {{ $contrato->nombre_r }}</td>
            <td colspan="4">Destinatario: {{ $contrato->nombre_d }}</td>
        </tr>
        <tr>
            <td>Telefono remitente</td>
            <td>{{ $contrato->telefono_r }}</td>
            <td>Origen</td>
            <td>{{ $contrato->origen }}</td>
            <td>Telefono destinatario</td>
            <td>{{ $contrato->telefono_d ?: '-' }}</td>
            <td>Destino</td>
            <td>{{ $departamentoDetalle }}</td>
        </tr>
        <tr>
            <td colspan="4">Direccion remitente: {{ $contrato->direccion_r }}</td>
            <td colspan="4">Direccion destinatario: {{ $contrato->direccion_d }}</td>
        </tr>
        <tr>
            <td>Codigo especial</td>
            <td>{{ $contrato->cod_especial ?: '-' }}</td>
            <td>Peso</td>
            <td>{{ $contrato->peso ?: '-' }}</td>
            <td>Cantidad</td>
            <td>{{ $contrato->cantidad ?: '-' }}</td>
            <td>Precio</td>
            <td>{{ $contrato->precio ?: '-' }}</td>
        </tr>
        <tr>
            <td colspan="4">Empresa: {{ $empresaNombre !== '' ? $empresaNombre : '-' }}</td>
            <td colspan="4">Contenido: {{ $contrato->contenido ?: '-' }}</td>
        </tr>
        <tr>
            <td colspan="4">Fecha solicitud: {{ $fechaRecojo ?: '-' }}</td>
            <td colspan="4">Generado: {{ optional($generatedAt ?? null)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="4">Observacion: {{ $contrato->observacion ?: '-' }}</td>
            <td colspan="2">QR verificacion</td>
            <td colspan="2">{{ $verificationUrl }}</td>
        </tr>
        <tr>
            <td colspan="8">Devolucion CN 15: Se mudo [ ]  No reclamado [ ]  Desconocido [ ]  Rechazado [ ]  Direccion insuficiente [ ]  Se ausento [ ]</td>
        </tr>
        <tr>
            <td colspan="8">El cliente declara que los datos proporcionados son ciertos y que el contenido cumple con las normas de seguridad postal, bajo su unica y exclusiva responsabilidad. Correos de Bolivia recibe esta guia para su proceso de admision y distribucion.</td>
        </tr>
        <tr>
            <td colspan="8"></td>
        </tr>
        <tr>
            <td colspan="3">Firma remitente</td>
            <td colspan="3">Recibido por Correos de Bolivia</td>
            <td colspan="2">Firma destinatario</td>
        </tr>
        <tr>
            <td colspan="8"></td>
        </tr>
        <tr>
            <td colspan="8"></td>
        </tr>
        @if(!$loop->last)
            <tr>
                <td colspan="8"></td>
            </tr>
            <tr>
                <td colspan="8"></td>
            </tr>
        @endif
    @endforeach
</table>
