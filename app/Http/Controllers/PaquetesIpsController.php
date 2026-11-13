<?php

namespace App\Http\Controllers;

use App\Models\IpsVerificationNote;
use App\Models\PaqueteUpu;
use App\Services\SitraIpsClient;
use App\Services\IpsCombinedOfficeWorkbench;
use App\Services\SitraTrackingEventsClient;
use App\Support\InternationalMenuAcl;
use App\Support\IpsOfficeContext;
use App\Support\IpsDeliveryReceipt;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaquetesIpsController extends Controller
{
    public const ACTIONS = [
        'EMR' => 'Registrar paquete de saca abierta',
        'EDN' => 'Despachar paquete por destino',
        'EIB' => 'Agrupar en saca nacional',
        'EMG' => 'Recepción en oficina de entrega',
        'CUSTOMS_RETURN' => 'Recibir devolución de Aduana',
        'EDH' => 'Disponible para retiro',
        'HANDOVER_AGENT' => 'Asignar al cartero',
        'EDG' => 'Salida a reparto',
        'EMH' => 'Intento de entrega fallido',
        'EMI' => 'Registrar entrega (baja)',
    ];

    private const DELIVERY_ACTION_LABELS = [
        'EDH' => 'Dejar disponible para retiro',
        'HANDOVER_AGENT' => 'Entregar paquete al cartero',
        'CUSTOMS_PRESENT' => 'Enviar a Aduana',
        'EDG' => 'Registrar salida a reparto',
        'EMH' => 'Registrar intento fallido',
        'EMI' => 'Confirmar entrega al destinatario',
    ];

    private const INTERNATIONAL_SECTIONS = [
        'almacen' => [
            'stage' => 'warehouse',
            'title' => 'Almacén internacional',
            'description' => 'Primero registra la recepción física del paquete en esta oficina. Después podrás registrar su entrega al destinatario.',
        ],
        'inventario' => [
            'stage' => 'delivered',
            'title' => 'Inventario internacional',
            'description' => 'Historial de paquetes con entrega (baja) registrada en IPS para tu oficina.',
        ],
        'rezago' => [
            'stage' => 'returns',
            'title' => 'Rezago y devoluciones',
            'description' => 'Paquetes que IPS reporta en flujo de devolución para tu oficina.',
        ],
        'todos' => [
            'stage' => 'all',
            'title' => 'Todos los paquetes internacionales',
            'description' => 'Consulta los paquetes y movimientos IPS asociados a tu oficina.',
        ],
    ];

    private const WAREHOUSE_VIEWS = [
        'por-entregar' => [
            'stage' => 'warehouse',
            'title' => 'Almacén internacional · Por entregar',
            'description' => 'La baja requiere una recepción confirmada en esta oficina (evento 32). Si aún no recibiste el paquete, vuelve a Recibir paquetes en destino.',
        ],
        'asignados-a-mi' => [
            'stage' => 'warehouse',
            'event_cd' => [39, 74],
            'delivery_agent' => true,
            'title' => 'Mi reparto · Asignados a mí',
            'description' => 'Paquetes entregados a tu código de cartero. Registra la salida y luego el resultado real del reparto.',
        ],
        'entregados' => [
            'stage' => 'delivered',
            'event_cd' => 37,
            'title' => 'Almacén internacional · Entregados',
            'description' => 'Entregas registradas en IPS para tu oficina.',
        ],
        'entregados-por-mi' => [
            'stage' => 'delivered',
            'event_cd' => 37,
            'by_me' => true,
            'title' => 'Almacén internacional · Entregados por mí',
            'description' => 'Entregas registradas en IPS por tu usuario vinculado.',
        ],
    ];

    private const WAREHOUSE_TABS = [
        ['key' => 'por-entregar', 'label' => 'Por entregar', 'params' => ['warehouseView' => 'por-entregar']],
        ['key' => 'asignados-a-mi', 'label' => 'Asignados a mí', 'params' => ['warehouseView' => 'asignados-a-mi']],
        ['key' => 'entregados-por-mi', 'label' => 'Entregados por mí', 'params' => ['warehouseView' => 'entregados-por-mi']],
    ];

    private const INTERNATIONAL_OPERATION_GROUPS = [
        'recepcion' => [
            'label' => 'Recibir y abrir saca',
            'start' => ['route' => 'internacional.sacas', 'params' => ['paso' => 'recibir']],
            'description' => 'Registra la llegada de la saca internacional cerrada y ábrela una sola vez. Después, escanea cada paquete en el flujo que corresponde: recepción internacional (30) o devolución de Aduana (38).',
            'tabs' => [
                ['key' => 'saca-recibir', 'label' => 'Recibir saca cerrada', 'route' => 'internacional.sacas', 'params' => ['paso' => 'recibir']],
                ['key' => 'saca-abrir', 'label' => 'Registrar apertura física', 'route' => 'internacional.sacas', 'params' => ['paso' => 'abrir']],
                ['key' => 'recepcion-paquete', 'label' => 'Registrar paquetes encontrados', 'route' => 'internacional.section', 'params' => ['section' => 'recepcion-cambio']],
                ['key' => 'saca-devolucion-aduana', 'label' => 'Registrar paquetes devueltos de Aduana', 'route' => 'internacional.section', 'params' => ['section' => 'devolucion-aduana']],
            ],
        ],
        'ruta' => [
            'label' => 'Clasificar y despachar',
            'start' => ['route' => 'internacional.section', 'params' => ['section' => 'despacho-nacional']],
            'description' => 'Define el destino y despacha cada paquete. Registra una saca nacional solo cuando realmente agrupes paquetes para el traslado; la oficina destino siempre confirma su llegada.',
            'tabs' => [
                ['key' => 'ruta-despacho', 'label' => 'Definir destino del paquete', 'route' => 'internacional.section', 'params' => ['section' => 'despacho-nacional']],
                ['key' => 'ruta-saca', 'label' => 'Asociar a saca (si aplica)', 'route' => 'internacional.section', 'params' => ['section' => 'saca-nacional']],
                ['key' => 'ruta-recepcion', 'label' => 'Recibir paquetes en destino', 'route' => 'internacional.section', 'params' => ['section' => 'recepcion-oficina']],
            ],
        ],
        'distribucion' => [
            'label' => 'Distribuir y entregar',
            'start' => ['route' => 'internacional.warehouse', 'params' => ['warehouseView' => 'por-entregar']],
            'description' => 'Después de recibir en destino, registra retiro en ventanilla o el recorrido del cartero.',
            'tabs' => [
                ['key' => 'entrega-bandeja', 'label' => 'Abrir bandeja de distribución', 'route' => 'internacional.warehouse', 'params' => ['warehouseView' => 'por-entregar']],
            ],
        ],
    ];

    private const INTERNATIONAL_WORKFLOWS = [
        'recepcion-cambio' => [
            'action' => 'EMR',
            'stage' => 'reception',
            'title' => 'Registrar paquetes de la saca abierta',
            'description' => 'Escanea cada paquete que encontraste al abrir físicamente la saca internacional. Cada paquete se registra por separado y queda asociado a esa saca.',
            'requires' => 'La saca internacional ya fue abierta físicamente y el envío está habilitado para recibirse en esta oficina.',
            'records' => 'La recepción individual del paquete y su asociación con la saca internacional abierta.',
            'next' => 'Define la oficina destinataria. El paquete puede despacharse directamente; asócialo a una saca nacional (evento 13) solo si físicamente lo agrupas en una saca.',
            'search_only' => true,
        ],
        'despacho-nacional' => [
            'action' => 'EDN',
            'stage' => 'all',
            'event_cd' => [30, 38],
            'title' => 'Clasificar paquetes por destino',
            'description' => 'Selecciona la siguiente oficina habilitada para cada paquete. Trabaja juntos los que van al mismo destino y separa los de otra ruta o clase postal.',
            'requires' => 'El envío fue recibido en la oficina de cambio o retornó físicamente de Aduana a esta oficina.',
            'records' => 'El despacho del paquete y la siguiente oficina de su ruta.',
            'next' => 'El despacho (evento 35) encamina el paquete directamente a la oficina siguiente. Si físicamente viaja dentro de una saca nueva, registra también su asociación (evento 13).',
        ],
        'saca-nacional' => [
            'action' => 'EIB',
            'stage' => 'all',
            'event_cd' => 35,
            'title' => 'Agrupar paquetes en saca nacional',
            'description' => 'Asocia cada paquete despachado a una saca nacional abierta compatible con su destino y clase postal. Es una saca física nueva; no se reutiliza el marbete de la saca internacional.',
            'requires' => 'El despacho ya está registrado, la oficina siguiente está definida y hay una saca abierta compatible con la ruta y clase postal.',
            'records' => 'La asociación del paquete a la nueva saca nacional seleccionada.',
            'next' => 'Al llegar físicamente a la oficina destinataria, registra la recepción (evento 32). El evento 13 solo corresponde a paquetes que realmente viajaron dentro de una saca.',
        ],
        'recepcion-oficina' => [
            'action' => 'EMG',
            'stage' => 'reception',
            'event_cd' => [13, 35, 38, 72],
            'title' => 'Recibir paquetes en oficina de entrega',
            'description' => 'Lista paquetes que IPS encaminó a esta oficina. Si escaneas uno con otra oficina de destino, podrás revisar su recepción física aquí sin crear un evento de cambio de ruta.',
            'requires' => 'El envío llegó físicamente a la oficina de destino indicada por su ruta.',
            'records' => 'La recepción en oficina de destino (evento 32).',
            'next' => 'El envío queda disponible para retiro o para el proceso de reparto que corresponda.',
        ],
        'devolucion-aduana' => [
            'action' => 'CUSTOMS_RETURN',
            'stage' => 'all',
            'event_cd' => [31, 34, 1251],
            'title' => 'Registrar devoluciones de Aduana dentro de una saca',
            'description' => 'Desde una saca recibida y abierta, escanea cada paquete que Aduana devolvió físicamente al correo. El evento 38 registra el retorno desde Aduana en esta oficina; no reemplaza la llegada a la oficina de entrega.',
            'requires' => 'La saca ya fue recibida y abierta físicamente en esta oficina.',
            'records' => 'Solo la devolución física desde Aduana (evento 38). No marca llegada a la oficina de entrega ni entrega al destinatario.',
            'next' => 'Si la oficina de entrega es otra, clasifica y despacha el paquete directamente (evento 35). Asócialo a una saca nacional (evento 13) solo si físicamente lo agrupas. Cuando llegue a la oficina destinataria, registra allí la recepción (evento 32).',
            'search_only' => true,
        ],
        'cartero' => [
            'action' => 'HANDOVER_AGENT',
            'stage' => 'pending',
            'event_cd' => [32, 36, 38],
            'title' => 'Asignar al cartero',
            'description' => 'Asigna el envío al agente de entrega de IPS antes de que salga a reparto.',
            'requires' => 'El envío está en esta oficina y un cartero activo recibirá físicamente la carga.',
            'records' => 'La transferencia al cartero identificado en IPS.',
            'next' => 'Registra salida a reparto y después la entrega o el intento fallido.',
        ],
        'salida-reparto' => [
            'action' => 'EDG',
            'stage' => 'pending',
            'event_cd' => 39,
            'title' => 'Registrar salida a reparto',
            'description' => 'Confirma que el cartero salió efectivamente con el envío para realizar la entrega.',
            'requires' => 'El envío ya fue transferido a un cartero en IPS.',
            'records' => 'La salida física del cartero a reparto.',
            'next' => 'Registra la entrega al destinatario o el resultado real del intento.',
        ],
        'disponible-retiro' => [
            'action' => 'EDH',
            'stage' => 'pending',
            'event_cd' => [32, 38],
            'title' => 'Dejar disponible para retiro',
            'description' => 'Informa que el envío está disponible para que el destinatario lo recoja en ventanilla.',
            'requires' => 'El envío fue recibido en esta oficina y no se asignará a reparto domiciliario.',
            'records' => 'La disponibilidad del envío para retiro en oficina.',
            'next' => 'Al entregarlo al destinatario, registra la entrega por ventanilla.',
        ],
        'intento-entrega' => [
            'action' => 'EMH',
            'stage' => 'pending',
            'event_cd' => 74,
            'title' => 'Registrar intento de entrega',
            'description' => 'Registra solo un intento realizado y captura el motivo y la medida aplicada.',
            'requires' => 'El cartero acudió al domicilio o al punto de entrega y no pudo completar la entrega.',
            'records' => 'El intento realizado con su motivo y la medida aplicada.',
            'next' => 'Si corresponde un nuevo intento, actualiza la acción según el estado que IPS habilite.',
        ],
    ];

    private const INTERNATIONAL_OPERATION_TABS = [
        'recepcion-cambio' => ['recepcion', 'recepcion-paquete'],
        'despacho-nacional' => ['ruta', 'ruta-despacho'],
        'saca-nacional' => ['ruta', 'ruta-saca'],
        'recepcion-oficina' => ['ruta', 'ruta-recepcion'],
        'devolucion-aduana' => ['recepcion', 'saca-devolucion-aduana'],
        'disponible-retiro' => ['distribucion', null],
        'cartero' => ['distribucion', null],
        'salida-reparto' => ['distribucion', null],
        'intento-entrega' => ['distribucion', null],
    ];

    private function context(Request $request, string $workflow = 'standard'): array
    {
        $user = $request->user();
        $link = IpsOfficeContext::resolve($request);
        abort_unless($link && $link->active && $link->ips_office_cd !== null, 403, 'Necesita un vínculo IPS activo con oficina asignada.');
        if ($workflow === 'my_delivery') {
            abort_unless(preg_match('/^[A-Za-z0-9]{2}$/', (string) $link->delivery_agent_cd) === 1, 403, 'Tu cuenta todavía no tiene un cartero IPS vinculado. Comunícate con administración.');
        }

        $keyPrefix = match ($workflow) {
            'delivery' => 'ips_delivery_workbench.',
            'standard' => 'ips_workbench.',
            default => 'ips_'.$workflow.'_workbench.',
        };

        $combinedOffices = in_array($workflow, ['international_emg', 'delivery'], true)
            ? IpsOfficeContext::combinedOffices($request) : [];
        if ($combinedOffices !== []) {
            $request->attributes->set('ips_combined_offices', $combinedOffices);
            $key = $keyPrefix.$user->id.'_offices_'.implode('_', array_column($combinedOffices, 'office_cd'));
            if (! $request->session()->has($key)) {
                $combinedSelection = [];
                foreach ($combinedOffices as $office) {
                    $officeLink = clone $link;
                    $officeLink->ips_office_cd = $office['office_cd'];
                    foreach ($request->session()->get($keyPrefix.$user->id.$officeLink->officeSessionSuffix(), []) as $code => $entry) {
                        $entry['office_cd'] = $office['office_cd'];
                        $entry['package']['workbench_office_cd'] = $office['office_cd'];
                        $entry['package']['workbench_office_name'] = $office['office_name'];
                        if (! isset($combinedSelection[$code]) || ! empty($entry['payload'])) {
                            $combinedSelection[$code] = $entry;
                        }
                    }
                }
                $request->session()->put($key, $combinedSelection);
            }
            $code = strtoupper(trim((string) $request->input('codigo', '')));
            $entryOffice = $request->session()->get($key)[$code]['office_cd'] ?? null;
            if ($entryOffice !== null) {
                abort_unless(in_array((int) $entryOffice, array_column($combinedOffices, 'office_cd'), true), 403);
                $request->merge(['office_cd' => $entryOffice]);
                $link = IpsOfficeContext::resolve($request);
            }

            return [$link, $key];
        }

        return [$link, $keyPrefix.$user->id.$link->officeSessionSuffix()];
    }

    private function automaticReceiptOffice(array $package, Request $request): ?int
    {
        $options = $package['receipt_office_options'] ?? [];
        if ($options === []) {
            return isset($package['workbench_office_cd']) ? (int) $package['workbench_office_cd'] : null;
        }
        $allowed = array_column($options, 'office_cd');
        foreach ([$package['receipt_office_locked_cd'] ?? null, $package['workbench_office_cd'] ?? null,
            $request->user()?->ipsLink?->ips_office_cd] as $candidate) {
            if ($candidate !== null && in_array((int) $candidate, $allowed, true)) return (int) $candidate;
        }

        return (int) $options[0]['office_cd'];
    }

    private function receptionBlockReason(array $package, int $officeCd): string
    {
        $event = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
        $state = (int) ($package['state_cd'] ?? -1);

        if ($event === 37 || $state === 5 || ! empty($package['terminal'])) {
            return 'El historial ya registra una entrega o un cierre terminal. No se puede volver a recepcionar este paquete.';
        }

        if ($event === 32) {
            return 'El paquete ya tiene registrada la recepción en oficina de destino (evento 32). No lo agregues otra vez.';
        }

        if (in_array($event, [31, 34], true) && $state === 1) {
            $office = trim((string) ($package['office_name'] ?? ''));
            $location = $office !== '' ? ' en '.$office : ' en la oficina registrada';
            $recordedArrival = $event === 34
                ? 'IPS registra la información aduanera'.$location.' (evento 34). El paquete ya está en el circuito de Aduana de esa oficina.'
                : 'IPS registra el envío a Aduana'.$location.' (evento 31).';

            return $recordedArrival.' El estado 1 indica que Aduana aún lo retiene. El catálogo IPS no permite registrar la recepción en destino (evento 32) desde ese estado. Cuando Aduana lo devuelva físicamente a Correo, registra el evento 38; luego encamínalo a la oficina destinataria y registra el 32 cuando llegue allí.';
        }

        if ($event === 1251 && $state === 1) {
            $currentOffice = trim((string) ($package['office_name'] ?? ''));
            $currentLocation = $currentOffice !== '' ? ' en '.$currentOffice : '';
            $currentOfficeCd = (int) ($package['operational_office_cd'] ?? $package['office_cd'] ?? 0);
            $officeMismatch = $currentOfficeCd > 0 && $currentOfficeCd !== $officeCd;
            $routingNote = $officeMismatch
                ? ' El evento y la saca están registrados en'.$currentLocation.'; para devolverlo desde Aduana usa la cuenta vinculada a esa oficina.'
                : '';

            return 'El evento 1251 indica que el envío fue observado por Aduana e incorporado a una saca nacional'.$currentLocation.'; conserva estado 1. No se puede registrar todavía la recepción en destino (evento 32).'.$routingNote.' Cuando Aduana lo devuelva físicamente, abre la saca recibida allí y registra desde “Devolución de Aduana” el evento 38. Después encamínalo a la oficina destinataria y registra el 32 cuando llegue.';
        }

        if ($event === 38) {
            $office = trim((string) ($package['office_name'] ?? ''));
            $location = $office !== '' ? ' en '.$office : '';

            return 'Aduana ya devolvió el paquete (evento 38)'.$location.'. Primero encamínalo a su oficina de destino; registra el evento 32 cuando llegue físicamente allí.';
        }

        $nextOfficeCd = isset($package['next_office_cd']) ? (int) $package['next_office_cd'] : null;
        $currentOfficeCd = (int) ($package['operational_office_cd'] ?? $package['office_cd'] ?? 0);
        if (in_array($event, [13, 35, 72], true)
            && $nextOfficeCd === $officeCd
            && $currentOfficeCd === $officeCd) {
            return 'IPS muestra una salida registrada desde esta misma oficina hacia ella misma. No es un traslado entrante; espera un despacho desde otra oficina dirigido aquí.';
        }
        if (in_array($event, [13, 35, 72], true) && $nextOfficeCd !== null && $nextOfficeCd !== $officeCd) {
            $nextOffice = trim((string) ($package['next_office_name'] ?? ''));
            $destination = $nextOffice !== '' ? $nextOffice : 'la oficina '.$nextOfficeCd;

            return 'IPS tiene este paquete encaminado a '.$destination.'. Esta cuenta está vinculada a otra oficina; registra la recepción en la oficina indicada.';
        }

        if ($event === 30) {
            return 'El último movimiento es la recepción en oficina de intercambio (evento 30). Todavía debe encaminarse a la oficina de destino antes de registrar el evento 32.';
        }

        $office = trim((string) ($package['office_name'] ?? ''));
        $location = $office !== '' ? ' en '.$office : '';

        return 'IPS no habilita la recepción (evento 32) para el último movimiento, evento '.$event.$location.'. Revisa la ruta y el historial del paquete.';
    }

    private function isRouteMismatchReceiptCandidate(array $package, int $officeCd): bool
    {
        $event = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
        $state = isset($package['state_cd']) ? (int) $package['state_cd'] : null;
        $currentOffice = (int) ($package['operational_office_cd'] ?? $package['office_cd'] ?? 0);
        $nextOffice = isset($package['next_office_cd']) ? (int) $package['next_office_cd'] : null;

        return ($package['destination_country'] ?? '') === 'BO'
            && empty($package['terminal'])
            && in_array($event, [13, 35, 72], true)
            && (($event === 13 && $state === 6) || (in_array($event, [35, 72], true) && in_array($state, [0, 2, 3, 6], true)))
            && $currentOffice > 0
            && $currentOffice !== $officeCd
            && $nextOffice !== null
            && $nextOffice !== $officeCd;
    }

    private function markRouteMismatchReceipt(array &$package, int $officeCd): bool
    {
        if (! $this->isRouteMismatchReceiptCandidate($package, $officeCd)) {
            return false;
        }

        $package['route_mismatch_receipt'] = true;
        $package['allowed_actions'] = array_values(array_unique(array_merge($package['allowed_actions'] ?? [], ['EMG'])));

        return true;
    }

    private function assertAssignedCourierPackage(array $package, string $agentCode): void
    {
        $assignedAgent = $this->normalizeDeliveryAgentCode((string) ($package['delivery_agent_cd'] ?? ''));
        $agentCode = $this->normalizeDeliveryAgentCode($agentCode);
        abort_unless($agentCode !== '' && hash_equals($agentCode, $assignedAgent), 403, 'El paquete no está asignado a tu cuenta de cartero.');

        $event = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
        abort_unless(in_array($event, [39, 74], true), 409, 'El paquete ya salió de tu bandeja de reparto. Actualiza la lista.');
        abort_unless(array_intersect(['EDG', 'EMH', 'EMI'], $package['allowed_actions'] ?? []) !== [], 409, 'IPS no habilita una acción de reparto para este paquete.');
    }

    private function normalizeDeliveryAgentCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return ctype_digit($code) ? str_pad($code, 2, '0', STR_PAD_LEFT) : $code;
    }

    private function normalizeRegionalName(?string $regional): string
    {
        $normalized = Str::upper(Str::ascii(trim((string) $regional)));
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        return match ($normalized) {
            'SANTA CRUZ DE LA SIERRA' => 'SANTA CRUZ',
            'CHUQUISACA' => 'SUCRE',
            'BENI' => 'TRINIDAD',
            'PANDO' => 'COBIJA',
            default => $normalized,
        };
    }

    private function operationNavigation(?string $section, ?string $warehouseView, ?string $receptacleStep = null, ?string $receptaclePid = null): array
    {
        $group = null;
        $tab = null;
        $groups = self::INTERNATIONAL_OPERATION_GROUPS;
        $user = auth()->user();

        if ($receptaclePid !== null) {
            foreach ($groups['recepcion']['tabs'] as &$navigationTab) {
                if ($navigationTab['key'] === 'saca-devolucion-aduana') {
                    $navigationTab['params']['receptacle_pid'] = $receptaclePid;
                }
            }
            unset($navigationTab);
        } else {
            $groups['recepcion']['tabs'] = array_values(array_filter(
                $groups['recepcion']['tabs'],
                fn (array $navigationTab) => $navigationTab['key'] !== 'saca-devolucion-aduana'
            ));
        }

        if ($receptacleStep !== null) {
            $group = 'recepcion';
            $tab = $receptacleStep === 'recibir' ? 'saca-recibir' : 'saca-abrir';
        } elseif ($warehouseView !== null) {
            $group = 'distribucion';
            $tab = 'entrega-bandeja';
        } elseif ($section !== null && isset(self::INTERNATIONAL_OPERATION_TABS[$section])) {
            [$group, $tab] = self::INTERNATIONAL_OPERATION_TABS[$section];
        }

        foreach ($groups as $groupKey => &$navigationGroup) {
            $navigationGroup['tabs'] = array_values(array_filter(
                $navigationGroup['tabs'],
                function (array $navigationTab) use ($user): bool {
                    $permission = InternationalMenuAcl::permissionForLink(
                        (string) ($navigationTab['route'] ?? ''),
                        (array) ($navigationTab['params'] ?? [])
                    );

                    return InternationalMenuAcl::userCanAccess($user, $permission);
                }
            ));

            if ($navigationGroup['tabs'] === []) {
                unset($groups[$groupKey]);
            }
        }
        unset($navigationGroup);

        $warehouseTabs = array_values(array_filter(
            self::WAREHOUSE_TABS,
            fn (array $warehouseTab): bool => InternationalMenuAcl::userCanAccess(
                $user,
                InternationalMenuAcl::permissionForWarehouseView((string) data_get($warehouseTab, 'params.warehouseView'))
            )
        ));

        return [
            $groups,
            $group,
            $tab,
            $warehouseTabs,
        ];
    }

    private function internationalWorkflowKey(Request $request, string $section, array $definition): string
    {
        $key = 'international_'.strtolower($definition['action']);
        if ($section === 'devolucion-aduana') {
            $receptaclePid = strtolower(trim((string) ($request->input('receptacle_pid') ?? $request->query('receptacle_pid', ''))));
            if ($receptaclePid !== '') {
                $key .= '_'.str_replace('-', '', $receptaclePid);
            }
        }

        return $key;
    }

    public function index(Request $request, SitraIpsClient $ips, ?string $section = null, ?string $warehouseView = null)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'stage' => ['nullable', 'in:all,reception,pending,returns,delivered,warehouse'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'receptacle_pid' => ['nullable', 'uuid'],
        ]);
        if ($section === 'inventario') {
            $legacyQuery = array_filter([
                'q' => $filters['q'] ?? null,
                'page' => $filters['page'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            return redirect()->route('internacional.warehouse', IpsOfficeContext::parameters($request, ['warehouseView' => 'entregados'] + $legacyQuery));
        }
        $internationalPage = null;
        $internationalWorkflow = null;
        if ($section !== null) {
            if (isset(self::INTERNATIONAL_WORKFLOWS[$section])) {
                $internationalWorkflow = self::INTERNATIONAL_WORKFLOWS[$section];
            } else {
                abort_unless(isset(self::INTERNATIONAL_SECTIONS[$section]), 404);
                $internationalPage = self::INTERNATIONAL_SECTIONS[$section];
            }
        }

        if ($section === 'almacen') {
            $warehouseView ??= 'por-entregar';
            abort_unless(isset(self::WAREHOUSE_VIEWS[$warehouseView]), 404);
        }

        $warehousePage = $warehouseView !== null ? self::WAREHOUSE_VIEWS[$warehouseView] : null;
        $stage = $warehousePage['stage'] ?? $internationalWorkflow['stage'] ?? $internationalPage['stage'] ?? ($filters['stage'] ?? 'all');
        $search = strtoupper(trim($filters['q'] ?? ''));
        $page = (int) ($filters['page'] ?? 1);
        if ($section === 'recepcion-oficina' && $search !== '') {
            $page = 1;
        }
        $preferredReceptaclePid = in_array($section, ['recepcion-cambio', 'devolucion-aduana'], true)
            ? ($filters['receptacle_pid'] ?? null)
            : null;
        abort_unless($section !== 'devolucion-aduana' || $preferredReceptaclePid, 422, 'Abre primero la saca y entra al registro de paquetes devueltos desde ese paso.');
        $items = collect();
        $total = 0;
        $catalog = ['non_delivery_reasons' => [], 'non_delivery_measures' => [], 'delivery_agents' => [], 'customs_office_types' => [], 'customs_release_statuses' => [], 'offices' => []];
        $error = null;
        $missingPackageCode = null;
        $inboundPending = null;
        $inboundCreateKey = null;
        $workflowAction = $internationalWorkflow['action'] ?? null;
        $emptyMessage = match ($workflowAction) {
            'EMR' => $search !== ''
                ? 'No aparece este código: el envío no existe o SITRA no habilita su recepción en esta oficina.'
                : 'Esta ventana trabaja por escaneo. Escribe o escanea el código exacto del envío recibido en la oficina de cambio; no muestra un listado general.',
            'EDN' => 'No hay envíos recibidos o devueltos de Aduana pendientes de despacho en esta oficina.',
            'EIB' => 'No hay envíos despachados pendientes de asociar a una saca en esta oficina.',
            'EMG' => 'No hay paquetes habilitados por IPS para confirmar su llegada física a esta oficina.',
            'CUSTOMS_RETURN' => 'Escanea el código exacto de un paquete que Aduana ya devolvió físicamente a esta oficina.',
            default => 'No hay paquetes que coincidan con esta búsqueda y oficina.',
        };
        $workflowKey = $internationalWorkflow
            ? $this->internationalWorkflowKey($request, (string) $section, $internationalWorkflow)
            : null;
        $isInternationalPage = $internationalPage !== null || $internationalWorkflow !== null;
        $courierOnly = $section === 'almacen' && $warehouseView === 'asignados-a-mi';
        $deliveryOnly = $section === 'almacen' && in_array($warehouseView, ['por-entregar', 'asignados-a-mi'], true);
        $workbenchActions = $workflowAction ? [$workflowAction]
            : ($courierOnly ? ['EDG', 'EMH', 'EMI'] : ($deliveryOnly ? ['EMI'] : null));
        if ($courierOnly) {
            $emptyMessage = 'No tienes paquetes asignados pendientes de salida o en reparto.';
        }
        $link = IpsOfficeContext::resolve($request);
        $combinedOffices = ($workflowAction === 'EMG' || ($deliveryOnly && ! $courierOnly))
            ? IpsOfficeContext::combinedOffices($request) : [];
        if ($combinedOffices !== []) {
            [$link, $combinedSelectionKey] = $this->context($request, $workflowKey ?? 'delivery');
        }
        $courierConfigurationMissing = $courierOnly && (
            ! $link
            || ! $link->active
            || $link->ips_office_cd === null
            || preg_match('/^[A-Za-z0-9]{2}$/', (string) $link->delivery_agent_cd) !== 1
        );
        $courierConfigurationMessage = ! $link || ! $link->active || $link->ips_office_cd === null
            ? 'Tu cuenta necesita un vínculo IPS activo con una oficina antes de consultar el reparto.'
            : 'Tu cuenta IPS está vinculada, pero falta asociarla a un cartero activo de su regional.';
        $workbenchPermissions = $workflowAction
            ? [
                'internacional.workflow.selection.add',
                'internacional.workflow.selection.remove',
                'internacional.workflow.operate',
            ]
            : ($deliveryOnly ? [
                'internacional.selection.add',
                'internacional.selection.remove',
                'internacional.operate',
            ] : []);
        $canUseWorkbench = collect($workbenchPermissions)->every(fn (string $permission): bool => $request->user()->can($permission));
        $readOnly = ($isInternationalPage && ! $deliveryOnly && ! $workflowAction)
            || $courierConfigurationMissing
            || ! $canUseWorkbench;
        $listingUrl = $section === 'almacen'
            ? route('internacional.warehouse', IpsOfficeContext::parameters($request, ['warehouseView' => $warehouseView]))
            : ($isInternationalPage
                ? route('internacional.section', IpsOfficeContext::parameters($request, array_filter(['section' => $section, 'receptacle_pid' => $preferredReceptaclePid])))
                : route('ips.index', IpsOfficeContext::parameters($request, [])));
        $listingClearUrl = $listingUrl;
        $selectionKey = $workflowKey
            ? 'ips_'.$workflowKey.'_workbench.'
            : ($courierOnly ? 'ips_my_delivery_workbench.' : ($deliveryOnly ? 'ips_delivery_workbench.' : 'ips_workbench.'));
        $resolvedSelectionKey = $combinedSelectionKey ?? $selectionKey.$request->user()->id.($link?->officeSessionSuffix() ?? '');
        $selection = $request->session()->get($resolvedSelectionKey, []);
        if ($workflowAction === 'EMG') {
            $selection = array_filter($selection, fn ($entry) => ($entry['status'] ?? null) !== 'succeeded');
            foreach ($selection as &$receiptEntry) {
                if (! empty($receiptEntry['needs_receipt_office'])) {
                    $receiptEntry['package']['receipt_office_locked_cd'] = $this->automaticReceiptOffice($receiptEntry['package'], $request);
                }
            }
            unset($receiptEntry);
            $request->session()->put($resolvedSelectionKey, $selection);
        }
        try {
            [$link] = $this->context($request, $workflowKey ?? ($courierOnly ? 'my_delivery' : ($deliveryOnly ? 'delivery' : 'standard')));
            if ($workflowAction === 'CUSTOMS_RETURN') {
                $catalog = $ips->catalog((int) $link->ips_office_cd) + $catalog;
                $openHere = collect($catalog['open_inbound_receptacles'] ?? [])->contains(
                    fn ($receptacle) => strtolower((string) data_get($receptacle, 'RECPTCL_PID')) === strtolower((string) $preferredReceptaclePid)
                );
                abort_unless($openHere, 409, 'La saca vinculada ya no está abierta en esta oficina. Vuelve a Recibir y abrir saca.');
            }
            if ($workflowAction && $search !== '') {
                try {
                    $package = ($combinedOffices !== []
                        ? (new IpsCombinedOfficeWorkbench($ips))->package($search, $combinedOffices, $workflowAction)
                        : $ips->package($search, (int) $link->ips_office_cd))['package'];
                    if (in_array($workflowAction, $package['allowed_actions'] ?? [], true)) {
                        $items = collect([$package]);
                        $total = 1;
                    } elseif ($workflowAction === 'EMG') {
                        $receiptOffice = (int) ($package['workbench_office_cd'] ?? $link->ips_office_cd);
                        if (! empty($package['receipt_office_options'])) {
                            $package['reception_block_reason'] = $package['receipt_office_note'];
                        } elseif ($this->markRouteMismatchReceipt($package, $receiptOffice)) {
                            $package['route_mismatch_note'] = 'La ruta IPS indica '.$package['next_office_name'].'; puedes revisar este paquete si llegó físicamente aquí. Se intentará registrar solo el evento 32 en esta oficina, sin crear un evento de cambio de ruta. IPS decidirá si lo permite.';
                        } else {
                            $package['reception_block_reason'] = $this->receptionBlockReason($package, $receiptOffice);
                            $emptyMessage = $package['reception_block_reason'];
                        }
                        $items = collect([$package]);
                        $total = 1;
                    }
                } catch (RequestException $e) {
                    if ($e->response->status() !== 404) {
                        throw $e;
                    }
                    if ($workflowAction === 'EMR') {
                        $missingPackageCode = $search;
                    } elseif ($workflowAction === 'EMG') {
                        $emptyMessage = 'Este código no aparece en IPS. Verifica el marbete o consulta el historial con el código correcto.';
                        if (preg_match('/^L[A-Z][0-9]{9}[A-Z]{2}$/', $search) === 1) {
                            $missingPackageCode = $search;
                        }
                    }
                }
            } elseif (! ($internationalWorkflow['search_only'] ?? false)) {
                $packageFilters = ['status' => $stage, 'office_cd' => $link->ips_office_cd, 'q' => $search, 'page' => $page, 'per_page' => 25];
                if ($warehousePage && isset($warehousePage['event_cd'])) {
                    $packageFilters['event_cd'] = $warehousePage['event_cd'];
                }
                if ($internationalWorkflow && isset($internationalWorkflow['event_cd'])) {
                    $packageFilters['event_cd'] = $internationalWorkflow['event_cd'];
                }
                if (!empty($warehousePage['by_me'])) {
                    $packageFilters['actor_user_pid'] = (int) $link->ips_user_pid;
                }
                if (!empty($warehousePage['delivery_agent'])) {
                    $packageFilters['delivery_agent_cd'] = $this->normalizeDeliveryAgentCode((string) $link->delivery_agent_cd);
                }
                $payload = $combinedOffices !== []
                    ? (new IpsCombinedOfficeWorkbench($ips))->packages($packageFilters, $combinedOffices)
                    : $ips->packages($packageFilters);
                $items = collect($payload['data'] ?? []);
                $total = (int) ($payload['meta']['total'] ?? 0);
            }
        } catch (HttpException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $error = 'No se pudo consultar IPS. Intente actualizar; no se modificó ningún paquete.';
        }
        if ($section === 'almacen' && $warehouseView === 'por-entregar') {
            $items = collect($this->withUpuRecipientData($items->all()));
        }
        $deliveryCatalogActions = ['HANDOVER_AGENT', 'EMH', 'CUSTOMS_PRESENT'];
        if ($link?->ips_office_cd !== null) {
            $items = $items->map(fn ($package) => IpsDeliveryReceipt::restrict($package, (int) ($package['workbench_office_cd'] ?? $link->ips_office_cd)));
            if ($workbenchActions !== null) {
                $items = $items->map(function ($package) use ($workbenchActions) {
                    $package['allowed_actions'] = array_values(array_intersect($package['allowed_actions'] ?? [], $workbenchActions));

                    return $package;
                });
            }
            foreach ($selection as &$selectedEntry) {
                $selectedEntry['package'] = IpsDeliveryReceipt::restrict($selectedEntry['package'], (int) ($selectedEntry['office_cd'] ?? $link->ips_office_cd));
                if ($workbenchActions !== null) {
                    $selectedEntry['package']['allowed_actions'] = array_values(array_intersect($selectedEntry['package']['allowed_actions'] ?? [], $workbenchActions));
                }
            }
            unset($selectedEntry);
        }
        $selectionNeedsDeliveryCatalog = collect($selection)->contains(fn ($entry) => array_intersect($deliveryCatalogActions, data_get($entry, 'package.allowed_actions', [])) !== []);
        $resultNeedsDeliveryCatalog = $items->contains(fn ($package) => array_intersect($deliveryCatalogActions, $package['allowed_actions'] ?? []) !== []);
        if (! $readOnly && ! $error && (! $deliveryOnly || $selectionNeedsDeliveryCatalog || $resultNeedsDeliveryCatalog)) {
            try {
                $officeCd = (int) $link->ips_office_cd;
                $catalog = Cache::remember('sitra:ips:delivery-catalog:v4:office:'.$officeCd, now()->addMinutes(5), fn () => $ips->deliveryCatalog($officeCd)) + $catalog;
            } catch (\Throwable) {
                // Keep package lookup available if the optional attempt catalog is unavailable.
            }
        }
        if (in_array($workflowAction, ['EMR', 'EMG'], true) && ! $error) {
            $pendingPrefix = $workflowAction === 'EMG' ? 'ips_destination_create_pending:' : 'ips_inbound_create_pending:';
            if ($workflowAction === 'EMG' && $search !== '') {
                foreach ($combinedOffices ?: [['office_cd' => $link->ips_office_cd]] as $pendingOffice) {
                    $pendingLink = clone $link;
                    $pendingLink->ips_office_cd = $pendingOffice['office_cd'];
                    $pending = $request->session()->get($pendingPrefix.$request->user()->id.$pendingLink->officeSessionSuffix().':'.$search);
                    if ($pending) {
                        $missingPackageCode = $search;
                        $inboundPending = $pending;
                        break;
                    }
                }
            }
            if ($missingPackageCode) {
                $pendingKey = $pendingPrefix.$request->user()->id.$link->officeSessionSuffix().':'.$missingPackageCode;
                $inboundPending ??= $request->session()->get($pendingKey);
                $inboundCreateKey = data_get($inboundPending, 'idempotency_key')
                    ?? old('idempotency_key')
                    ?? $request->session()->get('inbound_create_key')
                    ?? (string) Str::uuid();
            }
            try {
                if ($workflowAction === 'EMR' || $missingPackageCode) {
                    $catalog = $ips->catalog((int) $link->ips_office_cd) + $catalog;
                }
            } catch (\Throwable $e) {
                report($e);
                $error = 'No se pudo cargar el catálogo para dar de alta el envío. No se modificó IPS.';
            }
        }
        $paginationQuery = ['q' => $search];
        if ($preferredReceptaclePid) {
            $paginationQuery['receptacle_pid'] = $preferredReceptaclePid;
        }
        if (! $isInternationalPage) {
            $paginationQuery['stage'] = $stage;
        }
        $packages = new LengthAwarePaginator($items, $total, 25, $page, ['path' => $listingUrl, 'query' => $paginationQuery]);
        $pageTitle = $warehousePage['title'] ?? $internationalWorkflow['title'] ?? $internationalPage['title'] ?? 'Paquetes de tu oficina';
        $pageDescription = $warehousePage['description'] ?? $internationalWorkflow['description'] ?? $internationalPage['description'] ?? 'Gestiona los movimientos IPS de los paquetes asociados a tu oficina.';
        $actions = $workflowAction
            ? [$workflowAction => self::ACTIONS[$workflowAction]]
            : ($courierOnly
                ? array_intersect_key(self::DELIVERY_ACTION_LABELS, array_flip(['EDG', 'EMH', 'EMI']))
                : ($deliveryOnly ? array_intersect_key(self::DELIVERY_ACTION_LABELS, ['EMI' => true]) : array_intersect_key(self::ACTIONS, array_flip(['EMG', 'EDH', 'EDG', 'EMH', 'EMI']))));
        $deliveryActionLabels = $courierOnly
            ? array_intersect_key(self::DELIVERY_ACTION_LABELS, array_flip(['EDG', 'EMH', 'EMI']))
            : ($deliveryOnly ? array_intersect_key(self::DELIVERY_ACTION_LABELS, ['EMI' => true]) : self::DELIVERY_ACTION_LABELS);
        $selectionWorkflow = $courierOnly ? 'my_delivery' : 'delivery';
        $selectionAddUrl = $workflowAction
            ? route('internacional.workflow.selection.add', IpsOfficeContext::parameters($request, ['section' => $section]))
            : ($courierOnly
                ? route('internacional.my-delivery.selection.add', IpsOfficeContext::parameters($request, []))
                : ($deliveryOnly ? route('internacional.selection.add', IpsOfficeContext::parameters($request, [])) : route('ips.selection.add', IpsOfficeContext::parameters($request, []))));
        $selectionRemoveRouteName = $workflowAction
            ? 'internacional.workflow.selection.remove'
            : ($courierOnly ? 'internacional.my-delivery.selection.remove' : ($deliveryOnly ? 'internacional.selection.remove' : 'ips.selection.remove'));
        $operateUrl = $workflowAction
            ? route('internacional.workflow.operate', IpsOfficeContext::parameters($request, ['section' => $section]))
            : ($courierOnly
                ? route('internacional.my-delivery.operate', IpsOfficeContext::parameters($request, []))
                : ($deliveryOnly ? route('internacional.operate', IpsOfficeContext::parameters($request, [])) : route('ips.operate', IpsOfficeContext::parameters($request, []))));
        $operationLabel = $workflowAction ? self::ACTIONS[$workflowAction] : null;
        [$operationGroups, $operationGroup, $activeOperationTab, $warehouseTabs] = $this->operationNavigation($section, $warehouseView, null, $preferredReceptaclePid);
        $workflowHint = match ($workflowAction) {
            'EMR' => 'Escanea los marbetes encontrados; IPS registra un movimiento individual por paquete.',
            'EDN' => 'Agrupa solo paquetes compatibles con la misma oficina de destino.',
            'EIB' => 'Añade juntos los paquetes del mismo destino y clase a una saca nacional nueva.',
            'EMG' => 'La lista normal respeta la oficina exacta de destino IPS. Al escanear un paquete encaminado a otra oficina, confirma su llegada física para intentar solo el evento 32 aquí; no se agrega un evento de cambio de ruta y la recepción queda registrada únicamente si IPS la acepta.',
            'CUSTOMS_RETURN' => 'Escanea el paquete devuelto físicamente por Aduana. Este paso registra solo el evento 38; después se encamina a la oficina de entrega, donde se registra la llegada (evento 32).',
            default => null,
        };
        $viewData = compact(
            'packages', 'search', 'stage', 'selection', 'error', 'link', 'actions',
            'section', 'pageTitle', 'pageDescription',
            'warehouseView',
            'operationGroups', 'operationGroup', 'activeOperationTab', 'warehouseTabs',
            'isInternationalPage', 'deliveryOnly', 'courierOnly', 'selectionWorkflow', 'deliveryActionLabels', 'readOnly', 'listingUrl', 'listingClearUrl', 'workflowAction', 'workflowKey', 'operationLabel', 'workflowHint', 'preferredReceptaclePid',
            'courierConfigurationMissing', 'courierConfigurationMessage',
            'internationalWorkflow',
            'emptyMessage',
            'missingPackageCode', 'inboundPending', 'inboundCreateKey',
            'selectionAddUrl', 'selectionRemoveRouteName', 'operateUrl', 'catalog', 'combinedOffices'
        );
        if ($request->expectsJson()) {
            if ($error) {
                return response()->json(['message' => $error], 503)->header('Cache-Control', 'no-store');
            }

            return response()->json(['html' => view('paquetes_ips.partials.listing', $viewData)->render()])
                ->header('Cache-Control', 'no-store');
        }

        return view('paquetes_ips.index', $viewData);
    }

    public function warehouse(Request $request, SitraIpsClient $ips, string $warehouseView)
    {
        return $this->index($request, $ips, 'almacen', $warehouseView);
    }

    private function withUpuRecipientData(array $packages): array
    {
        $fields = ['recipient' => 'destinatario', 'phone' => 'telefono', 'address' => 'zona'];
        $codes = collect($packages)
            ->filter(fn (array $package) => collect(array_keys($fields))->contains(fn ($field) => blank($package[$field] ?? null)))
            ->pluck('codigo')->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return $packages;
        }
        $localPackages = PaqueteUpu::query()->whereIn('codigo', $codes)
            ->get(['codigo', ...array_values($fields)])->keyBy('codigo');

        return array_map(function (array $package) use ($fields, $localPackages): array {
            $local = $localPackages->get($package['codigo']);
            if ($local) {
                foreach ($fields as $remoteField => $localField) {
                    if (blank($package[$remoteField] ?? null) && filled($local->{$localField})) {
                        $package[$remoteField] = $local->{$localField};
                    }
                }
            }

            return $package;
        }, $packages);
    }

    public function addDeliverySelection(Request $request, SitraIpsClient $ips)
    {
        return $this->addSelection($request, $ips, 'delivery');
    }

    public function addMyDeliverySelection(Request $request, SitraIpsClient $ips)
    {
        return $this->addSelection($request, $ips, 'my_delivery');
    }

    public function addInternationalSelection(Request $request, SitraIpsClient $ips, string $section)
    {
        $definition = self::INTERNATIONAL_WORKFLOWS[$section] ?? abort(404);

        return $this->addSelection($request, $ips, $this->internationalWorkflowKey($request, $section, $definition), $definition['action'], $section);
    }

    public function addSelection(Request $request, SitraIpsClient $ips, string $workflow = 'standard', ?string $requiredAction = null, ?string $section = null)
    {
        [$link, $key] = $this->context($request, $workflow);
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'workflow' => ['nullable', 'in:delivery,my_delivery'],
            'receptacle_pid' => ['nullable', 'uuid'],
            'receipt_office_cd' => [$requiredAction === 'EMG' && $request->attributes->get('ips_combined_offices') ? 'nullable' : 'prohibited', 'integer',
                \Illuminate\Validation\Rule::in(array_column($request->attributes->get('ips_combined_offices', []), 'office_cd'))],
        ]);
        try {
            $combinedOffices = $request->attributes->get('ips_combined_offices', []);
            $existingReceipt = $request->session()->get($key, [])[strtoupper($data['codigo'])] ?? null;
            $receiptOffice = ! empty($existingReceipt['route_mismatch_receipt']) ? ($existingReceipt['office_cd'] ?? null) : null;
            $package = ($combinedOffices !== []
                ? (new IpsCombinedOfficeWorkbench($ips))->package(strtoupper($data['codigo']), $combinedOffices, $requiredAction ?? 'EMI', $receiptOffice !== null ? (int) $receiptOffice : null)
                : $ips->package(strtoupper($data['codigo']), (int) $link->ips_office_cd))['package'];
            if ($requiredAction === 'EMG' && $combinedOffices !== []) {
                $fixedOffice = $this->automaticReceiptOffice($package, $request);
                if (isset($data['receipt_office_cd']) && (int) $data['receipt_office_cd'] !== $fixedOffice) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'receipt_office_cd' => 'La oficina asignada automáticamente no se puede cambiar.',
                    ]);
                }
                if (isset($data['receipt_office_cd']) && $receiptOffice === null && ! empty($package['receipt_office_options'])) {
                    $fixed = collect($combinedOffices)->firstWhere('office_cd', $fixedOffice);
                    $package = $ips->package($package['codigo'], $fixedOffice)['package'];
                    $package['workbench_office_cd'] = $fixedOffice;
                    $package['workbench_office_name'] = $fixed['office_name'];
                    $package['workbench_office_fcd'] = $fixed['office_fcd'];
                    $receiptOffice = $fixedOffice;
                }
                $package['receipt_office_locked_cd'] = $fixedOffice;
            }
            if ($combinedOffices !== []) {
                $request->merge(['office_cd' => $package['workbench_office_cd']]);
                $link = IpsOfficeContext::resolve($request);
            }
            $office = (int) $link->ips_office_cd;
            $package = IpsDeliveryReceipt::restrict($package, $office);
            $needsReceiptOffice = $requiredAction === 'EMG' && ! empty($package['receipt_office_options']) && $receiptOffice === null;
            if ($needsReceiptOffice) {
                // Prepare the tray with a fixed office; IPS is rechecked on confirmation.
                $package['allowed_actions'] = ['EMG'];
                $package['needs_receipt_office'] = true;
            }
            $deliveryWorkflow = in_array($workflow, ['delivery', 'my_delivery'], true);
            $routeMismatchReceipt = false;
            $receptaclePid = isset($data['receptacle_pid']) ? strtolower((string) $data['receptacle_pid']) : null;
            if ($requiredAction === 'CUSTOMS_RETURN') {
                abort_unless($section === 'devolucion-aduana' && $receptaclePid !== null, 422, 'Escanea la devolución desde una saca recibida y abierta en esta oficina.');
                $openReceptacles = $ips->catalog($office)['open_inbound_receptacles'] ?? [];
                $openHere = collect($openReceptacles)->contains(fn ($receptacle) => strtolower((string) data_get($receptacle, 'RECPTCL_PID')) === $receptaclePid);
                abort_unless($openHere, 409, 'La saca debe estar recibida y abierta en esta oficina antes de registrar sus devoluciones de Aduana.');
                abort_unless(
                    hash_equals($receptaclePid, strtolower(trim((string) ($package['receptacle_pid'] ?? '')))),
                    409,
                    'Este paquete no pertenece a la saca abierta seleccionada. Abre la saca que IPS registra para el paquete.'
                );
            }
            if ($workflow === 'my_delivery') {
                $this->assertAssignedCourierPackage($package, (string) $link->delivery_agent_cd);
            }
            if ($requiredAction !== null) {
                if ($requiredAction === 'EMG'
                    && (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0) === 32) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'error_code' => 'already_received',
                            'message' => 'Este paquete ya está recibido. Continúa en Almacén internacional → Por entregar.',
                            'code' => $package['codigo'],
                        ], 409);
                    }
                    abort(409, 'Este paquete ya fue recibido en la oficina (evento 32). No lo recibas otra vez; continúa en Almacén internacional → Por entregar.');
                }
                if ($requiredAction === 'EMG'
                    && in_array((int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0), [31, 34, 1251], true)
                    && (int) ($package['state_cd'] ?? -1) === 1) {
                    abort(409, $this->receptionBlockReason($package, $office));
                }
                if ($requiredAction === 'EMG' && ! in_array($requiredAction, $package['allowed_actions'] ?? [], true)) {
                    $routeMismatchReceipt = $this->markRouteMismatchReceipt($package, $office);
                    abort_unless($routeMismatchReceipt, 409, $this->receptionBlockReason($package, $office));
                } else {
                    abort_unless(in_array($requiredAction, $package['allowed_actions'] ?? [], true), 409, 'IPS no habilita este movimiento para el paquete y la oficina vinculada.');
                }
                if ($requiredAction === 'EDN') {
                    abort_unless(! empty($package['next_offices']), 409, 'IPS no tiene una oficina de ruta habilitada para este envío.');
                }
                if ($requiredAction === 'EIB') {
                    abort_unless(! empty($package['domestic_receptacles']), 409, 'No hay una saca nacional abierta compatible con este envío.');
                }
            } elseif (! $deliveryWorkflow) {
                abort_unless(in_array($office, [(int) ($package['operational_office_cd'] ?? $package['office_cd']), (int) ($package['next_office_cd'] ?? 0)], true), 403, 'El paquete no corresponde a su oficina IPS.');
            }
            if ($deliveryWorkflow) {
                $allowedDeliveryActions = $workflow === 'my_delivery'
                    ? ['EDG', 'EMH', 'EMI']
                    : ['EMI'];
                abort_unless(array_intersect($allowedDeliveryActions, $package['allowed_actions'] ?? []) !== [], 409,
                    $package['delivery_block_reason'] ?? 'IPS no habilita movimientos de distribución para este paquete y oficina. Actualiza el listado.');
            }
            $selectionActions = $requiredAction !== null ? [$requiredAction]
                : ($deliveryWorkflow ? $allowedDeliveryActions : null);
            if ($selectionActions !== null) {
                $package['allowed_actions'] = array_values(array_intersect($package['allowed_actions'] ?? [], $selectionActions));
            }
            $selection = $request->session()->get($key, []);
            $code = $package['codigo'];
            if (isset($selection[$code])) {
                if ($requiredAction === 'CUSTOMS_RETURN'
                    && strtolower((string) ($selection[$code]['receptacle_pid'] ?? '')) !== $receptaclePid) {
                    abort(409, 'Este paquete ya está en la bandeja asociado a otra saca. Retíralo y vuelve a escanearlo desde la saca correcta.');
                }
                if ($request->expectsJson()) {
                    $removeRoute = $requiredAction !== null && ! $deliveryWorkflow
                        ? route('internacional.workflow.selection.remove', IpsOfficeContext::parameters($request, ['section' => $section, 'codigo' => $code] + ($requiredAction === 'CUSTOMS_RETURN' ? ['receptacle_pid' => $receptaclePid] : [])))
                        : route($workflow === 'my_delivery' ? 'internacional.my-delivery.selection.remove' : ($workflow === 'delivery' ? 'internacional.selection.remove' : 'ips.selection.remove'), IpsOfficeContext::parameters($request, ['codigo' => $code]));

                    return response()->json([
                        'code' => $code,
                        'package' => $selection[$code]['package'] ?? $package,
                        'receptacle_pid' => $selection[$code]['receptacle_pid'] ?? null,
                        'route_mismatch_receipt' => ! empty($selection[$code]['route_mismatch_receipt']),
                        'count' => count($selection),
                        'remove_url' => $removeRoute,
                    ]);
                }
                return back()->with('success', 'El paquete ya está en la bandeja.');
            }
            abort_if(count($selection) >= 50, 422, 'La bandeja admite hasta 50 paquetes.');
            $selection[$code] = ['package' => $package, 'office_cd' => (int) $link->ips_office_cd, 'needs_receipt_office' => $needsReceiptOffice, 'receptacle_pid' => $receptaclePid, 'route_mismatch_receipt' => $routeMismatchReceipt, 'status' => 'ready', 'message' => null, 'payload' => null];
            $request->session()->put($key, $selection);

            if ($request->expectsJson()) {
                $removeRoute = $requiredAction !== null && ! $deliveryWorkflow
                    ? route('internacional.workflow.selection.remove', IpsOfficeContext::parameters($request, ['section' => $section, 'codigo' => $code] + ($requiredAction === 'CUSTOMS_RETURN' ? ['receptacle_pid' => $receptaclePid] : [])))
                    : route($workflow === 'my_delivery' ? 'internacional.my-delivery.selection.remove' : ($workflow === 'delivery' ? 'internacional.selection.remove' : 'ips.selection.remove'), IpsOfficeContext::parameters($request, ['codigo' => $code]));

                return response()->json(['code' => $code, 'package' => $package, 'receptacle_pid' => $receptaclePid, 'route_mismatch_receipt' => $routeMismatchReceipt, 'count' => count($selection), 'remove_url' => $removeRoute]);
            }

            return back()->with('success', 'Paquete guardado en la bandeja.');
        } catch (RequestException $e) {
            $message = $e->response->json('message') ?? 'No se pudo consultar el paquete en IPS.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], $e->response->status());
            }

            return back()->withErrors(['ips' => $message]);
        }
    }

    public function removeDeliverySelection(Request $request, string $codigo)
    {
        return $this->removeSelection($request, $codigo, 'delivery');
    }

    public function removeMyDeliverySelection(Request $request, string $codigo)
    {
        return $this->removeSelection($request, $codigo, 'my_delivery');
    }

    public function removeInternationalSelection(Request $request, string $section, string $codigo)
    {
        $definition = self::INTERNATIONAL_WORKFLOWS[$section] ?? abort(404);

        return $this->removeSelection($request, $codigo, $this->internationalWorkflowKey($request, $section, $definition));
    }

    public function removeSelection(Request $request, string $codigo, string $workflow = 'standard')
    {
        [, $key] = $this->context($request, $workflow);
        $selection = $request->session()->get($key, []);
        $entry = $selection[strtoupper($codigo)] ?? null;
        abort_if($entry && in_array($entry['status'], ['processing', 'uncertain'], true), 409, 'Primero consulte o reintente la misma operación para confirmar su resultado.');
        unset($selection[strtoupper($codigo)]);
        $request->session()->put($key, $selection);

        if ($request->expectsJson()) {
            return response()->json(['removed' => true, 'code' => strtoupper($codigo), 'count' => count($selection)]);
        }
        return back();
    }

    public function createDestinationPackage(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents)
    {
        return $this->createInboundPackage($request, $ips, $trackingEvents, true);
    }

    public function createInboundPackage(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, bool $destinationReceipt = false)
    {
        [$link] = $this->context($request, $destinationReceipt ? 'international_emg' : 'international_emr');
        $section = $destinationReceipt ? 'recepcion-oficina' : 'recepcion-cambio';
        $pendingPrefix = $destinationReceipt ? 'ips_destination_create_pending:' : 'ips_inbound_create_pending:';
        $code = strtoupper(trim((string) $request->input('codigo')));
        $request->merge(['codigo' => $code]);
        $pendingKey = $pendingPrefix.$request->user()->id.$link->officeSessionSuffix().':'.$code;
        $pending = $code !== '' ? $request->session()->get($pendingKey) : null;
        $rules = [
            'codigo' => ['required', 'string', 'max:35', $destinationReceipt ? 'regex:/^L[A-Z][0-9]{9}[A-Z]{2}$/' : 'regex:/^[A-Za-z0-9-]+$/'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9_.:-]+$/'],
        ];
        if ($destinationReceipt && $request->attributes->get('ips_combined_offices')) {
            $rules['office_cd'] = ['required', 'integer', \Illuminate\Validation\Rule::in(array_column($request->attributes->get('ips_combined_offices'), 'office_cd'))];
        }
        if (! $pending) {
            $rules += [
                'weight_kg' => ['required', 'numeric', 'gt:0', 'max:999.999', 'decimal:0,3'],
                'mail_class' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z0-9]$/'],
                'condition_cd' => $destinationReceipt ? ['prohibited'] : ['required', 'integer', 'min:1'],
                'origin_country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', ...($destinationReceipt ? ['not_in:BO'] : [])],
                'receptacle_pid' => $destinationReceipt ? ['prohibited'] : ['required', 'uuid'],
                'physical_receipt_confirmed' => ['required', 'accepted'],
                'sender' => ['required', 'array:name,address,city,country,postcode,phone,email'],
                'recipient' => ['required', 'array:name,address,city,country,postcode,phone,email'],
                'sender.name' => ['required', 'string', 'max:64'],
                'sender.address' => ['required', 'string', 'max:105'],
                'sender.city' => ['required', 'string', 'max:32'],
                'sender.country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'sender.postcode' => ['nullable', 'string', 'max:20'],
                'sender.phone' => ['nullable', 'string', 'max:64'],
                'sender.email' => ['nullable', 'email', 'max:64'],
                'recipient.name' => ['required', 'string', 'max:64'],
                'recipient.address' => ['required', 'string', 'max:105'],
                'recipient.city' => ['required', 'string', 'max:32'],
                'recipient.country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', ...($destinationReceipt ? ['in:BO'] : [])],
                'recipient.postcode' => ['nullable', 'string', 'max:20'],
                'recipient.phone' => ['nullable', 'string', 'max:64'],
                'recipient.email' => ['nullable', 'email', 'max:64'],
            ];
        }
        $data = $request->validate($rules);
        $code = strtoupper(trim($data['codigo']));
        $pendingKey = $pendingPrefix.$request->user()->id.$link->officeSessionSuffix().':'.$code;

        if ($pending) {
            abort_unless(hash_equals((string) $pending['idempotency_key'], (string) $data['idempotency_key']), 409, 'La solicitud original está pendiente. Consulte con la misma clave antes de iniciar otra.');
            $payload = $pending;
        } else {
            if ($destinationReceipt) {
                abort_unless(trim((string) $request->user()->ciudad) !== '', 422, 'Configure el departamento del usuario antes de recibir paquetes.');
                IpsOfficeContext::assertCurrentAssignment($link, $ips);
            }
            $effectiveAt = Carbon::now('America/La_Paz');
            $payload = [
                'event' => $destinationReceipt ? 'EMG' : 'EMD',
                'codigo' => $code,
                'occurred_at' => $effectiveAt->format('Y-m-d\\TH:i:sP'),
                'office_cd' => (int) $link->ips_office_cd,
                'actor_user_pid' => (int) $link->ips_user_pid,
                'external_actor_id' => 'bolipost:'.$request->user()->id,
                'idempotency_key' => $data['idempotency_key'],
                'mail_class' => strtoupper($data['mail_class']),
                'origin_country' => strtoupper($data['origin_country']),
                'destination_country' => 'BO',
                'weight_kg' => (float) $data['weight_kg'],
                'physical_receipt_confirmed' => true,
                'sender' => $data['sender'],
                'recipient' => $data['recipient'],
            ];
            if (! $destinationReceipt) {
                $payload['condition_cd'] = (int) $data['condition_cd'];
                $payload['receptacle_pid'] = $data['receptacle_pid'];
            } else {
                $request->session()->put($pendingKey.':receipt', $this->upuReceiptData($code, [
                    'recipient' => $payload['recipient']['name'],
                    'phone' => $payload['recipient']['phone'] ?? null,
                    'address' => $payload['recipient']['address'],
                    'weight_kg' => $payload['weight_kg'],
                ], trim((string) $request->user()->ciudad)));
            }
            $request->session()->put($pendingKey, $payload);
            $request->session()->save();
        }

        try {
            $result = $ips->createPackage($payload);
            if (($result['status'] ?? null) === 'succeeded') {
                if ($destinationReceipt) {
                    PaqueteUpu::firstOrCreate(['codigo' => $code], $request->session()->get($pendingKey.':receipt'));
                }
                $request->session()->forget([$pendingKey, $pendingKey.':receipt']);
                $trackingEvents->forget($code);

                return redirect()->route($destinationReceipt ? 'internacional.warehouse' : 'internacional.section', IpsOfficeContext::parameters($request, $destinationReceipt ? ['warehouseView' => 'por-entregar', 'q' => $code] : ['section' => $section, 'q' => $code]))
                    ->with('success', $destinationReceipt ? 'Envío L creado y recibido en IPS. Ya puedes registrar su entrega al destinatario.' : 'Paquete creado y recepción en oficina de cambio confirmada por IPS.');
            }
            if (($result['status'] ?? null) === 'rejected') {
                $request->session()->forget([$pendingKey, $pendingKey.':receipt']);
                $formInput = $this->inboundCreateFormInput($payload);
                $request->session()->flash('inbound_create_key', $formInput['idempotency_key']);

                return redirect()->route('internacional.section', IpsOfficeContext::parameters($request, ['section' => $section, 'q' => $code]))
                    ->withErrors(['ips' => $result['message'] ?? 'IPS rechazó el registro. Revise los datos y vuelva a intentarlo.'])
                    ->withInput($formInput);
            }

            return redirect()->route('internacional.section', IpsOfficeContext::parameters($request, ['section' => $section, 'q' => $code]))
                ->withErrors(['ips' => $result['message'] ?? 'La operación sigue pendiente. Reintente con la misma clave para consultar el resultado.']);
        } catch (RequestException $e) {
            $result = $e->response->json() ?? [];
            $status = $result['status'] ?? ($e->response->status() >= 500 ? 'uncertain' : 'rejected');
            if ($status === 'rejected') {
                $request->session()->forget([$pendingKey, $pendingKey.':receipt']);
                $formInput = $this->inboundCreateFormInput($payload);
                $request->session()->flash('inbound_create_key', $formInput['idempotency_key']);

                return redirect()->route('internacional.section', IpsOfficeContext::parameters($request, ['section' => $section, 'q' => $code]))
                    ->withErrors(['ips' => $result['message'] ?? 'IPS rechazó el registro. Revise los datos y vuelva a intentarlo.'])
                    ->withInput($formInput);
            }

            return redirect()->route('internacional.section', IpsOfficeContext::parameters($request, ['section' => $section, 'q' => $code]))
                ->withErrors(['ips' => $result['message'] ?? 'No se pudo confirmar. Si queda pendiente, reintente exactamente la misma operación.']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('internacional.section', IpsOfficeContext::parameters($request, ['section' => $section, 'q' => $code]))
                ->withErrors(['ips' => 'No se pudo confirmar el resultado. Reintente la operación original; no se generará otra clave.']);
        }
    }

    private function inboundCreateFormInput(array $payload): array
    {
        return [
            'codigo' => $payload['codigo'],
            'office_cd' => $payload['office_cd'],
            'idempotency_key' => (string) Str::uuid(),
            'weight_kg' => $payload['weight_kg'],
            'mail_class' => $payload['mail_class'],
            'condition_cd' => $payload['condition_cd'] ?? null,
            'origin_country' => $payload['origin_country'],
            'receptacle_pid' => $payload['receptacle_pid'] ?? null,
            'physical_receipt_confirmed' => true,
            'sender' => $payload['sender'],
            'recipient' => $payload['recipient'],
        ];
    }

    public function operateDelivery(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents)
    {
        return $this->operate($request, $ips, $trackingEvents, 'delivery');
    }

    public function operateMyDelivery(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents)
    {
        return $this->operate($request, $ips, $trackingEvents, 'my_delivery');
    }

    public function operateInternational(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, string $section)
    {
        $definition = self::INTERNATIONAL_WORKFLOWS[$section] ?? abort(404);

        return $this->operate($request, $ips, $trackingEvents, $this->internationalWorkflowKey($request, $section, $definition), $definition['action']);
    }

    public function operate(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, string $workflow = 'standard', ?string $requiredAction = null)
    {
        [$link, $key] = $this->context($request, $workflow);
        $actionRule = $requiredAction ?? match ($workflow) {
            'delivery' => 'EMI',
            'my_delivery' => 'EDG,EMH,EMI',
            default => 'EMG,EDH,EDG,EMH,EMI',
        };
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35'],
            'action' => ['required', 'in:'.$actionRule],
            'confirmed' => ['required', 'accepted'],
            'customs_return_confirmed' => ['prohibited'],
            'physical_receipt_confirmed' => ['sometimes', 'accepted'],
            'route_mismatch_confirmed' => ['sometimes', 'accepted'],
            'receipt_office_cd' => [$requiredAction === 'EMG' && $request->attributes->get('ips_combined_offices') ? 'sometimes' : 'prohibited', 'integer',
                \Illuminate\Validation\Rule::in(array_column($request->attributes->get('ips_combined_offices', []), 'office_cd'))],
            'receptacle_pid' => ['sometimes', 'uuid'],
            'signatory' => ['nullable', 'string', 'max:64'],
            'zona' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'upu_receipt' => [$requiredAction === 'EMG' ? 'sometimes' : 'prohibited', 'array:destinatario,telefono,zona,peso'],
            'upu_receipt.destinatario' => ['required_with:upu_receipt', 'string', 'max:255'],
            'upu_receipt.telefono' => ['nullable', 'string', 'max:64'],
            'upu_receipt.zona' => ['nullable', 'string', 'max:1000'],
            'upu_receipt.peso' => ['required_with:upu_receipt', 'numeric', 'gt:0', 'max:999.999', 'decimal:0,3'],
            'delivery_mode' => ['required_if:action,EMI', 'nullable', 'in:DOMICILIARIA,NO_DOMICILIARIA'],
            'non_delivery_reason' => ['required_if:action,EMH', 'nullable', 'integer', 'min:1'],
            'non_delivery_measure' => ['required_if:action,EMH', 'nullable', 'string', 'size:1'],
            'next_office_cd' => ['required_if:action,EDN', 'nullable', 'integer', 'min:1', 'max:32767'],
            'domestic_receptacle_pid' => ['required_if:action,EIB', 'nullable', 'uuid'],
            'exchange_receptacle_pid' => ['required_if:action,EMR', 'nullable', 'uuid'],
            'condition_cd' => ['required_if:action,EMR', 'nullable', 'integer', 'min:1'],
            'delivery_agent_cd' => ['required_if:action,HANDOVER_AGENT', 'nullable', 'string', 'size:2', 'regex:/^[A-Za-z0-9]{2}$/'],
            'customs_office_cd' => ['required_if:action,CUSTOMS_PRESENT', 'nullable', 'integer', 'min:1', 'max:32767'],
            'customs_office_type_cd' => ['required_if:action,CUSTOMS_PRESENT', 'nullable', 'string', 'max:3', 'regex:/^[A-Za-z0-9]+$/'],
        ]);
        if (in_array($data['action'], ['EMR', 'EMG', 'CUSTOMS_RETURN'], true) && ! $request->boolean('physical_receipt_confirmed')) {
            return response()->json([
                'status' => 'needs_physical_confirmation',
                'message' => $data['action'] === 'CUSTOMS_RETURN'
                    ? 'Confirme que Aduana devolvió físicamente el envío a esta oficina.'
                    : 'Confirme que el envío llegó físicamente a esta oficina antes de registrar la recepción.',
            ], 422);
        }
        $effectiveAt = Carbon::now('America/La_Paz');
        $selection = $request->session()->get($key, []);
        $code = strtoupper($data['codigo']);
        abort_unless(isset($selection[$code]), 422, 'Agregue el paquete a la bandeja primero.');
        $entry = $selection[$code];
        if ($requiredAction === 'EMG' && isset($data['receipt_office_cd'])) {
            $fixedOffice = ! empty($entry['needs_receipt_office'])
                ? $this->automaticReceiptOffice($entry['package'], $request)
                : (int) ($entry['payload']['office_cd'] ?? $entry['office_cd'] ?? $link->ips_office_cd);
            if ((int) $data['receipt_office_cd'] !== $fixedOffice) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'receipt_office_cd' => 'La oficina asignada automáticamente no se puede cambiar.',
                ]);
            }
        }
        if ($requiredAction === 'EMG' && ! empty($entry['needs_receipt_office']) && empty($entry['payload'])) {
            $officeCd = $this->automaticReceiptOffice($entry['package'], $request);
            if ($officeCd === null || ! in_array($officeCd, array_column($entry['package']['receipt_office_options'] ?? [], 'office_cd'), true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'receipt_office_cd' => 'La oficina asignada automáticamente no está disponible para este paquete.',
                ]);
            }
            $request->merge(['office_cd' => $officeCd]);
            $link = IpsOfficeContext::resolve($request);
            $selection[$code]['office_cd'] = $officeCd;
            $selection[$code]['route_mismatch_receipt'] = true;
            $selection[$code]['needs_receipt_office'] = false;
            $selection[$code]['package']['workbench_office_cd'] = $officeCd;
            $selection[$code]['package']['workbench_office_name'] = $link->ips_office_name;
            $selection[$code]['package']['workbench_office_fcd'] = $link->ips_office_fcd;
            unset($selection[$code]['package']['receipt_office_options'], $selection[$code]['package']['receipt_office_note'], $selection[$code]['package']['needs_receipt_office']);
            $entry = $selection[$code];
        }
        if ($requiredAction === 'EMG' && empty($entry['upu_receipt'])) {
            $destination = trim((string) $request->user()->ciudad);
            abort_unless($destination !== '', 422, 'Configure el departamento del usuario antes de recibir paquetes.');
            $selection[$code]['upu_receipt'] = $this->upuReceiptData($code, $entry['package'], $destination);
            $entry = $selection[$code];
        }
        if ($entry['status'] === 'succeeded') {
            if ($requiredAction === 'EMG') {
                PaqueteUpu::firstOrCreate(['codigo' => $code], $entry['upu_receipt']);
                unset($selection[$code]);
                $request->session()->put($key, $selection);

                return response()->json(['status' => 'succeeded', 'message' => 'Este paquete ya está recibido y registrado en UPU.', 'upu_saved' => true, 'operation_id' => $entry['operation_id'] ?? null, 'selection_removed' => true, 'code' => $code, 'count' => count($selection)]);
            }
            return response()->json(['status' => 'succeeded', 'message' => 'Operación ya confirmada.', 'operation_id' => $entry['operation_id'] ?? null]);
        }
        $routeMismatchReceipt = ! empty($entry['route_mismatch_receipt']);
        abort_unless(! $routeMismatchReceipt || $requiredAction === 'EMG', 409, 'La recepción cruzada solo puede registrar el evento 32.');
        abort_unless(! $request->boolean('route_mismatch_confirmed') || $routeMismatchReceipt, 409, 'La confirmación de ruta distinta no corresponde a este paquete.');
        abort_unless(! $routeMismatchReceipt || $request->boolean('route_mismatch_confirmed'), 422, 'Confirma que el paquete con ruta distinta llegó físicamente a esta oficina antes de intentar recibirlo.');
        $payload = $entry['payload'] ?? null;
        abort_if($data['action'] === 'EMI' && ! empty($payload['customs_return_confirmed']), 409,
            'Primero debes recibir el paquete en esta oficina (evento 32). La devolución de Aduana y la baja deben registrarse por separado.');
        if ($payload && $payload['event'] !== $data['action']) {
            return response()->json(['status' => 'rejected', 'message' => 'Esta operación ya tiene una acción registrada. Mantenga la misma acción para consultar su resultado.'], 409);
        }
        if (! $payload && in_array($data['action'], ['EMG', 'EMI'], true)) {
            IpsOfficeContext::assertCurrentAssignment($link, $ips);
        }
        try {
            if (! $payload) {
                // Recheck before writing; optimistic concurrency still runs inside IPS's SQL transaction.
                $current = $ips->package($code, (int) $link->ips_office_cd)['package'];
                if ($workflow === 'my_delivery') {
                    $this->assertAssignedCourierPackage($current, (string) $link->delivery_agent_cd);
                }
                if ($data['action'] === 'EMI') {
                    abort_unless(IpsDeliveryReceipt::confirmed($current, (int) $link->ips_office_cd), 409,
                        'Primero debes recibir el paquete en esta oficina (evento 32). Después podrás registrar la baja.');
                }
                $routeMismatchAttempt = $routeMismatchReceipt
                    && $request->boolean('route_mismatch_confirmed')
                    && $this->isRouteMismatchReceiptCandidate($current, (int) $link->ips_office_cd);
                abort_unless(
                    in_array($data['action'], $current['allowed_actions'] ?? [], true) || $routeMismatchAttempt,
                    409,
                    'La etapa u oficina ya no permite esta acción. Actualice el paquete.'
                );
                abort_unless($current['event_cd'] === $entry['package']['event_cd'] && $current['event_at'] === $entry['package']['event_at'], 409, 'El paquete cambió desde que se agregó. Quítelo y vuelva a buscarlo.');
                $payload = [
                    'event' => $data['action'], 'codigo' => $code,
                    'occurred_at' => $effectiveAt->format('Y-m-d\\TH:i:sP'),
                    'office_cd' => (int) $link->ips_office_cd,
                    'actor_user_pid' => (int) $link->ips_user_pid,
                    'external_actor_id' => 'bolipost:'.$request->user()->id,
                    'expected_event_cd' => $current['event_cd'], 'expected_event_at' => $current['event_at'],
                    'idempotency_key' => 'bolipost-'.Str::uuid(),
                ];
                if (in_array($data['action'], ['EMR', 'EMG', 'CUSTOMS_RETURN'], true)) {
                    $payload['physical_receipt_confirmed'] = $request->boolean('physical_receipt_confirmed');
                }
                if ($data['action'] === 'EMG' && $routeMismatchAttempt) {
                    $payload['route_mismatch_confirmed'] = true;
                }
                if ($data['action'] === 'EDN') {
                    $nextOffice = (int) $data['next_office_cd'];
                    $validNextOffice = collect($current['next_offices'] ?? [])
                        ->contains(fn ($office) => (int) data_get($office, 'OWN_OFFICE_CD') === $nextOffice);
                    abort_unless($validNextOffice, 422, 'Seleccione una oficina habilitada por IPS para esta ruta.');
                    $payload['next_office_cd'] = $nextOffice;
                }
                if ($data['action'] === 'EIB') {
                    $receptaclePid = (string) $data['domestic_receptacle_pid'];
                    $validReceptacle = collect($current['domestic_receptacles'] ?? [])
                        ->contains(fn ($receptacle) => (string) data_get($receptacle, 'DOM_REC_PID') === $receptaclePid);
                    abort_unless($validReceptacle, 422, 'Seleccione una saca abierta que IPS habilita para este envío.');
                    $payload['domestic_receptacle_pid'] = $receptaclePid;
                }
                if ($data['action'] === 'CUSTOMS_RETURN') {
                    $expectedReceptaclePid = strtolower((string) ($entry['receptacle_pid'] ?? ''));
                    abort_unless($expectedReceptaclePid !== ''
                        && hash_equals($expectedReceptaclePid, strtolower((string) ($data['receptacle_pid'] ?? ''))),
                        409,
                        'El paquete debe registrarse desde la misma saca abierta en la que se escaneó.'
                    );
                    abort_unless(
                        hash_equals($expectedReceptaclePid, strtolower(trim((string) ($current['receptacle_pid'] ?? '')))),
                        409,
                        'El paquete ya no pertenece a la saca seleccionada. Actualiza el paso y escanéalo desde la saca vigente.'
                    );
                    $payload['receptacle_pid'] = $expectedReceptaclePid;
                }
                if ($data['action'] === 'EMR') {
                    $receptaclePid = (string) $data['exchange_receptacle_pid'];
                    $validReceptacle = collect($current['exchange_receptacles'] ?? [])
                        ->contains(fn ($receptacle) => (string) data_get($receptacle, 'RECPTCL_PID') === $receptaclePid);
                    abort_unless($validReceptacle, 422, 'Seleccione un envase internacional abierto en esta oficina de cambio.');
                    $payload['receptacle_pid'] = $receptaclePid;
                    $payload['condition_cd'] = (int) $data['condition_cd'];
                }
                if ($data['action'] === 'HANDOVER_AGENT') {
                    if ((int) ($current['operational_event_cd'] ?? $current['event_cd']) === 36) {
                        // Asignar el paquete para otro intento confirma su recepción física en esta oficina.
                        $payload['physical_return_confirmed'] = true;
                    }
                    $payload['delivery_agent_cd'] = strtoupper($data['delivery_agent_cd']);
                }
                if ($data['action'] === 'CUSTOMS_PRESENT') {
                    $payload['customs_office_cd'] = (int) $data['customs_office_cd'];
                    $payload['customs_office_type_cd'] = strtoupper($data['customs_office_type_cd']);
                }
                // Signatory is the actual recipient, never the employee or an assumed name.
                if ($data['action'] === 'EMI' && ! empty($data['signatory'])) {
                    $payload['signatory'] = $data['signatory'];
                }
                if ($data['action'] === 'EMI') {
                    $payload['delivery_mode'] = $data['delivery_mode'];
                }
                if ($data['action'] === 'EMH') {
                    $payload['non_delivery_reason'] = (int) $data['non_delivery_reason'];
                    $payload['non_delivery_measure'] = $data['non_delivery_measure'];
                }
                $selection[$code]['payload'] = $payload;
                if ($requiredAction === 'EMG') {
                    $selection[$code]['upu_receipt'] = $this->upuReceiptData($code, $current, $selection[$code]['upu_receipt']['destino']);
                    if (array_key_exists('zona', $data)) {
                        $selection[$code]['upu_receipt']['zona'] = $data['zona'];
                    }
                    $selection[$code]['upu_receipt'] = array_replace($selection[$code]['upu_receipt'],
                        array_intersect_key($data['upu_receipt'] ?? [], array_flip(['destinatario', 'telefono', 'zona', 'peso'])));
                }
                $selection[$code]['status'] = 'processing';
                $request->session()->put($key, $selection);
                $request->session()->save();
            }
            $result = $payload['event'] === 'EMI' ? $ips->deliver($code, $payload) : $ips->event($code, $payload);
        } catch (RequestException $e) {
            $result = $e->response->json() ?? [];
            $httpStatus = $e->response->status();
            $message = $result['message'] ?? '';
            if ($httpStatus >= 500) {
                Log::warning('SITRA IPS no pudo confirmar el movimiento.', [
                    'http_status' => $httpStatus,
                    'action' => $data['action'],
                    'office_cd' => (int) $link->ips_office_cd,
                    'operation_id' => $result['operation_id'] ?? null,
                ]);
                if (in_array(strtolower(trim(html_entity_decode((string) $message))), ['', 'server error', 'internal server error'], true)) {
                    $message = 'SITRA devolvió un error interno (HTTP '.$httpStatus.'). No se pudo confirmar el movimiento. Reintenta la misma operación para consultar su resultado.';
                }
            }
            $result = [
                'status' => $result['status'] ?? ($httpStatus >= 500 ? 'uncertain' : 'rejected'),
                'message' => $message !== '' ? $message : 'No se pudo confirmar la operación.',
                'operation_id' => $result['operation_id'] ?? null,
            ];
        } catch (HttpException $e) {
            $result = ['status' => 'rejected', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);
            $result = ['status' => $payload ? 'uncertain' : 'rejected', 'message' => 'No se pudo confirmar. Reintente la misma acción; se conservará la solicitud original.'];
        }
        if ($requiredAction === 'EMG' && ($result['status'] ?? null) === 'succeeded') {
            try {
                PaqueteUpu::firstOrCreate(['codigo' => $code], $selection[$code]['upu_receipt']);
                $result['upu_saved'] = true;
            } catch (\Throwable $e) {
                report($e);
                $result['status'] = 'uncertain';
                $result['message'] = 'IPS confirmó la recepción, pero falta guardar el paquete en Bolipost. Reintente la misma acción para completar el registro.';
            }
        }
        $selection[$code]['status'] = $result['status'] ?? 'uncertain';
        $selection[$code]['message'] = ($result['status'] ?? null) === 'succeeded' && ($payload['event'] ?? null) === 'EMG' && ! empty($payload['route_mismatch_confirmed'])
            ? 'IPS confirmó la recepción en esta oficina (evento 32). No se agregó un evento de cambio de ruta.'
            : (($result['status'] ?? null) === 'succeeded' && ($payload['event'] ?? null) === 'CUSTOMS_RETURN'
                ? 'Llegada física tras la devolución de Aduana (evento 38) registrada desde la saca abierta. No se registró entrega al destinatario.'
                : ($result['message'] ?? 'Movimiento registrado correctamente en IPS.'));
        $selection[$code]['operation_id'] = $result['operation_id'] ?? null;
        $result['message'] = $selection[$code]['message'];
        if (! empty($result['upu_saved'])) {
            $result['message'] .= ' Paquete registrado también en UPU de Bolipost.';
            $selection[$code]['message'] = $result['message'];
        }
        if ($requiredAction === 'EMG' && ($result['status'] ?? null) === 'succeeded') {
            unset($selection[$code]);
            $result['selection_removed'] = true;
            $result['code'] = $code;
            $result['count'] = count($selection);
        }
        $request->session()->put($key, $selection);
        if (($result['status'] ?? null) === 'succeeded') {
            $trackingEvents?->forget($code);
        }

        return response()->json($result);
    }

    private function upuReceiptData(string $code, array $package, string $destination): array
    {
        return [
            'codigo' => $code,
            'destinatario' => $package['recipient'] ?? null,
            'telefono' => $package['phone'] ?? null,
            'destino' => $destination,
            'zona' => $package['address'] ?? null,
            'peso' => $package['weight_kg'] ?? null,
        ];
    }

    // Legacy links now prepare the unified workbench; no unconfirmed write.
    public function receive(Request $request, string $codigo, SitraIpsClient $ips)
    {
        $request->merge(['codigo' => $codigo]);

        return $this->addSelection($request, $ips);
    }

    public function deliver(Request $request, string $codigo, SitraIpsClient $ips)
    {
        $request->merge(['codigo' => $codigo]);

        return $this->addSelection($request, $ips);
    }

    public function receptacleWorkbench(Request $request, SitraIpsClient $ips, string $paso)
    {
        [$link] = $this->context($request, 'receptacle_'.$paso);
        $assignedRegionalNames = collect(method_exists($request->user(), 'regionalesLista') ? $request->user()->regionalesLista() : [])
            ->map(fn ($regional) => strtoupper(trim((string) $regional)))
            ->filter()
            ->unique()
            ->values();
        $assignedRegionalKeys = $assignedRegionalNames
            ->map(fn ($regional) => $this->normalizeRegionalName($regional))
            ->filter()
            ->unique()
            ->values();
        $officeFcd = strtoupper(trim((string) $link->ips_office_fcd));
        $officeRegional = config('services.sitra_ips.office_regional_by_fcd.'.$officeFcd);
        if (! $officeRegional) {
            $officeName = strtoupper(trim((string) $link->ips_office_name));
            $officeRegional = $assignedRegionalNames->first(fn ($regional) => str_contains($officeName, $regional));
        }
        $officeRegionalKey = $this->normalizeRegionalName($officeRegional);
        $regionalMatchesOffice = $assignedRegionalNames->isEmpty()
            || ($officeRegionalKey !== '' && $assignedRegionalKeys->contains($officeRegionalKey));
        $pendingReceptacleRegionalLabel = $officeRegional
            ?: ($assignedRegionalNames->isNotEmpty() ? $assignedRegionalNames->implode(', ') : 'Sin regional asignada');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'queue' => ['nullable', 'in:expected,opening,registration'],
        ]);
        $codigo = strtoupper(trim($filters['q'] ?? ''));
        $page = (int) ($filters['page'] ?? 1);
        $activeReceptacleQueue = $filters['queue'] ?? ($paso === 'abrir' ? 'opening' : 'expected');
        $pendingKey = 'ips_receptacle_pending:'.$request->user()->id.$link->officeSessionSuffix().':'.$paso.':'.$codigo;
        $pendingPayload = $request->session()->get($pendingKey);
        $detail = null;
        $error = null;
        $receptacleQueueData = [];
        $receptacleQueueMeta = ['page' => $page, 'per_page' => 25, 'total' => 0, 'has_more' => false];
        $receptacleQueueError = null;
        if ($codigo !== '' && $activeReceptacleQueue !== 'registration') {
            try {
                $detail = $ips->receptacle($codigo, (int) $link->ips_office_cd);
                $requiredAction = $paso === 'recibir' ? 'RECEPTACLE_RECEIVE' : 'RECEPTACLE_OPEN';
                if (! in_array($requiredAction, $detail['receptacle']['allowed_actions'] ?? [], true) && ! $pendingPayload) {
                    $current = $detail['receptacle'] ?? [];
                    $eventCode = (int) ($current['event_cd'] ?? 0);
                    $stateCode = (int) ($current['state_cd'] ?? 0);
                    $officeName = trim((string) ($current['office_name'] ?? 'esta oficina'));
                    $canOpenHere = in_array('RECEPTACLE_OPEN', $current['allowed_actions'] ?? [], true);
                    $error = match (true) {
                        $paso === 'recibir' && $eventCode === 131 && $stateCode === 20 => null,
                        $paso === 'recibir' && $canOpenHere => null,
                        $paso === 'abrir' && $eventCode === 131 && $stateCode === 20 => null,
                        $paso === 'recibir' => 'IPS no habilita la recepción de esta saca en esta oficina.',
                        default => 'Para abrirla, IPS debe mostrarla recibida en esta oficina y habilitar la apertura.',
                    };
                }
            } catch (RequestException $e) {
                if ($e->response->status() === 404) {
                    $error = 'El código no existe como saca en IPS. Verifica el marbete y vuelve a escanear.';
                } else {
                    $error = $e->response->json('message') ?? 'No se pudo consultar la saca en IPS.';
                }
            } catch (\Throwable $e) {
                report($e);
                $error = 'No se pudo consultar IPS. No se registró ningún evento.';
            }
        } else {
            if (! $regionalMatchesOffice) {
                $receptacleQueueError = 'La oficina IPS vinculada no corresponde a ninguna de tus regionales asignadas. No se consultó el listado.';
            } else {
                try {
                    $payload = match ($activeReceptacleQueue) {
                        'expected' => $ips->expectedInboundReceptacles((int) $link->ips_office_cd, $page, 25),
                        'opening' => $ips->pendingReceptacleOpenings((int) $link->ips_office_cd, $page, 25),
                        default => $ips->openedReceptaclesForPackageRegistration((int) $link->ips_office_cd, $page, 25),
                    };
                    $receptacleQueueData = (array) ($payload['data'] ?? []);
                    $receptacleQueueMeta = (array) ($payload['meta'] ?? $receptacleQueueMeta);
                } catch (RequestException $e) {
                    $receptacleQueueError = $e->response->json('message') ?? 'No se pudo consultar esta lista de trabajo IPS.';
                } catch (\Throwable $e) {
                    report($e);
                    $receptacleQueueError = 'No se pudo consultar esta lista de trabajo IPS.';
                }
            }
        }
        $title = $paso === 'recibir' ? 'Recibir saca internacional' : 'Registrar apertura de saca';
        $description = $paso === 'recibir'
            ? 'Escanea el marbete de la saca cerrada que llegó físicamente a esta oficina.'
            : 'Busca la saca recibida. Registra la apertura en IPS solo después de abrirla físicamente.';
        $idempotencyKey = data_get($pendingPayload, 'idempotency_key') ?? (string) Str::uuid();
        $expectedReceptacleEventCode = data_get($pendingPayload, 'expected_event_cd');
        $isPending = (bool) $pendingPayload;
        $incidents = $codigo !== '' && $detail
            ? IpsVerificationNote::query()
                ->where('receptacle_code', $codigo)
                ->where('office_cd', (int) $link->ips_office_cd)
                ->with('reporter')
                ->latest()
                ->get()
            : collect();

        $openedReceptaclePid = (int) data_get($detail, 'receptacle.event_cd', 0) === 131
            && (int) data_get($detail, 'receptacle.state_cd', -1) === 20
                ? (string) data_get($detail, 'receptacle.id')
                : null;
        [$operationGroups, $operationGroup, $activeOperationTab] = $this->operationNavigation(null, null, $paso, $openedReceptaclePid);

        return view('paquetes_ips.receptacle', compact('paso', 'codigo', 'detail', 'error', 'title', 'description', 'link', 'idempotencyKey', 'expectedReceptacleEventCode', 'isPending', 'operationGroups', 'operationGroup', 'activeOperationTab', 'incidents', 'activeReceptacleQueue', 'receptacleQueueData', 'receptacleQueueMeta', 'receptacleQueueError', 'pendingReceptacleRegionalLabel'));
    }

    public function operateReceptacle(Request $request, SitraIpsClient $ips, string $paso)
    {
        [$link] = $this->context($request, 'receptacle_'.$paso);
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'confirmed' => ['required', 'accepted'],
            'physical_receipt_confirmed' => [$paso === 'recibir' ? 'required' : 'nullable', 'accepted'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9_.:-]+$/'],
        ]);
        $code = strtoupper(trim($data['codigo']));
        $pendingKey = 'ips_receptacle_pending:'.$request->user()->id.$link->officeSessionSuffix().':'.$paso.':'.$code;
        $pending = $request->session()->get($pendingKey);
        if ($pending) {
            $payload = $pending;
        } else {
            $detail = $ips->receptacle($code, (int) $link->ips_office_cd);
            $current = $detail['receptacle'] ?? [];
            $action = $paso === 'recibir' ? 'RECEPTACLE_RECEIVE' : 'RECEPTACLE_OPEN';
            abort_unless(in_array($action, $current['allowed_actions'] ?? [], true), 409, 'El estado actual de la saca ya no habilita este paso. Actualiza la consulta.');
            $effectiveAt = Carbon::now('America/La_Paz');
            $payload = [
                'event' => $action,
                'codigo' => $code,
                'occurred_at' => $effectiveAt->format('Y-m-d\\TH:i:sP'),
                'office_cd' => (int) $link->ips_office_cd,
                'actor_user_pid' => (int) $link->ips_user_pid,
                'external_actor_id' => 'bolipost:'.$request->user()->id,
                'expected_event_cd' => (int) $current['event_cd'],
                'expected_event_at' => $current['event_at'],
                'idempotency_key' => $data['idempotency_key'],
            ];
            if ($paso === 'recibir') {
                $payload['physical_receipt_confirmed'] = true;
            }
            $request->session()->put($pendingKey, $payload);
            $request->session()->save();
        }

        try {
            $result = $ips->receptacleEvent($code, $payload);
            if (($result['status'] ?? null) === 'succeeded') {
                $request->session()->forget($pendingKey);
                if ($paso === 'recibir') {
                    return redirect()->route('internacional.sacas', IpsOfficeContext::parameters($request, ['paso' => 'abrir', 'q' => $code]))
                        ->with('success', 'Saca recibida. Cuando la abras físicamente, confirma la apertura aquí.');
                }

                return redirect()->route('internacional.sacas', IpsOfficeContext::parameters($request, ['paso' => 'abrir', 'q' => $code]))
                    ->with('success', 'Saca abierta. Desde aquí elige el registro que corresponde a los paquetes encontrados.');
            }
            return redirect()->route('internacional.sacas', IpsOfficeContext::parameters($request, ['paso' => $paso, 'q' => $code]))
                ->withErrors(['ips' => $result['message'] ?? 'Consulte el estado antes de volver a operar.']);
        } catch (RequestException $e) {
            $response = $e->response->json() ?? [];
            $status = $response['status'] ?? ($e->response->status() >= 500 ? 'uncertain' : 'rejected');
            if (in_array($status, ['rejected'], true)) {
                $request->session()->forget($pendingKey);
            }
            return redirect()->route('internacional.sacas', IpsOfficeContext::parameters($request, ['paso' => $paso, 'q' => $code]))
                ->withErrors(['ips' => $response['message'] ?? 'No se pudo confirmar. Si el resultado es incierto, reintenta el mismo código para consultar la misma operación.']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('internacional.sacas', IpsOfficeContext::parameters($request, ['paso' => $paso, 'q' => $code]))
                ->withErrors(['ips' => 'No se pudo confirmar. Reintenta el mismo código; se conservará la clave de esta operación.']);
        }
    }
}
