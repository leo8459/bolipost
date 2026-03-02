@extends('adminlte::page')
@section('title', 'Eventos Despacho')
@section('template_title')
    Eventos Despacho
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'despacho'])
@endsection

