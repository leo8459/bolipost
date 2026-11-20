<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ContractReportScope
{
    public static function excludedCompanyQuery(string $packageAlias): Builder
    {
        return DB::table('empresa as excluded_company')
            ->selectRaw('1')
            ->whereRaw('excluded_company.id = COALESCE('.$packageAlias.'.empresa_id, '
                .'(SELECT company_user.empresa_id FROM users as company_user WHERE company_user.id = '.$packageAlias.'.user_id))')
            ->where(function (Builder $company): void {
                $company->whereRaw("LOWER(TRIM(COALESCE(excluded_company.nombre, ''))) LIKE ?", ['%prueba%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(excluded_company.nombre, ''))) = ?", ['EMPRESA']);
            });
    }
}
