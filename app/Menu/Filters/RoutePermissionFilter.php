<?php

namespace App\Menu\Filters;

use App\Support\AclPermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class RoutePermissionFilter implements FilterInterface
{
    /**
     * Add a dynamic "can" key to menu items using route names.
     */
    public function transform($item)
    {
        if (! Auth::check()) {
            return $item;
        }

        if (! empty($item['can']) || empty($item['url'])) {
            return $item;
        }

        $routeName = $this->resolveRouteName((string) $item['url']);

        if (! $routeName) {
            return $item;
        }

<<<<<<< HEAD
        $candidatePermissions = AclPermissionRegistry::authorizationPermissionsForRouteAccess($routeName);
=======
        // Keep menu visibility aligned with route access:
        // a menu item is visible only when the user has the exact route permission.
        $candidatePermissions = [$routeName];
>>>>>>> a41ccfb (Uchazara)

        foreach ($candidatePermissions as $permissionName) {
            if (AclPermissionRegistry::permissionExists($permissionName) && Auth::user()?->can($permissionName)) {
                $item['can'] = $permissionName;

                return $item;
            }
        }

        if (! AclPermissionRegistry::permissionExists($routeName)) {
            if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                return $item;
            }

            return false;
        }

        $item['can'] = $routeName;

        return $item;
    }

    private function resolveRouteName(string $url): ?string
    {
        if (
            str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
        ) {
            return null;
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create('/'.$path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }
}
