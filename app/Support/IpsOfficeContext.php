<?php

namespace App\Support;

use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class IpsOfficeContext
{
    public static function combinedOffices(Request $request): array
    {
        $offices = array_values(array_filter($request->user()?->ipsLink?->offices() ?? [],
            fn ($office) => in_array(strtoupper($office['office_fcd']), ['BOLPBA', 'BOLPBB', 'BOLPBC'], true)));
        usort($offices, fn ($a, $b) => $a['office_cd'] <=> $b['office_cd']);

        return count($offices) > 1 ? $offices : [];
    }

    public static function assertCurrentAssignment(UserIpsLink $link, SitraIpsClient $ips): void
    {
        try {
            $actor = $ips->user((int) $link->ips_user_pid);
        } catch (RequestException $exception) {
            if (in_array($exception->response->status(), [403, 404], true)) {
                abort(403, 'El usuario IPS vinculado ya no esta disponible o activo. Solicita verificar la vinculacion.');
            }

            abort(503, 'No se pudieron verificar tus oficinas en SITRA. Vuelve a intentarlo antes de registrar el movimiento.');
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'No se pudieron verificar tus oficinas en SITRA. Vuelve a intentarlo antes de registrar el movimiento.');
        }

        abort_unless(isset($actor['user_pid']) && (int) $actor['user_pid'] === (int) $link->ips_user_pid,
            403, 'SITRA no confirmo la identidad del usuario IPS vinculado.');
        abort_unless(collect(UserIpsLink::officesFromSnapshot($actor))->contains('office_cd', (int) $link->ips_office_cd),
            403, 'La oficina seleccionada ya no esta asignada a tu usuario IPS como principal o secundaria. Solicita verificar la vinculacion.');
    }

    public static function resolve(Request $request): ?UserIpsLink
    {
        $link = $request->user()?->ipsLink;
        $data = $request->validate(['office_cd' => ['nullable', 'integer', 'min:0', 'max:32767']]);
        $officeCd = $data['office_cd'] ?? $link?->ips_office_cd;
        if (! $link || $officeCd === null) {
            return $link;
        }

        $office = collect($link->offices())->firstWhere('office_cd', (int) $officeCd);
        abort_unless($office, 403, 'Esta oficina no esta asignada a tu usuario IPS. Solicita verificar la vinculacion.');

        // Each tab carries its office in the URL; never change the shared user link.
        $context = clone $link;
        $context->ips_office_cd = $office['office_cd'];
        $context->ips_office_fcd = $office['office_fcd'];
        $context->ips_office_name = $office['office_name'];
        if ((int) $link->ips_office_cd !== $office['office_cd']) {
            $context->delivery_agent_cd = null;
            $context->delivery_agent_full_cd = null;
        }
        $request->attributes->set('ips_office_cd', $office['office_cd']);

        return $context;
    }

    public static function parameters(Request $request, array $parameters = []): array
    {
        if ($request->attributes->get('ips_combined_offices')) {
            unset($parameters['office_cd']);

            return $parameters;
        }
        $officeCd = $request->attributes->get('ips_office_cd', $request->input('office_cd'));

        return $officeCd === null ? $parameters : $parameters + ['office_cd' => $officeCd];
    }
}
