@extends('adminlte::page')
@section('title', 'Crear Bitacora')
@section('template_title')
    Bitacoras
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
                        <span class="card-title">Crear Bitacora</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('bitacoras.store') }}" role="form" enctype="multipart/form-data">
                            @csrf
                            @include('bitacoras.form', ['editOnlyPhoto' => false])
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
