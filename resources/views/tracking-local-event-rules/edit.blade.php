@extends('adminlte::page')
@section('title', 'Editar regla local')
@section('template_title')
    Editar regla local
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Editar regla de tracking local</span>
                    </div>
                    <form method="POST" action="{{ route('tracking-local-event-rules.update', $rule) }}">
                        @method('PUT')
                        @include('tracking-local-event-rules._form')
                    </form>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
