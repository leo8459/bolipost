@extends('adminlte::page')
@section('title', 'Editar Tarifario Tiktoker')
@section('template_title')
    Tarifario Tiktoker
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
                        <span class="card-title">Editar Tarifario Tiktoker</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('tarifario-tiktoker.update', $tarifa->id) }}" role="form">
                            @csrf
                            @method('PUT')
                            @include('tarifario_tiktoker.form')
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
