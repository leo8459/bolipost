@extends('adminlte::page')

@section('title', 'Roles Clientes')

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @includeif('partials.errors')

                <div class="mb-3">
                    <h1 class="h3 mb-1">Editar rol de cliente</h1>
                    <p class="text-muted mb-0">Ajusta permisos del portal cliente sin tocar el panel interno.</p>
                </div>

                <form method="POST" action="{{ route('client-roles.update', $role->id) }}">
                    @csrf
                    @method('PUT')
                    @include('client-role.form')
                </form>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
