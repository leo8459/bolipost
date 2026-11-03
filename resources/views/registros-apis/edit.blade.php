@extends('adminlte::page')

@section('title', 'Editar API')

@section('content_header')
    <h1>Editar API</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Editar registro</h3></div>
        <form method="POST" action="{{ route('registros-apis.update', $registroApi) }}">
            @method('PUT')
            @include('registros-apis._form')
        </form>
    </div>
    @include('footer')
@endsection
