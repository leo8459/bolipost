<?php

namespace App\Http\Controllers;

use App\Models\IpsVerificationNote;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingEventsClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaquetesIpsController extends Controller
{
    public const ACTIONS = [
        'EMR' => 'Registrar paquete de saca abierta',
        'EDN' => 'Despachar paquete por destino',
        'EIB' => 'Agrupar en saca nacional',
        'EMG' => 'Recepción en oficina de entrega',
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
            'description' => 'Paquetes listos para entrega y envíos que figuran en Aduana. Si Aduana ya devolvió físicamente un envío, puedes registrar aquí su devolución y entrega al destinatario.',
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
            'description' => 'Paquetes habilitados para registrar su entrega en tu oficina. Si Aduana los devuelve físicamente, la baja registra ambos eventos en orden.',
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
            'description' => 'Registra la llegada de la saca internacional cerrada. Ábrela una sola vez en la oficina habilitada y después registra cada paquete encontrado.',
            'tabs' => [
                ['key' => 'saca-recibir', 'label' => 'Recibir saca cerrada', 'route' => 'internacional.sacas', 'params' => ['paso' => 'recibir']],
                ['key' => 'saca-abrir', 'label' => 'Registrar apertura física', 'route' => 'internacional.sacas', 'params' => ['paso' => 'abrir']],
                ['key' => 'recepcion-paquete', 'label' => 'Registrar paquetes encontrados', 'route' => 'internacional.section', 'params' => ['section' => 'recepcion-cambio']],
            ],
        ],
        'ruta' => [
            'label' => 'Clasificar y despachar',
            'start' => ['route' => 'internacional.section', 'params' => ['section' => 'despacho-nacional']],
            'description' => 'Define el destino de cada paquete. Agrupa los que viajan juntos en una saca nacional nueva; la oficina destino registra su llegada.',
            'tabs' => [
                ['key' => 'ruta-despacho', 'label' => 'Definir destino del paquete', 'route' => 'internacional.section', 'params' => ['section' => 'despacho-nacional']],
                ['key' => 'ruta-saca', 'label' => 'Agrupar en saca nacional nueva', 'route' => 'internacional.section', 'params' => ['section' => 'saca-nacional']],
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
            'next' => 'Define la oficina de destino de cada paquete. Para enviarlos a otra regional, agrúpalos en una saca nacional nueva.',
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
            'next' => 'Agrupa los paquetes compatibles en una saca nacional nueva, distinta de la saca internacional recibida.',
        ],
        'saca-nacional' => [
            'action' => 'EIB',
            'stage' => 'all',
            'event_cd' => 35,
            'title' => 'Agrupar paquetes en saca nacional',
            'description' => 'Asocia cada paquete despachado a una saca nacional abierta compatible con su destino y clase postal. Es una saca física nueva; no se reutiliza el marbete de la saca internacional.',
            'requires' => 'El despacho ya está registrado, la oficina siguiente está definida y hay una saca abierta compatible con la ruta y clase postal.',
            'records' => 'La asociación del paquete a la nueva saca nacional seleccionada.',
            'next' => 'Al llegar, la oficina de destino registra la recepción física de los paquetes.',
        ],
        'recepcion-oficina' => [
            'action' => 'EMG',
            'stage' => 'reception',
            'event_cd' => [13, 35, 72],
            'title' => 'Recibir paquetes en oficina de destino',
            'description' => 'Confirma la llegada física de los paquetes enviados a esta oficina, tanto si viajaron en una saca nacional como si llegaron sueltos.',
            'requires' => 'El envío llegó físicamente a la oficina de destino indicada por su ruta.',
            'records' => 'La recepción del envío en la oficina de destino.',
            'next' => 'El envío queda disponible para retiro o para el proceso de reparto que corresponda.',
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
        'disponible-retiro' => ['distribucion', null],
        'cartero' => ['distribucion', null],
        'salida-reparto' => ['distribucion', null],
        'intento-entrega' => ['distribucion', null],
    ];

    private function context(Request $request, string $workflow = 'standard'): array
    {
        $user = $request->user();
        $link = $user->ipsLink;
        abort_unless($link && $link->active && $link->ips_office_cd !== null, 403, 'Necesita un vínculo IPS activo con oficina asignada.');
        if ($workflow === 'my_delivery') {
            abort_unless(preg_match('/^[A-Za-z0-9]{2}$/', (string) $link->delivery_agent_cd) === 1, 403, 'Tu cuenta todavía no tiene un cartero IPS vinculado. Comunícate con administración.');
        }

        $keyPrefix = match ($workflow) {
            'delivery' => 'ips_delivery_workbench.',
            'standard' => 'ips_workbench.',
            default => 'ips_'.$workflow.'_workbench.',
        };

        return [$link, $keyPrefix.$user->id];
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

    private function operationNavigation(?string $section, ?string $warehouseView, ?string $receptacleStep = null): array
    {
        $group = null;
        $tab = null;

        if ($receptacleStep !== null) {
            $group = 'recepcion';
            $tab = $receptacleStep === 'recibir' ? 'saca-recibir' : 'saca-abrir';
        } elseif ($warehouseView !== null) {
            $group = 'distribucion';
            $tab = 'entrega-bandeja';
        } elseif ($section !== null && isset(self::INTERNATIONAL_OPERATION_TABS[$section])) {
            [$group, $tab] = self::INTERNATIONAL_OPERATION_TABS[$section];
        }

        return [
            self::INTERNATIONAL_OPERATION_GROUPS,
            $group,
            $tab,
            self::WAREHOUSE_TABS,
        ];
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

            return redirect()->route('internacional.warehouse', ['warehouseView' => 'entregados'] + $legacyQuery);
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
        $preferredReceptaclePid = $section === 'recepcion-cambio' ? ($filters['receptacle_pid'] ?? null) : null;
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
            'EMG' => 'No hay envíos de la ruta pendientes de recepción física en esta oficina.',
            default => 'No hay paquetes que coincidan con esta búsqueda y oficina.',
        };
        $workflowKey = $workflowAction ? 'international_'.strtolower($workflowAction) : null;
        $isInternationalPage = $internationalPage !== null || $internationalWorkflow !== null;
        $courierOnly = $section === 'almacen' && $warehouseView === 'asignados-a-mi';
        $deliveryOnly = $section === 'almacen' && in_array($warehouseView, ['por-entregar', 'asignados-a-mi'], true);
        if ($courierOnly) {
            $emptyMessage = 'No tienes paquetes asignados pendientes de salida o en reparto.';
        }
        $link = $request->user()->ipsLink;
        $courierConfigurationMissing = $courierOnly && (
            ! $link
            || ! $link->active
            || $link->ips_office_cd === null
            || preg_match('/^[A-Za-z0-9]{2}$/', (string) $link->delivery_agent_cd) !== 1
        );
        $courierConfigurationMessage = ! $link || ! $link->active || $link->ips_office_cd === null
            ? 'Tu cuenta necesita un vínculo IPS activo con una oficina antes de consultar el reparto.'
            : 'Tu cuenta IPS está vinculada, pero falta asociarla a un cartero activo de su regional.';
        $readOnly = ($isInternationalPage && ! $deliveryOnly && ! $workflowAction) || $courierConfigurationMissing;
        $listingUrl = $section === 'almacen'
            ? route('internacional.warehouse', ['warehouseView' => $warehouseView])
            : ($isInternationalPage
                ? route('internacional.section', array_filter(['section' => $section, 'receptacle_pid' => $preferredReceptaclePid]))
                : route('ips.index'));
        $listingClearUrl = $listingUrl;
        $selectionKey = $workflowKey
            ? 'ips_'.$workflowKey.'_workbench.'
            : ($courierOnly ? 'ips_my_delivery_workbench.' : ($deliveryOnly ? 'ips_delivery_workbench.' : 'ips_workbench.'));
        $selection = $request->session()->get($selectionKey.$request->user()->id, []);
        try {
            [$link] = $this->context($request, $workflowKey ?? ($courierOnly ? 'my_delivery' : ($deliveryOnly ? 'delivery' : 'standard')));
            if ($workflowAction && $search !== '') {
                try {
                    $package = $ips->package($search, (int) $link->ips_office_cd)['package'];
                    if (in_array($workflowAction, $package['allowed_actions'] ?? [], true)) {
                        $items = collect([$package]);
                        $total = 1;
                    }
                } catch (RequestException $e) {
                    if ($e->response->status() !== 404) {
                        throw $e;
                    }
                    if ($workflowAction === 'EMR') {
                        $missingPackageCode = $search;
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
                $payload = $ips->packages($packageFilters);
                $items = collect($payload['data'] ?? []);
                $total = (int) ($payload['meta']['total'] ?? 0);
            }
        } catch (HttpException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $error = 'No se pudo consultar IPS. Intente actualizar; no se modificó ningún paquete.';
        }
        $deliveryCatalogActions = ['HANDOVER_AGENT', 'EMH', 'CUSTOMS_PRESENT'];
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
        if ($workflowAction === 'EMR' && ! $error) {
            if ($missingPackageCode) {
                $pendingKey = 'ips_inbound_create_pending:'.$request->user()->id.':'.$missingPackageCode;
                $inboundPending = $request->session()->get($pendingKey);
                $inboundCreateKey = data_get($inboundPending, 'idempotency_key')
                    ?? old('idempotency_key')
                    ?? $request->session()->get('inbound_create_key')
                    ?? (string) Str::uuid();
            }
            try {
                $catalog = $ips->catalog((int) $link->ips_office_cd) + $catalog;
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
                : ($deliveryOnly ? self::DELIVERY_ACTION_LABELS : array_intersect_key(self::ACTIONS, array_flip(['EMG', 'EDH', 'EDG', 'EMH', 'EMI']))));
        $deliveryActionLabels = $courierOnly
            ? array_intersect_key(self::DELIVERY_ACTION_LABELS, array_flip(['EDG', 'EMH', 'EMI']))
            : self::DELIVERY_ACTION_LABELS;
        $selectionWorkflow = $courierOnly ? 'my_delivery' : 'delivery';
        $selectionAddUrl = $workflowAction
            ? route('internacional.workflow.selection.add', ['section' => $section])
            : ($courierOnly
                ? route('internacional.my-delivery.selection.add')
                : ($deliveryOnly ? route('internacional.selection.add') : route('ips.selection.add')));
        $selectionRemoveRouteName = $workflowAction
            ? 'internacional.workflow.selection.remove'
            : ($courierOnly ? 'internacional.my-delivery.selection.remove' : ($deliveryOnly ? 'internacional.selection.remove' : 'ips.selection.remove'));
        $operateUrl = $workflowAction
            ? route('internacional.workflow.operate', ['section' => $section])
            : ($courierOnly
                ? route('internacional.my-delivery.operate')
                : ($deliveryOnly ? route('internacional.operate') : route('ips.operate')));
        $operationLabel = $workflowAction ? self::ACTIONS[$workflowAction] : null;
        [$operationGroups, $operationGroup, $activeOperationTab] = $this->operationNavigation($section, $warehouseView);
        $workflowHint = match ($workflowAction) {
            'EMR' => 'Escanea los marbetes encontrados; IPS registra un movimiento individual por paquete.',
            'EDN' => 'Agrupa solo paquetes compatibles con la misma oficina de destino.',
            'EIB' => 'Añade juntos los paquetes del mismo destino y clase a una saca nacional nueva.',
            'EMG' => 'Selecciona los paquetes que llegaron físicamente a destino y registra su recepción en IPS.',
            default => null,
        };
        $viewData = compact(
            'packages', 'search', 'stage', 'selection', 'error', 'link', 'actions',
            'section', 'pageTitle', 'pageDescription',
            'warehouseView',
            'operationGroups', 'operationGroup', 'activeOperationTab',
            'isInternationalPage', 'deliveryOnly', 'courierOnly', 'selectionWorkflow', 'deliveryActionLabels', 'readOnly', 'listingUrl', 'listingClearUrl', 'workflowAction', 'workflowKey', 'operationLabel', 'workflowHint', 'preferredReceptaclePid',
            'courierConfigurationMissing', 'courierConfigurationMessage',
            'internationalWorkflow',
            'emptyMessage',
            'missingPackageCode', 'inboundPending', 'inboundCreateKey',
            'selectionAddUrl', 'selectionRemoveRouteName', 'operateUrl', 'catalog'
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

        return $this->addSelection($request, $ips, 'international_'.strtolower($definition['action']), $definition['action'], $section);
    }

    public function addSelection(Request $request, SitraIpsClient $ips, string $workflow = 'standard', ?string $requiredAction = null, ?string $section = null)
    {
        [$link, $key] = $this->context($request, $workflow);
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'workflow' => ['nullable', 'in:delivery,my_delivery'],
        ]);
        try {
            $package = $ips->package(strtoupper($data['codigo']), (int) $link->ips_office_cd)['package'];
            $office = (int) $link->ips_office_cd;
            $deliveryWorkflow = in_array($workflow, ['delivery', 'my_delivery'], true);
            if ($workflow === 'my_delivery') {
                $this->assertAssignedCourierPackage($package, (string) $link->delivery_agent_cd);
            }
            if ($requiredAction !== null) {
                abort_unless(in_array($requiredAction, $package['allowed_actions'] ?? [], true), 409, 'IPS no habilita este movimiento para el paquete y la oficina vinculada.');
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
                    : array_keys(self::DELIVERY_ACTION_LABELS);
                abort_unless(array_intersect($allowedDeliveryActions, $package['allowed_actions'] ?? []) !== [], 409, 'IPS no habilita movimientos de distribución para este paquete y oficina. Actualiza el listado.');
            }
            $selection = $request->session()->get($key, []);
            $code = $package['codigo'];
            if (isset($selection[$code])) {
                if ($request->expectsJson()) {
                    $removeRoute = $requiredAction !== null && ! $deliveryWorkflow
                        ? route('internacional.workflow.selection.remove', ['section' => $section, 'codigo' => $code])
                        : route($workflow === 'my_delivery' ? 'internacional.my-delivery.selection.remove' : ($workflow === 'delivery' ? 'internacional.selection.remove' : 'ips.selection.remove'), $code);

                    return response()->json([
                        'code' => $code,
                        'package' => $selection[$code]['package'] ?? $package,
                        'count' => count($selection),
                        'remove_url' => $removeRoute,
                    ]);
                }
                return back()->with('success', 'El paquete ya está en la bandeja.');
            }
            abort_if(count($selection) >= 50, 422, 'La bandeja admite hasta 50 paquetes.');
            $selection[$code] = ['package' => $package, 'status' => 'ready', 'message' => null, 'payload' => null];
            $request->session()->put($key, $selection);

            if ($request->expectsJson()) {
                $removeRoute = $requiredAction !== null && ! $deliveryWorkflow
                    ? route('internacional.workflow.selection.remove', ['section' => $section, 'codigo' => $code])
                    : route($workflow === 'my_delivery' ? 'internacional.my-delivery.selection.remove' : ($workflow === 'delivery' ? 'internacional.selection.remove' : 'ips.selection.remove'), $code);

                return response()->json(['code' => $code, 'package' => $package, 'count' => count($selection), 'remove_url' => $removeRoute]);
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

        return $this->removeSelection($request, $codigo, 'international_'.strtolower($definition['action']));
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

    public function createInboundPackage(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents)
    {
        [$link] = $this->context($request, 'international_emr');
        $code = strtoupper(trim((string) $request->input('codigo')));
        $pendingKey = 'ips_inbound_create_pending:'.$request->user()->id.':'.$code;
        $pending = $code !== '' ? $request->session()->get($pendingKey) : null;
        $rules = [
            'codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9_.:-]+$/'],
        ];
        if (! $pending) {
            $rules += [
                'weight_kg' => ['required', 'numeric', 'gt:0', 'max:999.999', 'decimal:0,3'],
                'mail_class' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z0-9]$/'],
                'condition_cd' => ['required', 'integer', 'min:1'],
                'origin_country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'receptacle_pid' => ['required', 'uuid'],
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
                'recipient.country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'recipient.postcode' => ['nullable', 'string', 'max:20'],
                'recipient.phone' => ['nullable', 'string', 'max:64'],
                'recipient.email' => ['nullable', 'email', 'max:64'],
            ];
        }
        $data = $request->validate($rules);
        $code = strtoupper(trim($data['codigo']));
        $pendingKey = 'ips_inbound_create_pending:'.$request->user()->id.':'.$code;

        if ($pending) {
            abort_unless(hash_equals((string) $pending['idempotency_key'], (string) $data['idempotency_key']), 409, 'La solicitud original está pendiente. Consulte con la misma clave antes de iniciar otra.');
            $payload = $pending;
        } else {
            $effectiveAt = Carbon::now('America/La_Paz');
            $payload = [
                'event' => 'EMD',
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
                'condition_cd' => (int) $data['condition_cd'],
                'receptacle_pid' => $data['receptacle_pid'],
                'physical_receipt_confirmed' => true,
                'sender' => $data['sender'],
                'recipient' => $data['recipient'],
            ];
            $request->session()->put($pendingKey, $payload);
            $request->session()->save();
        }

        try {
            $result = $ips->createPackage($payload);
            if (($result['status'] ?? null) === 'succeeded') {
                $request->session()->forget($pendingKey);
                $trackingEvents->forget($code);

                return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                    ->with('success', 'Paquete creado y recepción en oficina de cambio confirmada por IPS.');
            }
            if (($result['status'] ?? null) === 'rejected') {
                $request->session()->forget($pendingKey);
                $formInput = $this->inboundCreateFormInput($payload);
                $request->session()->flash('inbound_create_key', $formInput['idempotency_key']);

                return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                    ->withErrors(['ips' => $result['message'] ?? 'IPS rechazó el registro. Revise los datos y vuelva a intentarlo.'])
                    ->withInput($formInput);
            }

            return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                ->withErrors(['ips' => $result['message'] ?? 'La operación sigue pendiente. Reintente con la misma clave para consultar el resultado.']);
        } catch (RequestException $e) {
            $result = $e->response->json() ?? [];
            $status = $result['status'] ?? ($e->response->status() >= 500 ? 'uncertain' : 'rejected');
            if ($status === 'rejected') {
                $request->session()->forget($pendingKey);
                $formInput = $this->inboundCreateFormInput($payload);
                $request->session()->flash('inbound_create_key', $formInput['idempotency_key']);

                return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                    ->withErrors(['ips' => $result['message'] ?? 'IPS rechazó el registro. Revise los datos y vuelva a intentarlo.'])
                    ->withInput($formInput);
            }

            return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                ->withErrors(['ips' => $result['message'] ?? 'No se pudo confirmar. Si queda pendiente, reintente exactamente la misma operación.']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('internacional.section', ['section' => 'recepcion-cambio', 'q' => $code])
                ->withErrors(['ips' => 'No se pudo confirmar el resultado. Reintente la operación original; no se generará otra clave.']);
        }
    }

    private function inboundCreateFormInput(array $payload): array
    {
        return [
            'codigo' => $payload['codigo'],
            'idempotency_key' => (string) Str::uuid(),
            'weight_kg' => $payload['weight_kg'],
            'mail_class' => $payload['mail_class'],
            'condition_cd' => $payload['condition_cd'],
            'origin_country' => $payload['origin_country'],
            'receptacle_pid' => $payload['receptacle_pid'],
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

        return $this->operate($request, $ips, $trackingEvents, 'international_'.strtolower($definition['action']), $definition['action']);
    }

    public function operate(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, string $workflow = 'standard', ?string $requiredAction = null)
    {
        [$link, $key] = $this->context($request, $workflow);
        $actionRule = $requiredAction ?? match ($workflow) {
            'delivery' => 'EDH,HANDOVER_AGENT,CUSTOMS_PRESENT,EDG,EMH,EMI',
            'my_delivery' => 'EDG,EMH,EMI',
            default => 'EMG,EDH,EDG,EMH,EMI',
        };
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35'],
            'action' => ['required', 'in:'.$actionRule],
            'confirmed' => ['required', 'accepted'],
            'customs_return_confirmed' => ['sometimes', 'accepted'],
            'physical_receipt_confirmed' => ['sometimes', 'accepted'],
            'signatory' => ['nullable', 'string', 'max:64'],
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
        if (in_array($data['action'], ['EMR', 'EMG'], true) && ! $request->boolean('physical_receipt_confirmed')) {
            return response()->json([
                'status' => 'needs_physical_confirmation',
                'message' => 'Confirme que el envío llegó físicamente a esta oficina antes de registrar la recepción.',
            ], 422);
        }
        $effectiveAt = Carbon::now('America/La_Paz');
        $selection = $request->session()->get($key, []);
        $code = strtoupper($data['codigo']);
        abort_unless(isset($selection[$code]), 422, 'Agregue el paquete a la bandeja primero.');
        $entry = $selection[$code];
        if ($entry['status'] === 'succeeded') {
            return response()->json(['status' => 'succeeded', 'message' => 'Operación ya confirmada.', 'operation_id' => $entry['operation_id'] ?? null]);
        }
        $payload = $entry['payload'] ?? null;
        if ($payload && $payload['event'] !== $data['action']) {
            return response()->json(['status' => 'rejected', 'message' => 'Esta operación ya tiene una acción registrada. Mantenga la misma acción para consultar su resultado.'], 409);
        }
        try {
            if (! $payload) {
                // Recheck before writing; optimistic concurrency still runs inside IPS's SQL transaction.
                $current = $ips->package($code, (int) $link->ips_office_cd)['package'];
                if ($workflow === 'my_delivery') {
                    $this->assertAssignedCourierPackage($current, (string) $link->delivery_agent_cd);
                }
                abort_unless(in_array($data['action'], $current['allowed_actions'] ?? [], true), 409, 'La etapa u oficina ya no permite esta acción. Actualice el paquete.');
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
                if (in_array($data['action'], ['EMR', 'EMG'], true)) {
                    $payload['physical_receipt_confirmed'] = $request->boolean('physical_receipt_confirmed');
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
                if ($data['action'] === 'EMI'
                    && in_array((int) ($current['operational_event_cd'] ?? $current['event_cd']), [31, 34], true)
                    && (int) ($current['state_cd'] ?? -1) === 1) {
                    abort_unless($request->boolean('customs_return_confirmed'), 422, 'Confirme que Aduana devolvió físicamente el envío antes de registrar la entrega.');
                    $payload['customs_return_confirmed'] = true;
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
                $selection[$code]['status'] = 'processing';
                $request->session()->put($key, $selection);
                $request->session()->save();
            }
            $result = $payload['event'] === 'EMI' ? $ips->deliver($code, $payload) : $ips->event($code, $payload);
        } catch (RequestException $e) {
            $result = $e->response->json() ?? [];
            $result = [
                'status' => $result['status'] ?? ($e->response->status() >= 500 ? 'uncertain' : 'rejected'),
                'message' => $result['message'] ?? 'No se pudo confirmar la operación.',
                'operation_id' => $result['operation_id'] ?? null,
            ];
        } catch (HttpException $e) {
            $result = ['status' => 'rejected', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);
            $result = ['status' => $payload ? 'uncertain' : 'rejected', 'message' => 'No se pudo confirmar. Reintente la misma acción; se conservará la solicitud original.'];
        }
        $selection[$code]['status'] = $result['status'] ?? 'uncertain';
        $selection[$code]['message'] = $result['message'] ?? 'Movimiento registrado correctamente en IPS.';
        $selection[$code]['operation_id'] = $result['operation_id'] ?? null;
        $request->session()->put($key, $selection);
        if (($result['status'] ?? null) === 'succeeded') {
            $trackingEvents?->forget($code);
        }

        return response()->json($result + ['message' => $selection[$code]['message']]);
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
        $pendingKey = 'ips_receptacle_pending:'.$request->user()->id.':'.$paso.':'.$codigo;
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

        [$operationGroups, $operationGroup, $activeOperationTab] = $this->operationNavigation(null, null, $paso);

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
        $pendingKey = 'ips_receptacle_pending:'.$request->user()->id.':'.$paso.':'.$code;
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
                    return redirect()->route('internacional.sacas', ['paso' => 'abrir', 'q' => $code])
                        ->with('success', 'Saca recibida. Cuando la abras físicamente, confirma la apertura aquí.');
                }

                return redirect()->route('internacional.section', array_filter([
                    'section' => 'recepcion-cambio',
                    'receptacle_pid' => $result['receptacle_pid'] ?? null,
                ]))->with('success', 'Saca abierta. Ya puedes escanear y registrar sus paquetes en lote.');
            }
            return redirect()->route('internacional.sacas', ['paso' => $paso, 'q' => $code])
                ->withErrors(['ips' => $result['message'] ?? 'Consulte el estado antes de volver a operar.']);
        } catch (RequestException $e) {
            $response = $e->response->json() ?? [];
            $status = $response['status'] ?? ($e->response->status() >= 500 ? 'uncertain' : 'rejected');
            if (in_array($status, ['rejected'], true)) {
                $request->session()->forget($pendingKey);
            }
            return redirect()->route('internacional.sacas', ['paso' => $paso, 'q' => $code])
                ->withErrors(['ips' => $response['message'] ?? 'No se pudo confirmar. Si el resultado es incierto, reintenta el mismo código para consultar la misma operación.']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('internacional.sacas', ['paso' => $paso, 'q' => $code])
                ->withErrors(['ips' => 'No se pudo confirmar. Reintenta el mismo código; se conservará la clave de esta operación.']);
        }
    }
}
