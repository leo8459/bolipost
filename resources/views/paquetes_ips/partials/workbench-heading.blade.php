<header class="ips-workbench-heading">
    <span class="ips-workbench-icon" aria-hidden="true"><i class="fas {{ $workbenchMode === 'delivery' ? 'fa-hand-holding-heart' : 'fa-dolly' }}"></i></span>
    <div class="ips-workbench-heading-content">
        <span class="ips-workbench-mode">{{ $workbenchMode === 'delivery' ? 'ESTÁS EN ENTREGA' : 'ESTÁS EN RECEPCIÓN' }}</span>
        <h1>{{ $workbenchMode === 'delivery' ? ($courierOnly ? 'Reparto y entrega al destinatario' : 'Entregar paquetes al destinatario') : (($workflowAction ?? '') === 'EMG' ? 'Recibir paquetes en oficina' : $pageTitle) }}</h1>
        <p>{{ $workbenchMode === 'delivery' ? 'Gestiona el retiro o reparto de los paquetes. Confirma la entrega final cuando el destinatario los reciba.' : (($workflowAction ?? '') === 'EMG' ? 'Confirma la llegada física de los paquetes a esta oficina y prepara el siguiente lote.' : $pageDescription) }}</p>
    </div>
    @if($link)
        <div class="ips-workbench-office"><i class="fas fa-building" aria-hidden="true"></i><span><small>OFICINA DE TRABAJO</small><strong>{{ $link->ips_office_name ?: 'Oficina '.$link->ips_office_cd }}</strong></span></div>
    @endif
</header>
