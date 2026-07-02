@extends('adminlte::page')

@section('title', 'Perfil')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="mb-1">Perfil</h1>
            <p class="text-muted mb-0">Administra tus datos de acceso y cambia tu contrasena cuando lo necesites.</p>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Datos de la cuenta</h3>
                </div>
                <form method="post" action="{{ route('profile.update') }}">
                    @csrf
                    @method('patch')

                    <div class="card-body">
                        @if (session('status') === 'profile-updated')
                            <div class="alert alert-success">
                                Los datos del perfil fueron actualizados correctamente.
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="name">Nombre</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $user->name) }}"
                                required
                                autocomplete="name"
                            >
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="email">Correo electronico</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email) }}"
                                required
                                autocomplete="username"
                            >
                            @error('email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">Cambiar contrasena</h3>
                </div>
                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="card-body">
                        @if (session('status') === 'password-updated')
                            <div class="alert alert-success">
                                La contrasena fue actualizada correctamente.
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="current_password">Contrasena actual</label>
                            <input
                                id="current_password"
                                name="current_password"
                                type="password"
                                class="form-control @if($errors->updatePassword->has('current_password')) is-invalid @endif"
                                autocomplete="current-password"
                            >
                            @if($errors->updatePassword->has('current_password'))
                                <span class="invalid-feedback d-block">{{ $errors->updatePassword->first('current_password') }}</span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="password">Nueva contrasena</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="form-control @if($errors->updatePassword->has('password')) is-invalid @endif"
                                autocomplete="new-password"
                            >
                            @if($errors->updatePassword->has('password'))
                                <span class="invalid-feedback d-block">{{ $errors->updatePassword->first('password') }}</span>
                            @endif
                            <small class="form-text text-muted">
                                Usa una contrasena larga y dificil de adivinar.
                            </small>
                        </div>

                        <div class="form-group mb-0">
                            <label for="password_confirmation">Confirmar nueva contrasena</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="form-control @if($errors->updatePassword->has('password_confirmation')) is-invalid @endif"
                                autocomplete="new-password"
                            >
                            @if($errors->updatePassword->has('password_confirmation'))
                                <span class="invalid-feedback d-block">{{ $errors->updatePassword->first('password_confirmation') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                        <span class="text-muted small">Este cambio se aplica inmediatamente a tu cuenta.</span>
                        <button type="submit" class="btn btn-warning">
                            Actualizar contrasena
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
