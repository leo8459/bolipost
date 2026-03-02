@extends('adminlte::page')
@section('title', 'Eventos EMS')
@section('template_title')
    Eventos EMS
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'ems'])
@endsection
