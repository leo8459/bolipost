@extends('adminlte::page')
@section('title', 'Crear tarifas de contrato')
@section('template_title')
    Tarifa Contrato
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-default tarifa-sheet-card">
                    <div class="card-header">
                        <h1 class="card-title font-weight-bold"><i class="fas fa-table mr-2" aria-hidden="true"></i>Crear tarifas de contrato</h1>
                    </div>
                    <div class="card-body">
                        @if (collect($defaults ?? [])->except('tarifario_contrato_id')->contains(fn ($value) => $value !== null && $value !== ''))
                            <div class="alert alert-info">
                                La primera fila está precargada. Revisa sus datos antes de guardar.
                                @aclcan('create', null, 'tarifa-contrato')
                                <a href="{{ route('tarifa-contrato.create', ['reset' => 1, 'tarifario_id' => $defaults['tarifario_contrato_id'] ?? request('tarifario_id')]) }}" class="btn btn-sm btn-outline-secondary ml-2">
                                    Limpiar precarga
                                </a>
                                @endaclcan
                            </div>
                        @endif
                        @if (!empty($copySource))
                            <div class="alert alert-warning">
                                Usamos la tarifa #{{ $copySource->id }} como base para completar la primera fila.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('tarifa-contrato.store') }}" id="tarifas-sheet-form">
                            @csrf
                            @include('tarifa_contrato.spreadsheet')
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
