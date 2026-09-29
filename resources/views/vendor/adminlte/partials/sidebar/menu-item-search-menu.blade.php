<li class="sidebar-menu-search-item">
    <div class="form-inline my-2">
        <div class="input-group" data-sidebar-menu-filter>
            <input class="form-control form-control-sidebar" type="search"
                @isset($item['id']) id="{{ $item['id'] }}" @endisset
                placeholder="{{ $item['text'] }}"
                aria-label="{{ $item['text'] }}"
                autocomplete="off">

            <div class="input-group-append">
                <button class="btn btn-sidebar" type="button" data-sidebar-menu-filter-focus
                    aria-label="Buscar en el menú" title="Buscar en el menú">
                    <i class="fas fa-fw fa-search" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</li>
