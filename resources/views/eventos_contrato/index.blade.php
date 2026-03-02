@extends('adminlte::page')
@section('title', 'Eventos CONTRATO')
@section('template_title')
    Eventos CONTRATO
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'contrato'])
@endsection
