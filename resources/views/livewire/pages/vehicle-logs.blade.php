@extends('adminlte::page')

@section('content')
<div class="container-fluid bp-gestiones-module">
    <livewire:vehicle-log-manager wire:poll.7s.keep-alive />
</div>
@endsection
