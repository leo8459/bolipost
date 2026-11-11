<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the menu permission that protects each International workspace.
 */
class InternationalMenuAcl
{
    public const MENU_PERMISSION = 'internacional.menu';

    public const SUBMENU_RECEIVE_DESTINATION = 'internacional.menu.mi-almacen.recibir-destino';
    public const SUBMENU_PENDING_DELIVERY = 'internacional.menu.mi-almacen.por-entregar';
    public const SUBMENU_ASSIGNED_TO_ME = 'internacional.menu.mis-paquetes.asignados-a-mi';
    public const SUBMENU_DELIVERED_BY_ME = 'internacional.menu.mis-paquetes.entregados-por-mi';
    public const SUBMENU_RECEIVE_SACK = 'internacional.menu.recibir-abrir-saca';
    public const SUBMENU_CLASSIFY_DISPATCH = 'internacional.menu.clasificar-despachar';
    public const SUBMENU_RETURNS = 'internacional.menu.rezago-devoluciones';
    public const SUBMENU_ALL_PACKAGES = 'internacional.menu.todos-paquetes';
    public const SUBMENU_DELIVERED_AT_OFFICE = 'internacional.menu.entregados-oficina';

    /** @return array<string, string> */
    public static function submenuPermissions(): array
    {
        return [
            'receive-destination' => self::SUBMENU_RECEIVE_DESTINATION,
            'pending-delivery' => self::SUBMENU_PENDING_DELIVERY,
            'assigned-to-me' => self::SUBMENU_ASSIGNED_TO_ME,
            'delivered-by-me' => self::SUBMENU_DELIVERED_BY_ME,
            'receive-sack' => self::SUBMENU_RECEIVE_SACK,
            'classify-dispatch' => self::SUBMENU_CLASSIFY_DISPATCH,
            'returns' => self::SUBMENU_RETURNS,
            'all-packages' => self::SUBMENU_ALL_PACKAGES,
            'delivered-at-office' => self::SUBMENU_DELIVERED_AT_OFFICE,
        ];
    }

    public static function permissionForSection(?string $section): ?string
    {
        return match ($section) {
            'recepcion-oficina' => self::SUBMENU_RECEIVE_DESTINATION,
            'almacen', 'cartero', 'salida-reparto', 'disponible-retiro', 'intento-entrega' => self::SUBMENU_PENDING_DELIVERY,
            'asignados-a-mi' => self::SUBMENU_ASSIGNED_TO_ME,
            'entregados-por-mi' => self::SUBMENU_DELIVERED_BY_ME,
            'recepcion-cambio', 'devolucion-aduana' => self::SUBMENU_RECEIVE_SACK,
            'despacho-nacional', 'saca-nacional' => self::SUBMENU_CLASSIFY_DISPATCH,
            'rezago' => self::SUBMENU_RETURNS,
            'todos' => self::SUBMENU_ALL_PACKAGES,
            'inventario' => self::SUBMENU_DELIVERED_AT_OFFICE,
            default => null,
        };
    }

    public static function permissionForWarehouseView(?string $warehouseView): ?string
    {
        return match ($warehouseView) {
            'por-entregar' => self::SUBMENU_PENDING_DELIVERY,
            'asignados-a-mi' => self::SUBMENU_ASSIGNED_TO_ME,
            'entregados-por-mi' => self::SUBMENU_DELIVERED_BY_ME,
            'entregados' => self::SUBMENU_DELIVERED_AT_OFFICE,
            default => null,
        };
    }

    /**
     * Resolve the leaf permission for a request protected by the menu ACL.
     */
    public static function permissionForRequest(Request $request): ?string
    {
        $routeName = (string) $request->route()?->getName();

        if ($routeName === 'internacional.section') {
            return self::permissionForSection((string) $request->route('section'));
        }

        if ($routeName === 'internacional.warehouse') {
            return self::permissionForWarehouseView((string) $request->route('warehouseView'));
        }

        if ($routeName === 'internacional.sacas' || str_starts_with($routeName, 'internacional.sacas.')) {
            return self::SUBMENU_RECEIVE_SACK;
        }

        if (in_array($routeName, [
            'internacional.workflow.selection.add',
            'internacional.workflow.selection.remove',
            'internacional.workflow.operate',
        ], true)) {
            return self::permissionForSection((string) $request->route('section'));
        }

        if ($routeName === 'internacional.inbound.create') {
            return self::SUBMENU_RECEIVE_SACK;
        }

        if (str_starts_with($routeName, 'internacional.my-delivery.')) {
            return self::SUBMENU_ASSIGNED_TO_ME;
        }

        if (in_array($routeName, [
            'internacional.selection.add',
            'internacional.selection.remove',
            'internacional.operate',
        ], true)) {
            return self::SUBMENU_PENDING_DELIVERY;
        }

        return null;
    }

    /**
     * Resolve the leaf permission for a link in the in-page workflow guide.
     *
     * @param array<string, mixed> $parameters
     */
    public static function permissionForLink(string $routeName, array $parameters): ?string
    {
        return match ($routeName) {
            'internacional.section' => self::permissionForSection(isset($parameters['section']) ? (string) $parameters['section'] : null),
            'internacional.warehouse' => self::permissionForWarehouseView(isset($parameters['warehouseView']) ? (string) $parameters['warehouseView'] : null),
            'internacional.sacas' => self::SUBMENU_RECEIVE_SACK,
            default => null,
        };
    }

    public static function userCanAccess(mixed $user, ?string $submenuPermission): bool
    {
        return $user !== null
            && $submenuPermission !== null
            && $user->can(self::MENU_PERMISSION)
            && $user->can($submenuPermission);
    }
}
