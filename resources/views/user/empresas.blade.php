@extends('adminlte::page')
@section('title', 'Usuarios empresas')
@section('template_title')
    Usuarios empresas
@endsection

@section('content')
@livewire('users', ['empresaMode' => true])
@endsection
