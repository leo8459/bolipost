@extends('adminlte::page')
@section('title', 'Eventos CERTI')
@section('template_title')
    Eventos CERTI
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'certi'])
@endsection

