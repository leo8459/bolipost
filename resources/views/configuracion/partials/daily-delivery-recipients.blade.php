@php
    $formId = $national ? 'national' : 'regional';
    $restoreInput = (old('scope') === $nationalScope) === $national;
@endphp

<section class="card {{ $national ? 'card-primary' : 'card-info' }} card-outline" aria-labelledby="{{ $formId }}-title">
    <div class="card-header">
        <h2 id="{{ $formId }}-title" class="card-title">
            <i class="fas {{ $national ? 'fa-globe-americas' : 'fa-map-marked-alt' }} mr-2" aria-hidden="true"></i>
            {{ $national ? 'Destinatarios nacionales' : 'Destinatarios por regional' }}
        </h2>
        <a href="#{{ $formId }}-add" class="float-right small">Agregar correo</a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            {{ $national ? 'Cada correo de esta lista recibe el consolidado de todas las regionales.' : 'Cada correo recibe el reporte de su regional. Los encargados EMS se incorporan desde sus cuentas de Bolipost.' }}
        </p>

        @if ($groups === [])
            <p class="text-muted">Todavía no hay destinatarios {{ $national ? 'nacionales' : 'regionales' }}.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            @unless ($national)
                                <th scope="col">Regional</th>
                            @endunless
                            <th scope="col">Destinatario</th>
                            @unless ($national)
                                <th scope="col">Origen</th>
                            @endunless
                            <th scope="col" class="text-right"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    @foreach ($groups as $scope => $addresses)
                        <tbody>
                            @foreach ($addresses as $email => $recipient)
                                <tr>
                                    @if (! $national && $loop->first)
                                        <th scope="rowgroup" rowspan="{{ count($addresses) }}">{{ $scope }}</th>
                                    @endif
                                    <td class="text-break">
                                        @if ($recipient['name'] !== '')
                                            <span class="d-block font-weight-bold">{{ $recipient['name'] }}</span>
                                        @endif
                                        {{ $email }}
                                    </td>
                                    @unless ($national)
                                        <td>
                                            @if ($recipient['role'])
                                                <span class="badge badge-light border">Encargado EMS</span>
                                            @endif
                                            @if ($recipient['manual'])
                                                <span class="badge badge-info">Manual</span>
                                            @endif
                                        </td>
                                    @endunless
                                    <td class="text-right">
                                        @if ($recipient['manual'])
                                            <form method="POST" action="{{ route('daily-deliveries-email.recipients.destroy') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="hidden" name="recipient" value="{{ $email }}">
                                                <button class="btn btn-outline-danger btn-sm" title="Quitar destinatario manual" aria-label="Quitar {{ $email }} de {{ $scope }}">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif

        <form id="{{ $formId }}-add" method="POST" action="{{ route('daily-deliveries-email.recipients.store') }}" class="border-top pt-3 mt-3">
            @csrf
            <h3 class="h6 mb-3">{{ $national ? 'Agregar correo nacional' : 'Agregar correo regional' }}</h3>
            <div class="form-row align-items-end">
                @if ($national)
                    <input type="hidden" name="scope" value="{{ $nationalScope }}">
                @else
                    <div class="form-group col-sm-4 col-xl-3">
                        <label for="regional-scope">Regional</label>
                        <select id="regional-scope" name="scope" class="form-control" required>
                            <option value="">Seleccione</option>
                            @foreach ($regionals as $regional)
                                <option value="{{ $regional }}" @selected(old('scope') === $regional)>{{ $regional }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group {{ $national ? 'col-12' : 'col-sm-8 col-xl-3' }}">
                    <label for="{{ $formId }}-name">Nombre <span class="text-muted small">(opcional)</span></label>
                    <input id="{{ $formId }}-name" name="name" class="form-control" maxlength="120" value="{{ $restoreInput ? old('name') : '' }}">
                </div>
                <div class="form-group {{ $national ? 'col-12' : 'col-sm-8 col-xl-4' }}">
                    <label for="{{ $formId }}-email">Correo electrónico</label>
                    <input type="email" id="{{ $formId }}-email" name="recipient" class="form-control" maxlength="254" value="{{ $restoreInput ? old('recipient') : '' }}" required>
                </div>
                <div class="form-group {{ $national ? 'col-12' : 'col-sm-4 col-xl-2' }}">
                    <button class="btn {{ $national ? 'btn-primary' : 'btn-info' }} btn-block">
                        <i class="fas fa-plus mr-1" aria-hidden="true"></i>Agregar
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>
