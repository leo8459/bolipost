@extends('adminlte::page')
@section('title', 'Eventos Delivery Express')
@section('template_title')
    Eventos Delivery Express
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'tiktoker'])
@endsection
