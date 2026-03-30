@extends('adminlte::page')
@section('title', 'Crear Tarifa Contrato')
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

                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">Crear Tarifa de Contrato</span>
                    </div>
                    <div class="card-body">
                        @if (!empty($defaults))
                            <div class="alert alert-info">
                                Modo rapido activo: se precargaron datos del ultimo registro.
                                @aclcan('create', null, 'tarifa-contrato')
                                <a href="{{ route('tarifa-contrato.create', ['reset' => 1]) }}" class="btn btn-sm btn-outline-secondary ml-2">
                                    Limpiar precarga
                                </a>
                                @endaclcan
                            </div>
                        @endif
                        @if (!empty($copySource))
                            <div class="alert alert-warning">
                                Copiando base desde tarifa #{{ $copySource->id }}.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('tarifa-contrato.store') }}" role="form">
                            @csrf
                            @include('tarifa_contrato.form', ['isCreate' => true])
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
