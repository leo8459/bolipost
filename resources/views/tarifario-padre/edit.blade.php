@extends('adminlte::page')
@section('title', 'Editar tarifario padre')
@section('content')
    <section class="content container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Editar tarifario padre</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('tarifario-padre.update', $tarifarioPadre) }}">
                    @csrf
                    @method('PUT')
                    @include('tarifario-padre.form')
                </form>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
