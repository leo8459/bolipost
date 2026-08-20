<?php

namespace App\Console\Commands;

use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IssuePackageApiToken extends Command
{
    protected $signature = 'api:issue-package-token {name=Postman paquetes}';

    protected $description = 'Genera un token JWT de solo lectura para la API de paquetes y contactos';

    public function handle(): int
    {
        $apiToken = ExternalApiToken::query()->create([
            'user_id' => null,
            'name' => (string) $this->argument('name'),
            'jti' => hash('sha256', Str::uuid()->toString().Str::random(32)),
            'token_hash' => hash('sha256', Str::random(80)),
            'abilities' => ['paquetes-contactos:read'],
            'is_active' => true,
            'expires_at' => null,
        ]);

        $jwt = ExternalApiJwt::issue($apiToken);
        $apiToken->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        $this->info('Token generado correctamente.');
        $this->line($jwt);

        return self::SUCCESS;
    }
}
