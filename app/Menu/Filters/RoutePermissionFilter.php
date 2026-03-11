<?php

namespace App\Menu\Filters;

use App\Support\AclPermissionRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class RoutePermissionFilter implements FilterInterface
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $uriToName = null;

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

        $candidatePermissions = array_values(array_unique(array_merge(
            [$routeName],
            AclPermissionRegistry::featurePermissionsForRoute($routeName),
        )));

        foreach ($candidatePermissions as $permissionName) {
            if (AclPermissionRegistry::permissionExists($permissionName) && Auth::user()?->can($permissionName)) {
                $item['can'] = $permissionName;

                return $item;
            }
        }

        if (! AclPermissionRegistry::permissionExists($routeName)) {
            return $item;
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

        return $this->getUriToNameMap()[$path] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function getUriToNameMap(): array
    {
        if (self::$uriToName !== null) {
            return self::$uriToName;
        }

        $map = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! is_string($name) || $name === '') {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = trim($route->uri(), '/');

            if ($uri === '') {
                continue;
            }

            $map[$uri] = $name;
        }

        self::$uriToName = $map;

        return self::$uriToName;
    }
}
