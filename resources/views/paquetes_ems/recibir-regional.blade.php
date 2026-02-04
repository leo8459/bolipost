@extends('adminlte::page')
@section('title', 'Recibir de regional')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'transito_ems'])
@endsection
