<?php

namespace App\Services;

use App\Models\AlertaEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AlertaEmpresaService
{
    public function siguienteNoLeida(?User $user): ?AlertaEmpresa
    {
        if (! $user || ! $user->empresa_id || ! Schema::hasTable('alertas_empresa')) {
            return null;
        }

        return AlertaEmpresa::query()
            ->whereHas('empresas', fn ($query) => $query->whereKey($user->empresa_id))
            ->where('publicada_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('vence_at')->orWhere('vence_at', '>=', now()))
            ->whereDoesntHave('lectores', fn ($query) => $query->whereKey($user->id))
            ->latest('publicada_at')
            ->first();
    }
}
