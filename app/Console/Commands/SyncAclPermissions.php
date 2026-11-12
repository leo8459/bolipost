<?php

namespace App\Console\Commands;

use App\Support\AclRoleManager;
use App\Support\ClienteAclRoleManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SyncAclPermissions extends Command
{
    protected $signature = 'acl:sync';

    protected $description = 'Sincroniza roles y permisos internos y de clientes fuera de las solicitudes web';

    public function handle(): int
    {
        $synchronized = Cache::lock('acl:permissions:sync:lock', 600)->get(function (): bool {
            Auth::shouldUse('web');
            AclRoleManager::sync();
            ClienteAclRoleManager::sync();

            foreach (['acl:permissions:sync:at', 'acl:cliente:permissions:sync:at'] as $key) {
                Cache::put($key, now()->toIso8601String(), 300);
            }

            return true;
        });

        $this->info($synchronized
            ? 'Roles y permisos sincronizados correctamente.'
            : 'Ya hay una sincronizacion de permisos en curso.');

        return self::SUCCESS;
    }
}
