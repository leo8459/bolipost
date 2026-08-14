<?php

namespace Tests\Unit;

use App\Database\Query\Grammars\CaseInsensitivePostgresGrammar;
use Illuminate\Database\PostgresConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class CaseInsensitivePostgresGrammarTest extends TestCase
{
    public function test_like_operators_are_compiled_case_insensitively(): void
    {
        $connection = new PostgresConnection(new PDO('sqlite::memory:'));
        $connection->setQueryGrammar(new CaseInsensitivePostgresGrammar($connection));

        $likeSql = $connection->table('users')
            ->where('name', 'like', '%leonardo%')
            ->toSql();
        $notLikeSql = $connection->table('users')
            ->where('name', 'not like', '%leonardo%')
            ->toSql();

        $this->assertStringContainsString('"name"::text ilike ?', $likeSql);
        $this->assertStringContainsString('"name"::text not ilike ?', $notLikeSql);
    }

    public function test_other_comparison_operators_are_not_changed(): void
    {
        $connection = new PostgresConnection(new PDO('sqlite::memory:'));
        $connection->setQueryGrammar(new CaseInsensitivePostgresGrammar($connection));

        $sql = $connection->table('users')
            ->where('name', '=', 'Leonardo')
            ->toSql();

        $this->assertStringContainsString('"name" = ?', $sql);
        $this->assertStringNotContainsString('ilike', $sql);
    }
}
