@extends('adminlte::page')
@section('title', 'Paquetes Ordinarios - Despacho')
@section('template_title')
    Paquetes Ordinarios - Despacho
@endsection

@section('content')
@livewire('paquetes-ordi', ['mode' => 'despacho'])
@endsection
