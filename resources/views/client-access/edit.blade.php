@extends('adminlte::page')

@section('title', 'Accesos Clientes')

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @includeif('partials.errors')

                <div class="mb-3">
                    <h1 class="h3 mb-1">Editar accesos de cliente</h1>
                    <p class="text-muted mb-0">{{ $cliente->name }} - {{ $cliente->email }}</p>
                </div>

                <form method="POST" action="{{ route('client-access.update', $cliente->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="card card-outline card-warning">
                        <div class="card-body">
                            <div class="alert alert-warning">
                                Selecciona los roles del portal cliente que tendra esta cuenta.
                            </div>

                            <div class="row">
                                @foreach ($availableRoles as $role)
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox mb-3">
                                            <input
                                                type="checkbox"
                                                class="custom-control-input"
                                                id="client_role_{{ $role->id }}"
                                                name="roles[]"
                                                value="{{ $role->name }}"
                                                {{ in_array($role->name, old('roles', $selectedRoles), true) ? 'checked' : '' }}
                                            >
                                            <label class="custom-control-label" for="client_role_{{ $role->id }}">
                                                {{ $role->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning">Guardar accesos</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
