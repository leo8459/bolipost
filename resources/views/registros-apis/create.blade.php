@extends('adminlte::page')

@section('title', 'Registrar API')

@section('content_header')
    <h1>Registrar API</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Nueva API</h3></div>
        <form method="POST" action="{{ route('registros-apis.store') }}">
            @include('registros-apis._form')
        </form>
    </div>
    @include('footer')
@endsection
