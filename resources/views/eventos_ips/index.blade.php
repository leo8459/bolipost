@extends('adminlte::page')
@section('title', 'Eventos IPS')

@section('content_header')
<div class="track-title">
    <div><span>SEGUIMIENTO INTERNACIONAL</span><h1>Eventos IPS</h1><p>Consulta dónde está tu envío y revisa su recorrido paso a paso.</p></div>
    <i class="fas fa-globe-americas"></i>
</div>
@endsection

@section('content')
@php
    $value = fn ($a, $b = null, $empty = 'No disponible') => filled($a) ? $a : (filled($b) ? $b : $empty);
    $date = function ($date, $format = 'd/m/Y · H:i') {
        if (!filled($date)) return 'Sin fecha';
        try { return \Illuminate\Support\Carbon::parse($date)->format($format); } catch (\Throwable $e) { return $date; }
    };
    $eventLook = function ($name) {
        $name = mb_strtolower((string) $name);
        if (str_contains($name, 'entregar') || str_contains($name, 'entregado')) return ['green','fa-check'];
        if (str_contains($name, 'aduana') || str_contains($name, 'custom')) return ['amber','fa-landmark'];
        if (str_contains($name, 'devolver') || str_contains($name, 'retener')) return ['red','fa-exclamation'];
        if (str_contains($name, 'enviado') || str_contains($name, 'saca')) return ['purple','fa-paper-plane'];
        if (str_contains($name, 'recibir') || str_contains($name, 'recibido')) return ['blue','fa-box-open'];
        return ['cyan','fa-map-marker-alt'];
    };
    $countryFlag = function ($countryCode) {
        $countryCode = strtoupper(trim((string) $countryCode));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) return '🌐';
        return mb_chr(127397 + ord($countryCode[0]), 'UTF-8').mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    };
    $latest = $eventos->first();
    $originCode = $value(data_get($package,'origen.codigo'), data_get($tracking,'meta.origin_country_code'), '--');
    $originName = $value(data_get($package,'origen.nombre'), data_get($tracking,'origen'));
    $destinationCode = $value(data_get($package,'destino.codigo'), data_get($tracking,'meta.destination_country_code'), '--');
    $destinationName = $value(data_get($package,'destino.nombre'), data_get($tracking,'destino'));
@endphp

