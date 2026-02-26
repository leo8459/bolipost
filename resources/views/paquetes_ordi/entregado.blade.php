@extends('adminlte::page')
@section('title', 'Paquetes Ordinarios - Entregado')
@section('template_title')
    Paquetes Ordinarios - Entregado
@endsection

@section('content')
@livewire('paquetes-ordi', ['mode' => 'entregado'])
@endsection
