@if($link && count($link->offices()) > 1)
    <section class="card mb-3" aria-label="Oficinas IPS asignadas">
        <div class="card-body py-3">
            <strong>Trabajando en {{ $link->ips_office_name ?: 'Oficina '.$link->ips_office_cd }}</strong>
            <p class="small text-muted mb-2">Puedes abrir varias oficinas a la vez. Cada pestaña conserva su propia bandeja y sus operaciones pendientes.</p>
            <div class="d-flex flex-wrap" style="gap: .5rem">
                @php
                    $officeRoute = request()->route()?->getName() ?: 'ips.index';
                    $officeRouteParameters = request()->route()?->parameters() ?? [];
                @endphp
                @foreach($link->offices() as $assignedOffice)
                    <a class="btn btn-sm {{ $assignedOffice['office_cd'] === (int) $link->ips_office_cd ? 'btn-primary' : 'btn-outline-primary' }}"
                       href="{{ route($officeRoute, $officeRouteParameters + ['office_cd' => $assignedOffice['office_cd']]) }}"
                       target="_blank" rel="noopener"
                       aria-label="Abrir {{ $assignedOffice['office_name'] ?: 'oficina '.$assignedOffice['office_cd'] }} en otra pestaña">
                        {{ $assignedOffice['office_name'] ?: 'Oficina '.$assignedOffice['office_cd'] }}
                        @if($assignedOffice['office_fcd']) · {{ $assignedOffice['office_fcd'] }} @endif
                        <i class="fas fa-external-link-alt ml-1" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