<div class="track-page">
    <section class="search-box">
        <div class="search-help"><i class="fas fa-barcode"></i><span><strong>¿Cuál es tu código de seguimiento?</strong><small>Ejemplo: LX050606084NL</small></span></div>
        <form method="GET" action="{{ route('eventos-ips.index') }}">
            <label><i class="fas fa-search"></i><input name="codigo" type="search" value="{{ $codigo }}" maxlength="100" placeholder="Escribe el código aquí" autocomplete="off" autofocus></label>
            <button>Rastrear envío</button>
            @if($codigo !== '')<a href="{{ route('eventos-ips.index') }}" title="Nueva búsqueda"><i class="fas fa-times"></i></a>@endif
        </form>
    </section>

    @if($error)
        <div class="error-box"><i class="fas fa-wifi"></i><div><strong>No pudimos consultar IPS</strong><p>{{ $error }}</p></div></div>
    @elseif($codigo === '')
        <section class="welcome-box">
            <div class="welcome-art"><i class="fas fa-map-marker-alt"></i><span></span><b><i class="fas fa-box"></i></b><span></span><i class="fas fa-map-marker-alt"></i></div>
            <h2>Sigue tu envío de forma sencilla</h2><p>Primero verás lo más importante y debajo cada movimiento ordenado por fecha.</p>
            <div class="steps"><span><b>1</b> Escribe el código</span><i class="fas fa-chevron-right"></i><span><b>2</b> Revisa el estado</span><i class="fas fa-chevron-right"></i><span><b>3</b> Consulta el recorrido</span></div>
        </section>
    @elseif($tracking)
        <section class="shipment-card">
            <header>
                <div><small>CÓDIGO DE SEGUIMIENTO</small><h2>{{ $value(data_get($package,'codigo'),data_get($tracking,'codigo')) }}</h2><span><i class="fas fa-shipping-fast"></i> {{ ucfirst($value(data_get($package,'tipo_servicio'),data_get($tracking,'tipo_servicio'))) }}</span></div>
                <div class="latest"><small>ÚLTIMO MOVIMIENTO</small><strong>{{ $latest ? $value(data_get($latest,'eventType')) : 'Sin eventos registrados' }}</strong>@if($latest)<span><i class="far fa-clock"></i> {{ $date(data_get($latest,'eventDate')) }}</span>@endif</div>
            </header>

            <div class="route-box">
                <div class="place"><b><span class="country-flag">{{ $countryFlag($originCode) }}</span><em>{{ $originCode }}</em></b><span><small>Origen</small><strong>{{ $originName }}</strong></span></div>
                <div class="journey"><i></i><span></span><b class="fas fa-plane"></b><span></span><i></i></div>
                <div class="place destination"><b><span class="country-flag">{{ $countryFlag($destinationCode) }}</span><em>{{ $destinationCode }}</em></b><span><small>Destino</small><strong>{{ $destinationName }}</strong></span></div>
            </div>

            <div class="facts">
                <div><i class="fas fa-weight-hanging"></i><span><small>Peso</small><strong>{{ is_numeric(data_get($package,'peso')) ? number_format((float)data_get($package,'peso'),3,',','.').' kg' : 'No disponible' }}</strong></span></div>
                <div><i class="fas fa-envelope"></i><span><small>Clase de correo</small><strong>{{ $value(data_get($package,'clase_correo')) }}</strong></span></div>
                <div><i class="fas fa-calendar-alt"></i><span><small>Registrado</small><strong>{{ $date(data_get($package,'fecha_registro'),'d/m/Y H:i') }}</strong></span></div>
                <div><i class="fas fa-list-ol"></i><span><small>Movimientos</small><strong>{{ $eventos->count() }} evento(s)</strong></span></div>
            </div>

            <details class="all-data"><summary><i class="fas fa-info-circle"></i> Ver todos los datos del envío <i class="fas fa-chevron-down"></i></summary>
                <div>
                    <span><small>Código S10</small><strong>{{ $value(data_get($package,'codigo_s10')) }}</strong></span><span><small>Identificador IPS</small><strong>{{ $value(data_get($package,'mailitm_pid')) }}</strong></span>
                    <span><small>Número de despacho</small><strong>{{ $value(data_get($package,'numero_despacho')) }}</strong></span><span><small>Estado postal</small><strong>{{ $value(data_get($package,'estado_postal')) }}</strong></span>
                    <span><small>Contenido</small><strong>{{ $value(data_get($package,'contenido')) }}</strong></span><span><small>Teléfono principal</small><strong>{{ $value(data_get($package,'telefono')) }}</strong></span>
                    <span><small>Teléfono remitente</small><strong>{{ $value(data_get($package,'telefonos.remitente')) }}</strong></span><span><small>Teléfono destinatario</small><strong>{{ $value(data_get($package,'telefonos.destinatario')) }}</strong></span>
                </div>
            </details>
        </section>

        <section class="timeline-card">
            <header><div><i class="fas fa-route"></i><span><h3>Recorrido del envío</h3><p>Del movimiento más reciente al más antiguo.</p></span></div><b>{{ $eventos->count() }} evento(s)</b></header>
            <div class="timeline">
                @forelse($eventos as $index => $evento)
                    @php([$tone,$icon] = $eventLook(data_get($evento,'eventType')))
                    <article class="event {{ $index === 0 ? 'current' : '' }}">
                        <div class="rail"><b class="{{ $tone }}"><i class="fas {{ $icon }}"></i></b></div>
                        <div class="event-body">
                            <div class="event-head"><span>@if($index===0)<small>ESTADO ACTUAL</small>@endif<h4>{{ $value(data_get($evento,'eventType')) }}</h4></span><time><i class="far fa-calendar"></i> {{ $date(data_get($evento,'eventDate')) }}</time></div>
                            <p class="location"><i class="fas fa-map-marker-alt"></i> {{ $value(data_get($evento,'office')) }}</p>
                            @if(filled(data_get($evento,'condition')) || filled(data_get($evento,'nextOffice')) || filled(data_get($evento,'detail')))
                                <div class="notes">
                                    @if(filled(data_get($evento,'condition')))<p><i class="fas fa-check-circle"></i><span><small>Condición</small>{{ data_get($evento,'condition') }}</span></p>@endif
                                    @if(filled(data_get($evento,'nextOffice')))<p><i class="fas fa-arrow-right"></i><span><small>Siguiente oficina</small>{{ data_get($evento,'nextOffice') }}</span></p>@endif
                                    @if(filled(data_get($evento,'detail')))<p><i class="fas fa-align-left"></i><span><small>Detalle</small>{{ data_get($evento,'detail') }}</span></p>@endif
                                </div>
                            @endif
                            <details class="technical"><summary>Información técnica</summary><div><span><small>Registrado por</small>{{ $value(data_get($evento,'scanned')) }}</span><span><small>Estación</small>{{ $value(data_get($evento,'workstation')) }}</span><span><small>ID IPS</small>{{ $value(data_get($evento,'mailitM_PID')) }}</span></div></details>
                        </div>
                    </article>
                @empty
                    <div class="no-events"><i class="far fa-folder-open"></i><h3>Aún no hay movimientos</h3><p>IPS no devolvió eventos para <strong>{{ $codigo }}</strong>.</p></div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection

