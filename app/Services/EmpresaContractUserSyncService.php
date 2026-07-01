<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Collection;

class EmpresaContractUserSyncService
{
    public function syncExpiredUsers(): void
    {
        $today = Carbon::today()->toDateString();

        $expiredCompanyIds = Empresa::query()
            ->whereNotNull('fin_contrato')
            ->whereDate('fin_contrato', '<', $today)
            ->pluck('id');

        if ($expiredCompanyIds->isNotEmpty()) {
            User::query()
                ->whereIn('empresa_id', $expiredCompanyIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'auto_baja_empresa_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $activeCompanyIds = Empresa::query()
            ->whereNotNull('fin_contrato')
            ->whereDate('fin_contrato', '>=', $today)
            ->pluck('id');

        if ($activeCompanyIds->isNotEmpty()) {
            User::withTrashed()
                ->whereIn('empresa_id', $activeCompanyIds)
                ->whereNotNull('auto_baja_empresa_at')
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null,
                    'auto_baja_empresa_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function syncCompanyById(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $isExpired = !empty($empresa->fin_contrato) && Carbon::parse($empresa->fin_contrato)->lt(Carbon::parse($today));

        if ($isExpired) {
            User::query()
                ->where('empresa_id', $empresa->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'auto_baja_empresa_at' => now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        User::withTrashed()
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('auto_baja_empresa_at')
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'auto_baja_empresa_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function ensureAuthenticatedUserIsActive(?AuthenticatableContract $user): bool
    {
        if (! $user instanceof User || (int) ($user->empresa_id ?? 0) <= 0) {
            return true;
        }

        $empresa = Empresa::query()->find((int) $user->empresa_id);
        if (! $empresa || empty($empresa->fin_contrato)) {
            return true;
        }

        $today = Carbon::today();
        $expired = Carbon::parse($empresa->fin_contrato)->lt($today);

        if (! $expired) {
            return true;
        }

        if ($user->trashed()) {
            return false;
        }

        User::query()
            ->whereKey($user->id)
            ->update([
                'deleted_at' => now(),
                'auto_baja_empresa_at' => now(),
                'updated_at' => now(),
            ]);

        return false;
    }

    public function buildExpirationAlertsForUser(?AuthenticatableContract $user): Collection
    {
        if (! $user instanceof User) {
            return collect();
        }

        $today = Carbon::today();
        $limitDate = $today->copy()->addDays(90);

        if ($this->isAdminUser($user)) {
            return Empresa::query()
                ->whereNotNull('fin_contrato')
                ->whereDate('fin_contrato', '>=', $today->toDateString())
                ->whereDate('fin_contrato', '<=', $limitDate->toDateString())
                ->orderBy('fin_contrato')
                ->get()
                ->map(fn (Empresa $empresa) => $this->mapEmpresaAlert($empresa, true, $today))
                ->values();
        }

        if (! $this->isEmpresaUser($user) || (int) ($user->empresa_id ?? 0) <= 0) {
            return collect();
        }

        $empresa = Empresa::query()->find((int) $user->empresa_id);
        if (! $empresa || empty($empresa->fin_contrato)) {
            return collect();
        }

        $finContrato = Carbon::parse($empresa->fin_contrato)->startOfDay();
        if ($finContrato->lt($today) || $finContrato->gt($limitDate)) {
            return collect();
        }

        return collect([$this->mapEmpresaAlert($empresa, false, $today)]);
    }

    private function mapEmpresaAlert(Empresa $empresa, bool $includeCompanyName, Carbon $today): array
    {
        $finContrato = Carbon::parse($empresa->fin_contrato)->startOfDay();
        $daysLeft = (int) $today->diffInDays($finContrato, false);
        $leadText = $daysLeft <= 0
            ? 'vence hoy'
            : ($daysLeft <= 30 ? 'esta por vencer en ' . $daysLeft . ' dia(s)' : 'vence en ' . $daysLeft . ' dia(s)');

        return [
            'empresa' => (string) ($empresa->nombre ?? 'EMPRESA'),
            'fin_contrato' => $finContrato->format('d/m/Y'),
            'days_left' => $daysLeft,
            'message' => $includeCompanyName
                ? 'La empresa ' . (string) ($empresa->nombre ?? 'EMPRESA') . ' ' . $leadText . ' (' . $finContrato->format('d/m/Y') . ').'
                : 'Su contrato ' . $leadText . ' (' . $finContrato->format('d/m/Y') . ').',
        ];
    }

    private function isAdminUser(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('administrador') || $user->hasRole('admin');
    }

    private function isEmpresaUser(User $user): bool
    {
        return $user->hasRole('empresa');
    }
}
