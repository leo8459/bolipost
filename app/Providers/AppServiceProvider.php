<?php

namespace App\Providers;

use App\Livewire\Hooks\EnsureLivewireActionPermission;
use App\Support\AclPermissionRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force Bootstrap paginator markup globally (AdminLTE uses Bootstrap).
        Paginator::useBootstrapFive();

        Livewire::componentHook(EnsureLivewireActionPermission::class);

        Blade::if('aclcan', function (string $action, mixed $component = null, ?string $moduleOverride = null): bool {
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

            if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
                return true;
            }

            $moduleKeys = [];

            if (is_string($moduleOverride) && $moduleOverride !== '') {
                $moduleKeys[] = $moduleOverride;
            } elseif (is_object($component)) {
                $moduleKeys = AclPermissionRegistry::moduleKeysForLivewireComponent($component::class, $component);
            } elseif (is_string($component) && $component !== '') {
                $moduleKeys = AclPermissionRegistry::moduleKeysForLivewireComponent($component);
            } else {
                $routeName = request()->route()?->getName();

                if (is_string($routeName) && $routeName !== '') {
                    [$moduleKey] = AclPermissionRegistry::splitPermissionName($routeName);
                    $moduleKeys[] = $moduleKey;
                }
            }

            $moduleKeys = array_values(array_unique(array_filter($moduleKeys, fn ($moduleKey): bool => is_string($moduleKey) && $moduleKey !== '')));

            foreach ($moduleKeys as $moduleKey) {
                // Button visibility must be strict by feature action.
                // Do not grant button access through broad/manage or route permissions.
                $permissions = ['feature.'.$moduleKey.'.'.$action];
                $existingPermissions = AclPermissionRegistry::existingPermissionsFrom($permissions);

                if ($existingPermissions === []) {
                    if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                        continue;
                    }

                    continue;
                }

                foreach ($existingPermissions as $permission) {
                    if ($user->can($permission)) {
                        return true;
                    }
                }
            }

            return false;
        });
    }
}
