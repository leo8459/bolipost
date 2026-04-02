@extends('adminlte::page')

@section('title', 'Roles Clientes')

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @includeif('partials.errors')

                <div class="mb-3">
                    <h1 class="h3 mb-1">Crear rol de cliente</h1>
                    <p class="text-muted mb-0">Gestiona permisos solo para el portal cliente.</p>
                </div>

                <form method="POST" action="{{ route('client-roles.store') }}">
                    @csrf
                    @include('client-role.form')
                </form>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
