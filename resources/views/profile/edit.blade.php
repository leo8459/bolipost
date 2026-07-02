@extends('adminlte::page')

@section('title', 'Perfil')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="mb-1">Cambiar contraseña</h1>
            <p class="text-muted mb-0">Actualiza tu contraseña de acceso de forma segura.</p>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">Cambiar contraseña</h3>
                </div>
                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="card-body">
                        @if (session('status') === 'password-updated')
                            <div class="alert alert-success">
                                La contraseña fue actualizada correctamente.
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="current_password">Contraseña actual</label>
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
                            <label for="password">Nueva contraseña</label>
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
                                Usa una contraseña larga y difícil de adivinar.
                            </small>
                        </div>

                        <div class="form-group mb-0">
                            <label for="password_confirmation">Confirmar nueva contraseña</label>
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
                            Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
