<?php

namespace App\Menu\Filters;

use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class PanelContextFilter implements FilterInterface
{
    public function transform($item)
    {
        $isCliente = Auth::guard('cliente')->check() && ! Auth::guard('web')->check();
        $isWeb = Auth::guard('web')->check();
        $context = $item['context'] ?? null;
        $type = $item['type'] ?? null;

        if ($isCliente) {
            if (in_array($type, ['sidebar-menu-search', 'fullscreen-widget', 'navbar-search'], true)) {
                return $item;
            }

            return $context === 'cliente' ? $item : false;
        }

        if ($isWeb && $context === 'cliente') {
            return false;
        }

        return $item;
    }
}
