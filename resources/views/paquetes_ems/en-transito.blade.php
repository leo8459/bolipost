@extends('adminlte::page')
@section('title', 'Paquetes EMS - En transito')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'en_transito_ems'])
@endsection
