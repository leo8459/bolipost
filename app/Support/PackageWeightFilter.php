<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

class PackageWeightFilter
{
    public static function apply(Builder $query, ?float $minimum, ?float $maximum): Builder
    {
        return $query
            ->when($minimum !== null, fn (Builder $query) => $query->whereRaw("CAST(NULLIF(TRIM(peso), '') AS DECIMAL) >= ?", [$minimum]))
            ->when($maximum !== null, fn (Builder $query) => $query->whereRaw("CAST(NULLIF(TRIM(peso), '') AS DECIMAL) <= ?", [$maximum]));
    }
}
