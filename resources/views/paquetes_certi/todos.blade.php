@extends('adminlte::page')
@section('title', 'Paquetes Certificados - Todos')
@section('template_title')
    Paquetes Certificados - Todos
@endsection

@section('content')
@livewire('paquete-certi', ['mode' => 'todos'])
@endsection
