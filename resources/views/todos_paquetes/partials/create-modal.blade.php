@php
    $departmentOptions = collect($departamentos)->mapWithKeys(fn ($item) => [$item => $item])->all();
    $stateOptions = $estados->mapWithKeys(fn ($item) => [$item->id => $item->nombre_estado])->all();
    $serviceOptions = $servicios->mapWithKeys(fn ($item) => [$item->id => $item->nombre_servicio])->all();
    $windowOptions = $ventanillas->mapWithKeys(fn ($item) => [$item->id => $item->nombre_ventanilla])->all();
    $companyOptions = $empresas->mapWithKeys(fn ($item) => [$item->id => $item->nombre])->all();

    $forms = [
        'ems' => [
            'title' => 'Paquete EMS',
            'fields' => [
                'codigo' => ['label' => 'Codigo', 'required' => true],
                'cod_especial' => ['label' => 'Codigo especial / CN-33'],
                'origen' => ['label' => 'Origen', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'ciudad' => ['label' => 'Destino', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'tipo_correspondencia' => ['label' => 'Tipo de correspondencia'],
                'servicio_especial' => ['label' => 'Servicio especial'],
                'contenido' => ['label' => 'Contenido', 'type' => 'textarea'],
                'cantidad' => ['label' => 'Cantidad', 'type' => 'number', 'default' => 1, 'step' => 1],
                'peso' => ['label' => 'Peso', 'type' => 'number', 'step' => '0.001'],
                'precio' => ['label' => 'Precio', 'type' => 'number', 'step' => '0.01'],
                'nombre_remitente' => ['label' => 'Remitente'],
                'nombre_envia' => ['label' => 'Nombre de quien envia'],
                'carnet' => ['label' => 'Carnet'],
                'telefono_remitente' => ['label' => 'Telefono remitente'],
                'nombre_destinatario' => ['label' => 'Destinatario'],
                'telefono_destinatario' => ['label' => 'Telefono destinatario'],
                'direccion' => ['label' => 'Direccion', 'type' => 'textarea'],
                'referencia' => ['label' => 'Referencia', 'type' => 'textarea'],
                'observacion' => ['label' => 'Observacion', 'type' => 'textarea'],
                'justificacion' => ['label' => 'Justificacion', 'type' => 'textarea'],
                'estado_id' => ['label' => 'Estado', 'type' => 'select', 'options' => $stateOptions],
            ],
        ],
        'contrato' => [
            'title' => 'Paquete de contrato',
            'fields' => [
                'codigo' => ['label' => 'Codigo', 'required' => true],
                'codigo_madre' => ['label' => 'Codigo madre'],
                'cod_especial' => ['label' => 'Codigo especial / CN-33'],
                'origen' => ['label' => 'Origen', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'destino' => ['label' => 'Destino', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'provincia_origen' => ['label' => 'Provincia de origen'],
                'provincia' => ['label' => 'Provincia de destino'],
                'empresa_id' => ['label' => 'Empresa', 'type' => 'select', 'options' => $companyOptions],
                'nombre_r' => ['label' => 'Remitente', 'required' => true],
                'telefono_r' => ['label' => 'Telefono remitente', 'required' => true],
                'direccion_r' => ['label' => 'Direccion remitente', 'type' => 'textarea', 'required' => true],
                'nombre_d' => ['label' => 'Destinatario', 'required' => true],
                'telefono_d' => ['label' => 'Telefono destinatario'],
                'direccion_d' => ['label' => 'Direccion destinatario', 'type' => 'textarea', 'required' => true],
                'contenido' => ['label' => 'Contenido', 'type' => 'textarea', 'required' => true],
                'cantidad' => ['label' => 'Cantidad', 'type' => 'number', 'default' => 1, 'step' => 1],
                'peso' => ['label' => 'Peso', 'type' => 'number', 'step' => '0.001', 'required' => true],
                'precio' => ['label' => 'Precio', 'type' => 'number', 'step' => '0.01'],
                'mapa' => ['label' => 'Enlace de mapa'],
                'observacion' => ['label' => 'Observacion', 'type' => 'textarea'],
                'justificacion' => ['label' => 'Justificacion', 'type' => 'textarea'],
                'estados_id' => ['label' => 'Estado', 'type' => 'select', 'options' => $stateOptions],
            ],
        ],
        'certi' => [
            'title' => 'Paquete certificado',
            'fields' => [
                'codigo' => ['label' => 'Codigo', 'required' => true],
                'cod_especial' => ['label' => 'Codigo especial / CN-33'],
                'destinatario' => ['label' => 'Destinatario', 'required' => true],
                'telefono' => ['label' => 'Telefono', 'type' => 'number'],
                'cuidad' => ['label' => 'Destino', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'zona' => ['label' => 'Zona'],
                'peso' => ['label' => 'Peso', 'type' => 'number', 'step' => '0.001', 'required' => true],
                'precio' => ['label' => 'Precio', 'type' => 'number', 'step' => '0.01'],
                'tipo' => ['label' => 'Tipo', 'default' => 'CERTIFICADO', 'required' => true],
                'aduana' => ['label' => 'Aduana', 'type' => 'select', 'options' => ['NO' => 'NO', 'SI' => 'SI'], 'default' => 'NO', 'required' => true],
                'servicio_id' => ['label' => 'Servicio predefinido', 'type' => 'select', 'options' => $serviceOptions],
                'fk_ventanilla' => ['label' => 'Ventanilla', 'type' => 'select', 'options' => $windowOptions, 'required' => true],
                'fk_estado' => ['label' => 'Estado', 'type' => 'select', 'options' => $stateOptions, 'required' => true],
                'observaciones' => ['label' => 'Observaciones', 'type' => 'textarea'],
            ],
        ],
        'ordi' => [
            'title' => 'Paquete ordinario',
            'fields' => [
                'codigo' => ['label' => 'Codigo', 'required' => true],
                'cod_especial' => ['label' => 'Codigo especial / CN-33'],
                'destinatario' => ['label' => 'Destinatario', 'required' => true],
                'telefono' => ['label' => 'Telefono', 'required' => true],
                'ciudad' => ['label' => 'Destino', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'zona' => ['label' => 'Zona', 'required' => true],
                'peso' => ['label' => 'Peso', 'type' => 'number', 'step' => '0.001', 'required' => true],
                'precio' => ['label' => 'Precio', 'type' => 'number', 'step' => '0.01'],
                'tipo' => ['label' => 'Tipo', 'default' => 'ORDINARIO'],
                'pais' => ['label' => 'Pais', 'default' => 'BOLIVIA'],
                'iso' => ['label' => 'ISO', 'default' => 'BO'],
                'aduana' => ['label' => 'Aduana', 'type' => 'select', 'options' => ['NO' => 'NO', 'SI' => 'SI'], 'default' => 'NO', 'required' => true],
                'servicio_id' => ['label' => 'Servicio predefinido', 'type' => 'select', 'options' => $serviceOptions],
                'fk_ventanilla' => ['label' => 'Ventanilla', 'type' => 'select', 'options' => $windowOptions, 'required' => true],
                'fk_estado' => ['label' => 'Estado', 'type' => 'select', 'options' => $stateOptions, 'required' => true],
                'factura' => ['label' => 'Factura'],
                'manifiesto' => ['label' => 'Manifiesto'],
                'observaciones' => ['label' => 'Observaciones', 'type' => 'textarea'],
            ],
        ],
        'solicitud' => [
            'title' => 'Solicitud de cliente',
            'fields' => [
                'codigo_solicitud' => ['label' => 'Codigo de solicitud'],
                'barcode' => ['label' => 'Codigo de barras'],
                'cod_especial' => ['label' => 'Codigo especial / CN-33'],
                'origen' => ['label' => 'Origen', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'ciudad' => ['label' => 'Destino', 'type' => 'select', 'options' => $departmentOptions, 'required' => true],
                'tipo_correspondencia' => ['label' => 'Tipo de correspondencia'],
                'servicio_especial' => ['label' => 'Servicio especial'],
                'servicio_id' => ['label' => 'Servicio predefinido', 'type' => 'select', 'options' => $serviceOptions],
                'contenido' => ['label' => 'Contenido', 'type' => 'textarea', 'required' => true],
                'cantidad' => ['label' => 'Cantidad', 'type' => 'number', 'default' => 1, 'step' => 1, 'required' => true],
                'peso' => ['label' => 'Peso', 'type' => 'number', 'step' => '0.001'],
                'precio' => ['label' => 'Precio', 'type' => 'number', 'step' => '0.01'],
                'nombre_remitente' => ['label' => 'Remitente', 'required' => true],
                'nombre_envia' => ['label' => 'Nombre de quien envia'],
                'carnet' => ['label' => 'Carnet', 'required' => true],
                'telefono_remitente' => ['label' => 'Telefono remitente'],
                'nombre_destinatario' => ['label' => 'Destinatario', 'required' => true],
                'telefono_destinatario' => ['label' => 'Telefono destinatario'],
                'direccion_recojo' => ['label' => 'Direccion de recojo', 'type' => 'textarea'],
                'direccion' => ['label' => 'Direccion de entrega', 'type' => 'textarea', 'required' => true],
                'estado_id' => ['label' => 'Estado', 'type' => 'select', 'options' => $stateOptions],
                'pago_destinatario' => ['label' => 'El destinatario paga', 'type' => 'checkbox'],
                'observacion' => ['label' => 'Observacion', 'type' => 'textarea'],
                'justificacion' => ['label' => 'Justificacion', 'type' => 'textarea'],
            ],
        ],
    ];

    $selectedCreateType = old('package_type', '');
    $createCloseUrl = route('todos-paquetes.index', request()->except(['create']));
@endphp

<div class="modal fade {{ $showCreateModal ? 'show' : '' }}" id="createPackageModal" tabindex="-1" role="dialog"
     style="{{ $showCreateModal ? 'display:block;' : '' }}" aria-hidden="{{ $showCreateModal ? 'false' : 'true' }}">
    <div class="modal-dialog modal-xl tp-create-dialog" role="document">
        <div class="modal-content tp-modal">
            <form method="POST" action="{{ route('todos-paquetes.store') }}" id="createPackageForm" class="tp-create-form">
                @csrf
                @foreach(request()->except(['create']) as $key => $value)
                    @if(is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Crear nuevo</h5>
                        <div class="small text-white-50">Selecciona el servicio para mostrar el formulario correspondiente.</div>
                    </div>
                    <a href="{{ $createCloseUrl }}" class="close text-white" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </a>
                </div>
                <div class="modal-body">
                    <div class="form-group tp-service-picker">
                        <label for="packageType" class="font-weight-bold">Tipo de servicio <span class="text-danger">*</span></label>
                        <select name="package_type" id="packageType" class="form-control form-control-lg @error('package_type') is-invalid @enderror" required>
                            <option value="">Seleccione un servicio...</option>
                            @foreach($types as $typeKey => $typeConfig)
                                <option value="{{ $typeKey }}" @selected($selectedCreateType === $typeKey)>{{ $typeConfig['label'] }}</option>
                            @endforeach
                        </select>
                        @error('package_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="createFormPrompt" class="tp-create-prompt">
                        <i class="fas fa-hand-pointer"></i>
                        <span>Elige un tipo de servicio para comenzar.</span>
                    </div>

                    @foreach($forms as $formType => $form)
                        <section class="tp-dynamic-form d-none" data-package-form="{{ $formType }}">
                            <div class="tp-form-heading">
                                <i class="fas fa-box-open"></i>
                                <span>{{ $form['title'] }}</span>
                            </div>
                            <div class="row">
                                @foreach($form['fields'] as $field => $fieldConfig)
                                    @php
                                        $fieldType = $fieldConfig['type'] ?? 'text';
                                        $fieldValue = old($field, $fieldConfig['default'] ?? '');
                                        $isWide = in_array($fieldType, ['textarea'], true);
                                    @endphp
                                    <div class="{{ $isWide ? 'col-12' : 'col-12 col-md-6' }} mb-3">
                                        @if($fieldType === 'checkbox')
                                            <div class="custom-control custom-switch mt-4">
                                                <input type="hidden" name="{{ $field }}" value="0" data-form-control>
                                                <input type="checkbox" name="{{ $field }}" value="1" id="{{ $formType }}_{{ $field }}"
                                                       class="custom-control-input" data-form-control @checked((bool) $fieldValue)>
                                                <label class="custom-control-label font-weight-bold" for="{{ $formType }}_{{ $field }}">{{ $fieldConfig['label'] }}</label>
                                            </div>
                                        @else
                                            <label for="{{ $formType }}_{{ $field }}" class="small font-weight-bold">
                                                {{ $fieldConfig['label'] }}
                                                @if(!empty($fieldConfig['required']))<span class="text-danger">*</span>@endif
                                            </label>
                                            @if($fieldType === 'select')
                                                <select name="{{ $field }}" id="{{ $formType }}_{{ $field }}"
                                                        class="form-control @error($field) is-invalid @enderror"
                                                        data-form-control @required(!empty($fieldConfig['required']))>
                                                    <option value="">Seleccione...</option>
                                                    @foreach($fieldConfig['options'] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($fieldType === 'textarea')
                                                <textarea name="{{ $field }}" id="{{ $formType }}_{{ $field }}" rows="3"
                                                          class="form-control @error($field) is-invalid @enderror"
                                                          data-form-control @required(!empty($fieldConfig['required']))>{{ $fieldValue }}</textarea>
                                            @else
                                                <input type="{{ $fieldType }}" name="{{ $field }}" id="{{ $formType }}_{{ $field }}"
                                                       value="{{ $fieldValue }}" min="{{ $fieldType === 'number' ? '0' : '' }}"
                                                       step="{{ $fieldConfig['step'] ?? '' }}"
                                                       class="form-control @error($field) is-invalid @enderror"
                                                       data-form-control @required(!empty($fieldConfig['required']))>
                                            @endif
                                            @error($field)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <a href="{{ $createCloseUrl }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success" id="createPackageSubmit" disabled>
                        <i class="fas fa-save mr-1"></i> Crear registro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($showCreateModal)
    <div class="modal-backdrop fade show"></div>
@endif