@section('css')
<style>
:root{--blue:#20539a;--navy:#17233c;--muted:#64748b;--line:#dde6f0}.content-wrapper{background:#edf2f7}.track-page{max-width:1180px;margin:auto;padding-bottom:25px}.track-title{display:flex;align-items:center;justify-content:space-between}.track-title span{color:#2970b8;font-size:.68rem;font-weight:900;letter-spacing:.13em}.track-title h1{margin:3px 0;color:var(--navy);font-size:1.8rem;font-weight:900}.track-title p{margin:0;color:var(--muted);font-size:.9rem}.track-title>i{padding:15px;border-radius:15px;background:#dbeafe;color:var(--blue);font-size:1.3rem}
.search-box{display:grid;grid-template-columns:.8fr 1.2fr;align-items:center;gap:22px;margin-bottom:18px;padding:19px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 8px 24px #0f172a0f}.search-help{display:flex;align-items:center;gap:12px}.search-help>i{padding:13px;border-radius:11px;background:#eaf1fb;color:var(--blue)}.search-help strong,.search-help small{display:block}.search-help strong{color:var(--navy);font-size:.88rem}.search-help small{color:var(--muted);font-size:.74rem}.search-box form{display:flex;gap:8px}.search-box label{position:relative;flex:1;margin:0}.search-box label i{position:absolute;top:16px;left:15px;color:#8998aa}.search-box input{width:100%;height:48px;padding:0 14px 0 42px;border:2px solid #d9e3ef;border-radius:12px;color:var(--navy);font-weight:800;text-transform:uppercase;outline:0}.search-box input:focus{border-color:#4a82c4;box-shadow:0 0 0 4px #20539a1a}.search-box input::placeholder{font-weight:500;text-transform:none}.search-box button{padding:0 20px;border:0;border-radius:12px;background:var(--blue);color:#fff;font-weight:800;white-space:nowrap}.search-box form>a{width:48px;display:flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:12px;color:var(--muted)}
.welcome-box{padding:60px 20px;border:1px solid var(--line);border-radius:18px;background:#fff;text-align:center;box-shadow:0 10px 30px #0f172a0d}.welcome-art{display:flex;align-items:center;justify-content:center;margin-bottom:20px;color:#4b83c2}.welcome-art span{width:60px;border-top:3px dashed #bfd1e7}.welcome-art b{width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:17px;background:var(--blue);color:#fff;font-size:1.3rem;box-shadow:0 9px 20px #20539a40}.welcome-box h2{color:var(--navy);font-size:1.3rem;font-weight:900}.welcome-box>p{color:var(--muted)}.steps{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:22px;color:#51627a;font-size:.78rem;font-weight:700}.steps span b{display:inline-flex;width:25px;height:25px;align-items:center;justify-content:center;margin-right:5px;border-radius:50%;background:#eaf1fb;color:var(--blue)}.steps>i{color:#b6c3d3;font-size:.6rem}.error-box{display:flex;align-items:center;gap:14px;padding:18px;border:1px solid #fecaca;border-radius:14px;background:#fff1f2;color:#9f1239}.error-box>i{padding:12px;border-radius:11px;background:#ffe4e6}.error-box p{margin:2px 0 0;font-size:.8rem}
.shipment-card,.timeline-card{overflow:hidden;margin-bottom:18px;border-radius:18px;background:#fff;box-shadow:0 12px 34px #0f172a14}.shipment-card>header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:23px;background:linear-gradient(120deg,#173f78,#2764aa);color:#fff}.shipment-card header small{color:#cfe2fb;font-size:.64rem;font-weight:900}.shipment-card header h2{margin:4px 0 8px;font-size:1.6rem;font-weight:900;letter-spacing:.04em}.shipment-card header>div>span{font-size:.72rem}.latest{max-width:410px;padding:13px 15px;border:1px solid #ffffff33;border-radius:12px;background:#0a1e4133}.latest small,.latest strong,.latest span{display:block}.latest strong{margin:4px 0;font-size:.86rem}.latest span{color:#dbeafe!important}
.route-box{display:grid;grid-template-columns:.8fr 1.4fr .8fr;align-items:center;gap:18px;padding:25px 30px;border-bottom:1px solid #e7edf5;background:#f8fafc}.place{display:flex;align-items:center;gap:11px}.place>b{width:58px;height:58px;display:flex;align-items:center;justify-content:center;flex:none;flex-direction:column;border:1px solid #cbd9e9;border-radius:50%;background:#fff;box-shadow:0 5px 14px #0f172a12}.country-flag{font-family:"Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif;font-size:1.45rem;line-height:1}.place>b em{margin-top:2px;color:var(--blue);font-size:.55rem;font-style:normal;font-weight:900;letter-spacing:.08em}.place small,.place strong{display:block}.place small{color:#8190a4;font-size:.62rem;font-weight:900;text-transform:uppercase}.place strong{color:var(--navy);font-size:.78rem}.destination{justify-content:flex-end;text-align:right}.destination>b{order:2}.journey{display:flex;align-items:center;color:#3e75b5}.journey span{flex:1;border-top:2px dashed #9ebbdc}.journey>i{width:9px;height:9px;border:2px solid #3e75b5;border-radius:50%}.journey>b{margin:0 9px;transform:rotate(8deg)}
.facts{display:grid;grid-template-columns:repeat(4,1fr);padding:20px 24px}.facts>div{min-width:0;display:flex;align-items:center;gap:10px;padding:3px 16px;border-right:1px solid #e6edf5}.facts>div:first-child{padding-left:0}.facts>div:last-child{border:0}.facts>div>i{padding:10px;border-radius:10px;background:#eaf1fb;color:var(--blue)}.facts small,.facts strong{display:block}.facts small,.all-data small,.technical small{color:#8290a5;font-size:.6rem;font-weight:900;text-transform:uppercase}.facts strong{color:#34435a;font-size:.76rem;overflow-wrap:anywhere}details summary{cursor:pointer;list-style:none}details summary::-webkit-details-marker{display:none}.all-data{border-top:1px solid #e7edf5}.all-data>summary{padding:14px 24px;color:var(--blue);font-size:.76rem;font-weight:800}.all-data>summary i:last-child{float:right}.all-data[open]>summary i:last-child{transform:rotate(180deg)}.all-data>div{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:0 24px 22px}.all-data span{min-width:0;padding:10px;border-radius:8px;background:#f5f8fc}.all-data small,.all-data strong{display:block}.all-data strong{color:#43516a;font-size:.72rem;overflow-wrap:anywhere}
.timeline-card>header{display:flex;align-items:center;justify-content:space-between;padding:18px 23px;border-bottom:1px solid #e4ebf3}.timeline-card>header>div{display:flex;align-items:center;gap:10px}.timeline-card>header>div>i{padding:12px;border-radius:11px;background:#eaf1fb;color:var(--blue)}.timeline-card h3{margin:0;color:var(--navy);font-size:1rem;font-weight:900}.timeline-card header p{margin:2px 0 0;color:var(--muted);font-size:.72rem}.timeline-card header>b{padding:6px 10px;border-radius:999px;background:#edf4fc;color:var(--blue);font-size:.7rem}.timeline{padding:24px 28px 7px}.event{display:grid;grid-template-columns:44px 1fr}.rail{position:relative;display:flex;justify-content:center}.rail:after{content:"";position:absolute;top:37px;bottom:-12px;left:21px;border-left:2px solid #dce6f1}.event:last-child .rail:after{display:none}.rail>b{z-index:2;width:35px;height:35px;display:flex;align-items:center;justify-content:center;border:4px solid #fff;border-radius:50%;color:#fff;font-size:.68rem;box-shadow:0 0 0 2px #dce6f1}.green{background:#10b981}.amber{background:#f59e0b}.red{background:#ef4444}.purple{background:#8b5cf6}.blue{background:#2563eb}.cyan{background:#0891b2}.event-body{margin:0 0 17px 11px;padding:16px 18px;border:1px solid #e0e8f1;border-radius:13px}.current .event-body{border-color:#a9c7e7;background:linear-gradient(100deg,#f5f9ff,#fff);box-shadow:0 6px 18px #20539a12}.event-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.event-head span>small{display:inline-block;margin-bottom:4px;padding:3px 7px;border-radius:999px;background:#dbeafe;color:#1d4f91;font-size:.56rem;font-weight:900}.event-head h4{margin:0;color:var(--navy);font-size:.88rem;font-weight:900}.event-head time{flex:none;color:#66768d;font-size:.7rem;font-weight:700}.location{margin:9px 0 0;color:#52647d;font-size:.75rem;font-weight:700}.location i{margin-right:5px;color:#e05252}.notes{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:11px;padding:10px 12px;border-radius:9px;background:#f7f9fc}.notes p{min-width:180px;flex:1;display:flex;gap:7px;margin:0;color:#4a5b72;font-size:.71rem}.notes p>i{margin-top:3px;color:#4c79b2}.notes small,.notes span{display:block}.technical{margin-top:9px}.technical>summary{color:#78879a;font-size:.64rem;font-weight:700}.technical>div{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:7px}.technical span{padding:8px;border:1px dashed #dbe3ed;border-radius:7px;color:#64748b;font-size:.62rem;overflow-wrap:anywhere}.technical small{display:block}.no-events{padding:45px;text-align:center;color:var(--muted)}.no-events>i{font-size:1.4rem}.no-events h3{color:var(--navy);font-size:1rem;font-weight:900}
@media(max-width:992px){.search-box{grid-template-columns:1fr}.facts{grid-template-columns:repeat(2,1fr);gap:15px}.facts>div:nth-child(2){border:0}.all-data>div{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.track-title>i{display:none}.search-box form{flex-wrap:wrap}.search-box label{flex-basis:100%}.search-box button{height:46px;flex:1}.steps{flex-direction:column}.steps>i{display:none}.shipment-card>header{align-items:flex-start;flex-direction:column}.latest{width:100%;max-width:none}.route-box{grid-template-columns:1fr 35px 1fr;gap:6px;padding:19px 13px}.place{display:block}.place>b{margin-bottom:6px}.destination>b{margin-left:auto}.journey{transform:rotate(90deg)}.journey>b{margin:0 4px}.facts{grid-template-columns:1fr;padding:15px}.facts>div{padding:10px 0;border-right:0;border-bottom:1px solid #e7edf5}.facts>div:last-child{border:0}.all-data>div{grid-template-columns:1fr}.timeline{padding:19px 11px 5px}.event{grid-template-columns:36px 1fr}.rail:after{left:17px}.rail>b{width:30px;height:30px}.event-body{margin-left:6px;padding:13px}.event-head{flex-direction:column;gap:6px}.notes{display:block}.notes p{margin-bottom:9px}.technical>div{grid-template-columns:1fr}}
</style>
@endsection
