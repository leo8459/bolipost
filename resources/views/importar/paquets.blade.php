@extends('adminlte::page')
@section('title', 'Importar Paquets')
@section('template_title')
    Importar Paquets
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">IMPORTAR PAQUETS</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            Modulo listo para integrar importacion masiva de paquets.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection

