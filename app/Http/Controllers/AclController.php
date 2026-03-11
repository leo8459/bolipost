<?php

namespace App\Http\Controllers;

use App\Support\AclPermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;

class AclController extends Controller
{
    public function livewireActions(Request $request): JsonResponse
    {
        $enabled = (bool) config('acl.ui.auto_hide_livewire_actions', true);

        if (! $enabled) {
            return response()->json([
                'enabled' => false,
                'components' => [],
            ]);
        }

        $user = $request->user();

        if (! $user) {
            abort(403, 'No autenticado.');
        }

        $aliases = $this->resolveComponentAliases($request);

        if ($aliases === []) {
            return response()->json([
                'enabled' => true,
                'components' => [],
            ]);
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');
        $isSuperAdmin = $superAdminRole !== ''
            && method_exists($user, 'hasRole')
            && $user->hasRole($superAdminRole);
        $allowWhenMissing = (bool) config('acl.route_permission.allow_when_permission_missing', true);

        $components = [];

        foreach ($aliases as $alias) {
            try {
                $componentClass = app('livewire')->getClass($alias);
            } catch (\Throwable) {
                continue;
            }

            $reflection = new ReflectionClass($componentClass);
            $methods = [];

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflection->getName()) {
                    continue;
                }

                $methodName = $method->getName();

                if ($methodName === '' || str_starts_with($methodName, '__')) {
                    continue;
                }

                $permissionsToCheck = AclPermissionRegistry::authorizationPermissionsForLivewireAction(
                    $componentClass,
                    $methodName
                );

                if ($permissionsToCheck === []) {
                    continue;
                }

                $existingPermissions = AclPermissionRegistry::existingPermissionsFrom($permissionsToCheck);

                if ($existingPermissions === []) {
                    $methods[$methodName] = $allowWhenMissing;
                    continue;
                }

                if ($isSuperAdmin) {
                    $methods[$methodName] = true;
                    continue;
                }

                $allowed = false;

                foreach ($existingPermissions as $permissionName) {
                    if ($user->can($permissionName)) {
                        $allowed = true;
                        break;
                    }
                }

                $methods[$methodName] = $allowed;
            }

            $components[$alias] = $methods;
        }

        return response()->json([
            'enabled' => true,
            'components' => $components,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveComponentAliases(Request $request): array
    {
        $raw = $request->query('components', []);
        $aliases = [];

        if (is_string($raw)) {
            $aliases = array_map('trim', explode(',', $raw));
        } elseif (is_array($raw)) {
            $aliases = $raw;
        }

        return collect($aliases)
            ->filter(fn (mixed $alias): bool => is_string($alias) && trim($alias) !== '')
            ->map(fn (string $alias): string => trim($alias))
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }
}

