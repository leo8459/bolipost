<nav class="d-flex flex-wrap align-items-center mb-3" aria-label="Recepción y entrega de paquetes">
    @foreach([
        ['label' => '1. Recibir paquetes', 'route' => 'internacional.section', 'params' => ['section' => 'recepcion-oficina'], 'permission' => \App\Support\InternationalMenuAcl::SUBMENU_RECEIVE_DESTINATION, 'active' => ($workflowAction ?? '') === 'EMG'],
        ['label' => '2. Entregar paquetes recibidos', 'route' => 'internacional.warehouse', 'params' => ['warehouseView' => 'por-entregar'], 'permission' => \App\Support\InternationalMenuAcl::SUBMENU_PENDING_DELIVERY, 'active' => $warehouseView === 'por-entregar'],
    ] as $step)
        @if(\App\Support\InternationalMenuAcl::userCanAccess(auth()->user(), $step['permission']))
            <a class="btn {{ $step['active'] ? 'btn-primary' : 'btn-outline-primary' }} mr-2 mb-2" href="{{ route($step['route'], $step['params'] + (empty($combinedOffices) ? ['office_cd' => $link?->ips_office_cd] : [])) }}" @if($step['active']) aria-current="page" @endif>{{ $step['label'] }}</a>
        @else
            <span class="text-muted mr-3 mb-2">{{ $step['label'] }}</span>
        @endif
    @endforeach
</nav>
