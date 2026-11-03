@extends('adminlte::page')

@section('logout_url', 'clientes/logout')

@section('content_top_nav_right')
    @if (session()->has('impersonator_cliente_admin_id'))
        <li class="nav-item impersonation-navbar-item" title="Estas usando el perfil de {{ auth('cliente')->user()?->name }}">
            <span class="impersonation-navbar-label">
                <i class="fas fa-user-secret mr-1"></i>
                Vista como <strong class="impersonation-navbar-user">{{ auth('cliente')->user()?->name }}</strong>
            </span>
            <form method="POST" action="{{ route('clientes-admin.impersonate.stop') }}">
                @csrf
                <button type="submit" class="btn btn-dark btn-sm text-nowrap" title="Volver a tu cuenta de administrador">
                    <i class="fas fa-undo-alt mr-1"></i> Administrador
                </button>
            </form>
        </li>
    @endif
@stop

