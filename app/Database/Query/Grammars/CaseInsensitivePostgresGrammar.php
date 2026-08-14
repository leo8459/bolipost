<?php

namespace App\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

class CaseInsensitivePostgresGrammar extends PostgresGrammar
{
    /**
     * PostgreSQL distingue mayusculas en LIKE. Los buscadores del sistema
     * deben comportarse de forma uniforme, por lo que LIKE se compila como
     * ILIKE y NOT LIKE como NOT ILIKE.
     */
    protected function whereBasic(Builder $query, $where)
    {
        $operator = mb_strtolower(trim((string) ($where['operator'] ?? '')));

        if ($operator === 'like') {
            $where['operator'] = 'ilike';
        } elseif ($operator === 'not like') {
            $where['operator'] = 'not ilike';
        }

        return parent::whereBasic($query, $where);
    }
}
