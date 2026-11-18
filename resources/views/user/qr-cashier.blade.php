<div class="card card-default" id="caja-qr">
    <div class="card-header">
        <h2 class="card-title">Caja QR de Qhantuy</h2>
    </div>
    <div class="card-body">
        @if(session('qr_cashier_success'))
            <div class="alert alert-success" role="status">{{ session('qr_cashier_success') }}</div>
        @endif
        @if(session('qr_cashier_error'))
            <div class="alert alert-danger" role="alert">{{ session('qr_cashier_error') }}</div>
        @endif

        @if($qrCashier['context'] === null)
            <p class="mb-0">Guarda una sucursal de facturación con punto de venta para asignar una caja QR.</p>
        @else
            <p class="mb-2">
                Sucursal {{ $qrCashier['context']['branch_code'] }} · Punto de venta {{ $qrCashier['context']['point_sale_id'] }}
            </p>

            @if($qrCashier['error'] !== null)
                <div class="alert alert-warning mb-0" role="alert">{{ $qrCashier['error'] }}</div>
            @else
                <p class="mb-2">
                    <strong>{{ $qrCashier['configured'] ? 'Caja asignada' : 'Caja general' }}</strong>
                </p>

                <form method="POST" action="{{ route('users.qr-cashier.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="qr_cashier_context" value="{{ $qrCashier['context_hash'] }}">

                    <div class="form-group">
                        <label for="qhantuy_qr_appkey">{{ $qrCashier['configured'] ? 'Nuevo appkey de Qhantuy' : 'Appkey de Qhantuy' }}</label>
                        <input
                            type="password"
                            id="qhantuy_qr_appkey"
                            name="qhantuy_qr_appkey"
                            class="form-control @error('qhantuy_qr_appkey') is-invalid @enderror"
                            maxlength="500"
                            autocomplete="new-password"
                            required
                        >
                        @error('qhantuy_qr_appkey')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">La asignación se aplica a los nuevos cobros QR de este punto de venta.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        {{ $qrCashier['configured'] ? 'Reemplazar appkey' : 'Guardar caja QR' }}
                    </button>
                </form>

                @if($qrCashier['configured'])
                    <form method="POST" action="{{ route('users.qr-cashier.destroy', $user) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="qr_cashier_context" value="{{ $qrCashier['context_hash'] }}">
                        <button type="submit" class="btn btn-outline-secondary">Usar caja general para nuevos cobros</button>
                    </form>
                @endif
            @endif
        @endif
    </div>
</div>
