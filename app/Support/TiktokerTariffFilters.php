<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TiktokerTariffFilters
{
    private const PRICE_COLUMNS = [
        'precio_peso1' => 'peso1',
        'precio_peso2' => 'peso2',
        'precio_peso3' => 'peso3',
        'precio_peso_extra' => 'peso_extra',
    ];

    public static function fromQuery(array $query): array
    {
        $search = $query['q'] ?? '';
        $filters = [
            'q' => is_scalar($search) ? trim((string) $search) : '',
            'origen_id' => max(0, (int) filter_var($query['origen_id'] ?? 0, FILTER_VALIDATE_INT)),
            'destino_id' => max(0, (int) filter_var($query['destino_id'] ?? 0, FILTER_VALIDATE_INT)),
        ];

        foreach (array_keys(self::PRICE_COLUMNS) as $filter) {
            $filters[$filter] = self::normalizePrice($query[$filter] ?? null);
        }

        return $filters;
    }

    public static function apply(Builder $query, array $filters): Builder
    {
        foreach (['origen_id', 'destino_id'] as $column) {
            if ($filters[$column] > 0) {
                $query->where($column, $filters[$column]);
            }
        }

        foreach (self::PRICE_COLUMNS as $filter => $column) {
            if ($filters[$filter] !== null) {
                $query->where($column, $filters[$filter]);
            }
        }

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $pattern = "%{$term}%";
            $price = self::normalizePrice($term);
            $castType = $query->getModel()->getConnection()->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT';

            $query->where(function (Builder $search) use ($pattern, $price, $castType): void {
                foreach ([...array_values(self::PRICE_COLUMNS), 'tiempo_entrega'] as $column) {
                    $search->orWhereLike(DB::raw("CAST({$column} AS {$castType})"), $pattern);
                }

                $search->orWhereHas('origen', fn (Builder $relation) => $relation->whereLike('nombre_origen', $pattern))
                    ->orWhereHas('destino', fn (Builder $relation) => $relation->whereLike('nombre_destino', $pattern))
                    ->orWhereHas('servicioExtra', fn (Builder $relation) => $relation->whereLike('nombre', $pattern));

                if ($price !== null) {
                    foreach (self::PRICE_COLUMNS as $column) {
                        $search->orWhere($column, $price);
                    }
                }
            });
        }

        return $query;
    }

    private static function normalizePrice(mixed $value): ?float
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(str_replace(',', '.', (string) $value));

        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0
            ? round((float) $value, 2)
            : null;
    }
}
