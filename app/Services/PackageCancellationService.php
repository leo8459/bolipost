<?php

namespace App\Services;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Model;

class PackageCancellationService
{
    public function cancel(Model $package, string $stateColumn): bool
    {
        $cancelledStateId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');

        if (! $cancelledStateId) {
            return false;
        }

        $package->forceFill([$stateColumn => (int) $cancelledStateId])->save();

        return true;
    }
}
