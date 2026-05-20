@extends('adminlte::page')

@section('content')
<div class="container-fluid bp-gestiones-module">
    <livewire:maintenance-log-manager wire:poll.7s.keep-alive />
</div>
@endsection
