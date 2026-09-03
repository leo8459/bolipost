<?php

namespace App\Services;

use App\Models\AlertaEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AlertaEmpresaService
{
    public function siguienteNoLeida(?User $user): ?AlertaEmpresa
    {
        if (! $user || ! Schema::hasTable('alertas_empresa')) {
            return null;
        }

        return AlertaEmpresa::query()
            ->where(function ($query) use ($user): void {
                if ($user->empresa_id) {
                    $query->whereHas('empresas', fn ($empresaQuery) => $empresaQuery->whereKey($user->empresa_id));

                    return;
                }

                $query->whereHas('usuariosDestinatarios', fn ($userQuery) => $userQuery->whereKey($user->id));
            })
            ->whereNotNull('aprobada_at')
            ->where('publicada_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('vence_at')->orWhere('vence_at', '>=', now()))
            ->whereDoesntHave('lectores', fn ($query) => $query->whereKey($user->id))
            ->latest('publicada_at')
            ->first();
    }
}
