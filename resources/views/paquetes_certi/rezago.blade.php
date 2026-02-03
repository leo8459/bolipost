@extends('adminlte::page')
@section('title', 'Paquetes Certificados - Rezago')
@section('template_title')
    Paquetes Certificados - Rezago
@endsection

@section('content')
@livewire('paquete-certi', ['mode' => 'rezago'])
@endsection
