<nav class="ips-warehouse-nav" aria-label="Vistas del almacén internacional">
    @foreach($warehouseTabs as $tab)
        <a href="{{ route('internacional.warehouse', \App\Support\IpsOfficeContext::parameters(request(), $tab['params'])) }}" class="{{ $warehouseView === $tab['key'] ? 'active' : '' }}" @if($warehouseView === $tab['key']) aria-current="page" @endif>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
