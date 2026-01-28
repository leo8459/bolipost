@extends('adminlte::page')
@section('title', 'Usuarios')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('plantilla')
{{-- @hasrole ('ADMISION')
@livewire('iniciar')
@endhasrole --}}
@include('footer')
@endsection
