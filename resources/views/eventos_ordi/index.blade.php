@extends('adminlte::page')
@section('title', 'Eventos ORDI')
@section('template_title')
    Eventos ORDI
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'ordi'])
@endsection

