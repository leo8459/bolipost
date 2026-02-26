@extends('adminlte::page')
@section('title', 'Paquetes Ordinarios - Rezago')
@section('template_title')
    Paquetes Ordinarios - Rezago
@endsection

@section('content')
@livewire('paquetes-ordi', ['mode' => 'rezago'])
@endsection
