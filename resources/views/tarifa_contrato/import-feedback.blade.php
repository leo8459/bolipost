@if ($message = session('success'))
    <div class="alert {{ $successClass ?? 'alert-success' }}" role="status">
        <p class="mb-0">{{ $message }}</p>
    </div>
@endif

@if ($message = session('warning'))
    <div class="alert alert-warning" role="status">
        <p class="mb-0">{{ $message }}</p>
    </div>
@endif

@if (session()->has('import_errors'))
    <div class="alert alert-danger" role="alert">
        <p class="mb-2"><strong>Errores de importación (hasta 20)</strong></p>
        <ul class="mb-0">
            @foreach (session('import_errors', []) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
