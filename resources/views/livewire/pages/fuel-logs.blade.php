@extends('adminlte::page')

@section('content')
<div class="container-fluid bp-gestiones-module">
    <livewire:fuel-log-manager wire:poll.5s.keep-alive />
</div>
@endsection
