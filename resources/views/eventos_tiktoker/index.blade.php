@extends('adminlte::page')
@section('title', 'Eventos TIKTOKER')
@section('template_title')
    Eventos TIKTOKER
@endsection

@section('content')
    @livewire('eventos-tabla', ['tipo' => 'tiktoker'])
@endsection
