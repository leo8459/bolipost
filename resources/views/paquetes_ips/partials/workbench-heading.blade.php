<header class="ips-workbench-heading">
    <span class="ips-workbench-icon" aria-hidden="true"><i class="fas {{ $workbenchMode === 'delivery' ? 'fa-hand-holding-heart' : 'fa-dolly' }}"></i></span>
    <div class="ips-workbench-heading-content">
        <span class="ips-workbench-mode">{{ $workbenchMode === 'delivery' ? 'ESTÁS EN ENTREGA' : 'ESTÁS EN RECEPCIÓN' }}</span>
        <h1>{{ $workbenchMode === 'delivery' ? ($courierOnly ? 'Reparto y entrega al destinatario' : 'Entregar paquetes al destinatario') : (($workflowAction ?? '') === 'EMG' ? 'Recibir paquetes en oficina' : $pageTitle) }}</h1>
        <p>{{ $workbenchMode === 'delivery' ? ($courierOnly ? 'Gestiona el reparto y confirma la entrega final cuando el destinatario reciba el paquete.' : 'Entrega los paquetes que ya recibiste en esta oficina. La baja se registra cuando el destinatario recibe el envío.') : (($workflowAction ?? '') === 'EMG' ? 'Esta vista registra únicamente la recepción física. Después, entrega los paquetes desde Almacén → Por entregar.' : $pageDescription) }}</p>
    </div>
    @if($link)
        <div class="ips-workbench-office"><i class="fas fa-building" aria-hidden="true"></i><span><small>{{ empty($combinedOffices) ? 'OFICINA DE TRABAJO' : 'OFICINAS DE TRABAJO' }}</small><strong>{{ empty($combinedOffices) ? ($link->ips_office_name ?: 'Oficina '.$link->ips_office_cd) : 'La Paz · '.count($combinedOffices).' oficinas' }}</strong></span></div>
    @endif
</header>
