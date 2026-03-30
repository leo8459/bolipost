@extends('adminlte::page')
@section('title', 'Todos los Paquetes Ordinarios')
@section('template_title')
    Todos los Paquetes Ordinarios
@endsection

@section('content')
@livewire('paquetes-ordi', ['mode' => 'todos'])
@endsection
