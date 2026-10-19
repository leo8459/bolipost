<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $note->document_type }} · VN local #{{ $note->id }}</title>
    <style>
        *{box-sizing:border-box}
        body{color:#1d3554;font:14px/1.45 Arial,sans-serif;margin:0;background:#eef2f7}
        .toolbar{display:flex;gap:10px;justify-content:center;padding:16px}
        .toolbar button,.toolbar a{background:#205493;border:0;border-radius:5px;color:#fff;cursor:pointer;padding:10px 15px;text-decoration:none}
        .sheet{background:#fff;border:1px solid #ccd6e2;margin:0 auto 24px;max-width:900px;padding:34px}
        .warning{background:#fff4ce;border:1px solid #e6bd36;border-radius:5px;color:#624c00;margin:16px 0;padding:12px}
        h1{font-size:23px;margin:0 0 4px}
        h2{border-bottom:2px solid #d9e2ee;font-size:15px;margin:24px 0 12px;padding-bottom:7px}
        .muted{color:#68809c}
        .grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
        .field{border:1px solid #d8e1eb;border-radius:4px;min-height:56px;padding:9px 11px;white-space:pre-wrap}
        .field small{color:#68809c;display:block;font-size:11px;margin-bottom:4px;text-transform:uppercase}
        .field.full{grid-column:1/-1}
        .code{font-size:22px;font-weight:bold;overflow-wrap:anywhere}
        .footer{border-top:1px solid #d8e1eb;color:#71849c;font-size:11px;margin-top:24px;padding-top:12px}
        @media print{
            body{background:#fff;font-size:12px}
            .toolbar{display:none}
            .sheet{border:0;margin:0;max-width:none;padding:12mm}
            .warning{print-color-adjust:exact;-webkit-print-color-adjust:exact}
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('internacional.sacas', ['paso' => 'abrir', 'q' => $note->receptacle_code]) }}">Volver a la saca</a>
    </div>
    <main class="sheet">
        <div class="muted">CORREOS DE BOLIVIA · GESTIÓN POSTAL INTERNACIONAL</div>
        <h1>Preparación de Nota de Verificación {{ $note->document_type }}</h1>
        <div class="muted">Registro local #{{ $note->id }} · {{ $note->statusLabel() }}</div>
        <div class="warning"><strong>Documento de trabajo.</strong> Esta ficha se guarda en Bolipost y no es el formulario oficial CP 78/CN 43 ni confirma su transmisión al operador de origen.</div>

        <section class="grid">
            <div class="field"><small>S9 · Saca / receptáculo</small><div class="code">{{ $note->receptacle_code }}</div></div>
            <div class="field"><small>S8 · Despacho declarado</small>{{ $note->dispatch_code ?: 'No informado por IPS; cotejar con documento de despacho' }}</div>
            <div class="field"><small>Documento UPU que corresponde</small>{{ $note->document_type }} · Código de irregularidad {{ $note->summary_code ?? '—' }}</div>
            <div class="field"><small>Tipo de incidencia</small>{{ $note->typeLabel() }}</div>
            <div class="field"><small>S10 · Marbete del paquete</small>{{ $note->package_code ?: 'No aplica a esta incidencia' }}</div>
            <div class="field"><small>Oficina IPS</small>{{ $note->office_cd }}</div>
            <div class="field"><small>Registrada</small>{{ $note->created_at->timezone('America/La_Paz')->format('d/m/Y H:i') }} · {{ $note->reporter?->name ?: 'Usuario no disponible' }}</div>
            <div class="field"><small>Fuente del cotejo</small>{{ match($note->comparison_source) { 'ips' => 'Lista asociada en IPS', 'dispatch_document' => 'Documento de despacho', 'other' => 'Otra verificación documentada', default => 'No aplica' }}{{ $note->comparison_reference ? ' · '.$note->comparison_reference : '' }}</div>
            <div class="field"><small>Cantidad declarada / encontrada</small>{{ $note->declared_count ?? 'Sin dato' }} / {{ $note->observed_count ?? 'Sin dato' }}</div>
            <div class="field full"><small>Descripción observada</small>{{ $note->description }}</div>
            <div class="field"><small>Estado exterior y cierre</small>{{ $note->outer_condition ?: 'Sin detalle adicional' }}</div>
            <div class="field"><small>Estado del contenido</small>{{ $note->contents_condition ?: 'Sin detalle adicional' }}</div>
            @if($note->cn24_required)
                <div class="field full"><small>Acción complementaria</small>Preparar CN 24: se indicó que el contenido pudo haberse extraído o dañado.</div>
            @endif
        </section>

        <h2>Evidencia adjunta</h2>
        <p>{{ count($note->evidence_paths ?? []) }} archivo(s) guardado(s) en el expediente local. Consulte los adjuntos desde la pantalla de la saca.</p>

        <h2>Transmisión y respuesta</h2>
        <div class="grid">
            <div class="field"><small>Estado</small>{{ $note->statusLabel() }}</div>
            <div class="field"><small>N.º VN / canal / fecha</small>{{ $note->external_vn_number ?: 'Pendiente' }}{{ $note->transmission_method ? ' · '.$note->transmission_method : '' }}{{ $note->sent_at ? ' · '.$note->sent_at->timezone('America/La_Paz')->format('d/m/Y') : '' }}</div>
            @if($note->resolution)<div class="field full"><small>Resolución recibida</small>{{ $note->resolution }}</div>@endif
        </div>

        <div class="footer">La apertura de la saca y los eventos individuales de los paquetes se registran por separado en IPS. Esta ficha conserva la incidencia y sus evidencias mientras se completa la transmisión formal.</div>
    </main>
</body>
</html>
