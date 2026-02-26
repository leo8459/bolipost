@extends('adminlte::page')
@section('title', 'Ventanilla EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'ventanilla_ems'])
@endsection
