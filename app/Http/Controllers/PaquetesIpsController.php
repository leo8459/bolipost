<?php

namespace App\Http\Controllers;

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
        'EMR' => 'Recepción en oficina de cambio',
        'EDN' => 'Despacho nacional',
        'EIB' => 'Incorporar a saca nacional',
        'EMG' => 'Recepción en oficina de entrega',
        'EDH' => 'Disponible para retiro',
        'EDG' => 'Salida a reparto',
        'EMH' => 'Intento de entrega fallido',
        'EMI' => 'Registrar entrega (baja)',
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

    private const INTERNATIONAL_WORKFLOWS = [
        'recepcion-cambio' => [
            'action' => 'EMR',
            'step' => 1,
            'place' => 'Oficina de cambio',
            'stage' => 'reception',
            'title' => 'Recepción en oficina de cambio',
            'description' => 'Escanea el envío cuando llegue físicamente a la oficina de cambio. El sistema comprobará que corresponda recibirlo aquí.',
            'requires' => 'El envío llegó físicamente a la oficina de cambio y SITRA lo habilita para esta oficina.',
            'records' => 'La llegada del envío a la oficina de cambio.',
            'next' => 'Preparar su despacho a la siguiente oficina.',
            'search_only' => true,
        ],
        'despacho-nacional' => [
            'action' => 'EDN',
            'step' => 2,
            'place' => 'Oficina de cambio',
            'stage' => 'all',
            'event_cd' => 30,
            'title' => 'Despacho a una ubicación nacional',
            'description' => 'Registra la salida del envío y selecciona la siguiente oficina habilitada en su ruta.',
            'requires' => 'La recepción en la oficina de cambio ya está registrada para este envío.',
            'records' => 'El despacho del envío y la oficina siguiente de su ruta.',
            'next' => 'El paso siguiente mostrará sacas nacionales abiertas compatibles con ese destino y la clase postal.',
        ],
        'saca-nacional' => [
            'action' => 'EIB',
            'step' => 3,
            'place' => 'Oficina de cambio',
            'stage' => 'all',
            'event_cd' => 35,
            'title' => 'Incorporar a saca nacional',
            'description' => 'Asocia el envío a una saca nacional abierta compatible con la oficina siguiente ya registrada y su clase postal. La saca puede prepararse físicamente antes de la salida; SITRA la vincula después del despacho para comprobar destino y clase.',
            'requires' => 'El despacho ya está registrado, la oficina siguiente está definida y hay una saca abierta compatible con la ruta y clase postal.',
            'records' => 'La asociación del envío a la saca seleccionada.',
            'next' => 'Recibir físicamente la saca en la oficina de destino.',
        ],
        'recepcion-oficina' => [
            'action' => 'EMG',
            'step' => 4,
            'place' => 'Oficina de destino',
            'stage' => 'pending',
            'event_cd' => 13,
            'title' => 'Recepción en oficina de entrega',
            'description' => 'Confirma la llegada física del envío a la oficina de destino que indica su ruta.',
            'requires' => 'El envío llegó físicamente a la oficina de destino indicada por su ruta.',
            'records' => 'La recepción del envío en la oficina de destino.',
            'next' => 'El envío queda disponible para retiro o para el proceso de reparto que corresponda.',
        ],
    ];

    private const INTERNATIONAL_FLOW_STEPS = [
        ['section' => 'recepcion-cambio', 'label' => 'Recibir en oficina de cambio', 'place' => 'Oficina de cambio'],
        ['section' => 'despacho-nacional', 'label' => 'Registrar despacho', 'place' => 'Oficina de cambio'],
        ['section' => 'saca-nacional', 'label' => 'Asociar a saca nacional', 'place' => 'Oficina de cambio'],
        ['section' => 'recepcion-oficina', 'label' => 'Recibir en oficina de destino', 'place' => 'Oficina de destino'],
    ];

    private function context(Request $request, string $workflow = 'standard'): array
    {
        $user = $request->user();
        $link = $user->ipsLink;
        abort_unless($link && $link->active && $link->ips_office_cd, 403, 'Necesita un vínculo IPS activo con oficina asignada.');

        $keyPrefix = match ($workflow) {
            'delivery' => 'ips_delivery_workbench.',
            'standard' => 'ips_workbench.',
            default => 'ips_'.$workflow.'_workbench.',
        };

        return [$link, $keyPrefix.$user->id];
    }

    public function index(Request $request, SitraIpsClient $ips, ?string $section = null, ?string $warehouseView = null)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'stage' => ['nullable', 'in:all,reception,pending,returns,delivered,warehouse'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
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
        $items = collect();
        $total = 0;
        $catalog = ['non_delivery_reasons' => [], 'non_delivery_measures' => []];
        $error = null;
        $workflowAction = $internationalWorkflow['action'] ?? null;
        $emptyMessage = match ($workflowAction) {
            'EMR' => $search !== ''
                ? 'No aparece este código: el envío no existe o SITRA no habilita su recepción en esta oficina.'
                : 'Esta ventana trabaja por escaneo. Escribe o escanea el código exacto del envío recibido en la oficina de cambio; no muestra un listado general.',
            'EDN' => 'No hay envíos recibidos en la oficina de cambio que estén pendientes de despacho.',
            'EIB' => 'No hay envíos despachados pendientes de asociar a una saca en esta oficina.',
            'EMG' => 'No hay envíos de la ruta pendientes de recepción física en esta oficina.',
            default => 'No hay paquetes que coincidan con esta búsqueda y oficina.',
        };
        $workflowKey = $workflowAction ? 'international_'.strtolower($workflowAction) : null;
        $isInternationalPage = $internationalPage !== null || $internationalWorkflow !== null;
        $deliveryOnly = $section === 'almacen' && $warehouseView === 'por-entregar';
        $readOnly = $isInternationalPage && ! $deliveryOnly && ! $workflowAction;
        $listingUrl = $section === 'almacen'
            ? route('internacional.warehouse', ['warehouseView' => $warehouseView])
            : ($isInternationalPage ? route('internacional.section', ['section' => $section]) : route('ips.index'));
        $link = $request->user()->ipsLink;
        $selectionKey = $workflowKey ? 'ips_'.$workflowKey.'_workbench.' : ($deliveryOnly ? 'ips_delivery_workbench.' : 'ips_workbench.');
        $selection = $request->session()->get($selectionKey.$request->user()->id, []);
        try {
            [$link] = $this->context($request, $workflowKey ?? ($deliveryOnly ? 'delivery' : 'standard'));
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
        if (! $readOnly && ! $deliveryOnly && ! $workflowAction && ! $error) {
            try {
                $catalog = Cache::remember('sitra:ips:delivery-catalog:v1', now()->addDay(), fn () => $ips->deliveryCatalog()) + $catalog;
            } catch (\Throwable) {
                // Keep package lookup available if the optional attempt catalog is unavailable.
            }
        }
        $paginationQuery = ['q' => $search];
        if (! $isInternationalPage) {
            $paginationQuery['stage'] = $stage;
        }
        $packages = new LengthAwarePaginator($items, $total, 25, $page, ['path' => $listingUrl, 'query' => $paginationQuery]);
        $pageTitle = $warehousePage['title'] ?? $internationalWorkflow['title'] ?? $internationalPage['title'] ?? 'Paquetes de tu oficina';
        $pageDescription = $warehousePage['description'] ?? $internationalWorkflow['description'] ?? $internationalPage['description'] ?? 'Gestiona los movimientos IPS de los paquetes asociados a tu oficina.';
        $actions = $workflowAction
            ? [$workflowAction => self::ACTIONS[$workflowAction]]
            : ($deliveryOnly ? ['EMI' => self::ACTIONS['EMI']] : array_intersect_key(self::ACTIONS, array_flip(['EMG', 'EDH', 'EDG', 'EMH', 'EMI'])));
        $selectionAddUrl = $workflowAction
            ? route('internacional.workflow.selection.add', ['section' => $section])
            : ($deliveryOnly ? route('internacional.selection.add') : route('ips.selection.add'));
        $selectionRemoveRouteName = $workflowAction ? 'internacional.workflow.selection.remove' : ($deliveryOnly ? 'internacional.selection.remove' : 'ips.selection.remove');
        $operateUrl = $workflowAction
            ? route('internacional.workflow.operate', ['section' => $section])
            : ($deliveryOnly ? route('internacional.operate') : route('ips.operate'));
        $operationLabel = $workflowAction ? self::ACTIONS[$workflowAction] : null;
        $internationalFlowSteps = self::INTERNATIONAL_FLOW_STEPS;
        $activeWorkflowStep = $internationalWorkflow['step'] ?? null;
        $workflowPlace = $internationalWorkflow['place'] ?? null;
        $workflowHint = match ($workflowAction) {
            'EMR' => 'Esta ventana trabaja por escaneo. Busca el código exacto del envío recibido; SITRA comprobará si corresponde recibirlo en esta oficina.',
            'EDN' => 'Aquí aparecen envíos ya recibidos en la oficina de cambio. En cada uno selecciona la oficina siguiente de la ruta.',
            'EIB' => 'Aquí aparecen envíos cuyo despacho ya se registró. Agrega un envío para ver las sacas abiertas compatibles con su destino y clase postal.',
            'EMG' => 'Aquí aparecen envíos que, según su ruta, deben recibirse en esta oficina de destino.',
            default => null,
        };
        $viewData = compact(
            'packages', 'search', 'stage', 'selection', 'error', 'link', 'actions',
            'section', 'pageTitle', 'pageDescription',
            'warehouseView',
            'isInternationalPage', 'deliveryOnly', 'readOnly', 'listingUrl', 'workflowAction', 'workflowKey', 'operationLabel', 'workflowHint',
            'internationalWorkflow', 'internationalFlowSteps', 'activeWorkflowStep', 'workflowPlace',
            'emptyMessage',
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
            'workflow' => ['nullable', 'in:delivery'],
        ]);
        try {
            $package = $ips->package(strtoupper($data['codigo']), (int) $link->ips_office_cd)['package'];
            $office = (int) $link->ips_office_cd;
            if ($requiredAction !== null) {
                abort_unless(in_array($requiredAction, $package['allowed_actions'] ?? [], true), 409, 'IPS no habilita este movimiento para el paquete y la oficina vinculada.');
                if ($requiredAction === 'EDN') {
                    abort_unless(! empty($package['next_offices']), 409, 'IPS no tiene una oficina de ruta habilitada para este envío.');
                }
                if ($requiredAction === 'EIB') {
                    abort_unless(! empty($package['domestic_receptacles']), 409, 'No hay una saca nacional abierta compatible con este envío.');
                }
            } else {
                abort_unless(in_array($office, [(int) ($package['operational_office_cd'] ?? $package['office_cd']), (int) ($package['next_office_cd'] ?? 0)], true), 403, 'El paquete no corresponde a su oficina IPS.');
            }
            if ($workflow === 'delivery') {
                abort_unless(in_array('EMI', $package['allowed_actions'] ?? [], true), 409, 'IPS todavía no habilita la baja de este paquete en su oficina.');
            }
            $selection = $request->session()->get($key, []);
            $code = $package['codigo'];
            if (isset($selection[$code])) {
                return back()->with('success', 'El paquete ya está en la bandeja.');
            }
            abort_if(count($selection) >= 50, 422, 'La bandeja admite hasta 50 paquetes.');
            $selection[$code] = ['package' => $package, 'status' => 'ready', 'message' => null, 'payload' => null];
            $request->session()->put($key, $selection);

            if ($request->expectsJson()) {
                $removeRoute = $requiredAction !== null
                    ? route('internacional.workflow.selection.remove', ['section' => $section, 'codigo' => $code])
                    : route($workflow === 'delivery' ? 'internacional.selection.remove' : 'ips.selection.remove', $code);

                return response()->json(['code' => $code, 'package' => $package, 'count' => count($selection), 'remove_url' => $removeRoute]);
            }

            return back()->with('success', 'Paquete guardado en la bandeja.');
        } catch (RequestException $e) {
            return back()->withErrors(['ips' => $e->response->json('message') ?? 'No se pudo consultar el paquete.']);
        }
    }

    public function removeDeliverySelection(Request $request, string $codigo)
    {
        return $this->removeSelection($request, $codigo, 'delivery');
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

    public function operateDelivery(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents)
    {
        return $this->operate($request, $ips, $trackingEvents, 'delivery');
    }

    public function operateInternational(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, string $section)
    {
        $definition = self::INTERNATIONAL_WORKFLOWS[$section] ?? abort(404);

        return $this->operate($request, $ips, $trackingEvents, 'international_'.strtolower($definition['action']), $definition['action']);
    }

    public function operate(Request $request, SitraIpsClient $ips, SitraTrackingEventsClient $trackingEvents, string $workflow = 'standard', ?string $requiredAction = null)
    {
        [$link, $key] = $this->context($request, $workflow);
        $actionRule = $requiredAction ?? ($workflow === 'delivery' ? 'EMI' : 'EMG,EDH,EDG,EMH,EMI');
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35'],
            'action' => ['required', 'in:'.$actionRule],
            'confirmed' => ['required', 'accepted'],
            'customs_return_confirmed' => ['sometimes', 'accepted'],
            'physical_receipt_confirmed' => ['sometimes', 'accepted'],
            'signatory' => ['nullable', 'string', 'max:64'],
            'occurred_at_local' => ['required', 'date_format:Y-m-d\\TH:i'],
            'time_reason' => ['nullable', 'string', 'max:255'],
            'delivery_mode' => ['required_if:action,EMI', 'nullable', 'in:DOMICILIARIA,NO_DOMICILIARIA'],
            'non_delivery_reason' => ['required_if:action,EMH', 'nullable', 'integer', 'min:1'],
            'non_delivery_measure' => ['required_if:action,EMH', 'nullable', 'string', 'size:1'],
            'next_office_cd' => ['required_if:action,EDN', 'nullable', 'integer', 'min:1', 'max:32767'],
            'domestic_receptacle_pid' => ['required_if:action,EIB', 'nullable', 'uuid'],
        ]);
        if (in_array($data['action'], ['EMR', 'EMG'], true) && ! $request->boolean('physical_receipt_confirmed')) {
            return response()->json([
                'status' => 'needs_physical_confirmation',
                'message' => 'Confirme que el envío llegó físicamente a esta oficina antes de registrar la recepción.',
            ], 422);
        }
        $effectiveAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['occurred_at_local'], 'America/La_Paz');
        if ($effectiveAt->lt(now('America/La_Paz')->subMinutes(2)) && trim((string) ($data['time_reason'] ?? '')) === '') {
            return response()->json([
                'status' => 'needs_reason',
                'message' => 'La hora efectiva tiene más de dos minutos. Indique por qué se registra tarde; no se envió ningún evento a SITRA.',
            ], 422);
        }
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
                // Signatory is the actual recipient, never the employee or an assumed name.
                if ($data['action'] === 'EMI' && ! empty($data['signatory'])) {
                    $payload['signatory'] = $data['signatory'];
                }
                if (! empty($data['time_reason'])) {
                    $payload['time_reason'] = trim($data['time_reason']);
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
}
