@extends('adminlte::page')
@section('title', 'Crear regla local')
@section('template_title')
    Crear regla local
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Nueva regla de tracking local</span>
                    </div>
                    <form method="POST" action="{{ route('tracking-local-event-rules.store') }}">
                        @include('tracking-local-event-rules._form')
                    </form>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
