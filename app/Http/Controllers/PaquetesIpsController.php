<?php

namespace App\Http\Controllers;

use App\Services\SitraIpsClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaquetesIpsController extends Controller
{
    public const ACTIONS = ['EMG' => 'Recepcionar', 'EDH' => 'Disponible para retiro', 'EDG' => 'Salida a reparto', 'EMI' => 'Registrar entrega (baja)'];

    private function context(Request $request): array
    {
        $user = $request->user();
        $link = $user->ipsLink;
        abort_unless($link && $link->active && $link->ips_office_cd, 403, 'Necesita un vínculo IPS activo con oficina asignada.');

        return [$link, 'ips_workbench.'.$user->id];
    }

    public function index(Request $request, SitraIpsClient $ips)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'stage' => ['nullable', 'in:all,reception,pending,returns,delivered'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $stage = $filters['stage'] ?? 'all';
        $search = strtoupper(trim($filters['q'] ?? ''));
        $page = (int) ($filters['page'] ?? 1);
        $items = collect();
        $total = 0;
        $error = null;
        $link = $request->user()->ipsLink;
        $selection = $request->session()->get('ips_workbench.'.$request->user()->id, []);
        try {
            [$link] = $this->context($request);
            $payload = $ips->packages(['status' => $stage, 'office_cd' => $link->ips_office_cd, 'q' => $search, 'page' => $page, 'per_page' => 25]);
            $items = collect($payload['data'] ?? []);
            $total = (int) ($payload['meta']['total'] ?? 0);
        } catch (HttpException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $error = 'No se pudo consultar IPS. Intente actualizar; no se modificó ningún paquete.';
        }
        $packages = new LengthAwarePaginator($items, $total, 25, $page, ['path' => route('ips.index'), 'query' => ['q' => $search, 'stage' => $stage]]);
        $viewData = compact('packages', 'search', 'stage', 'selection', 'error', 'link') + ['actions' => self::ACTIONS];
        if ($request->expectsJson()) {
            if ($error) {
                return response()->json(['message' => $error], 503)->header('Cache-Control', 'no-store');
            }

            return response()->json(['html' => view('paquetes_ips.partials.listing', $viewData)->render()])
                ->header('Cache-Control', 'no-store');
        }

        return view('paquetes_ips.index', $viewData);
    }

    public function addSelection(Request $request, SitraIpsClient $ips)
    {
        [$link, $key] = $this->context($request);
        $data = $request->validate(['codigo' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/']]);
        try {
            $package = $ips->package(strtoupper($data['codigo']), (int) $link->ips_office_cd)['package'];
            $office = (int) $link->ips_office_cd;
            abort_unless(in_array($office, [(int) ($package['operational_office_cd'] ?? $package['office_cd']), (int) ($package['next_office_cd'] ?? 0)], true), 403, 'El paquete no corresponde a su oficina IPS.');
            $selection = $request->session()->get($key, []);
            $code = $package['codigo'];
            if (isset($selection[$code])) {
                return back()->with('success', 'El paquete ya está en la bandeja.');
            }
            abort_if(count($selection) >= 50, 422, 'La bandeja admite hasta 50 paquetes.');
            $selection[$code] = ['package' => $package, 'status' => 'ready', 'message' => null, 'payload' => null];
            $request->session()->put($key, $selection);

            if ($request->expectsJson()) {
                return response()->json(['code' => $code, 'package' => $package, 'count' => count($selection), 'remove_url' => route('ips.selection.remove', $code)]);
            }

            return back()->with('success', 'Paquete guardado en la bandeja.');
        } catch (RequestException $e) {
            return back()->withErrors(['ips' => $e->response->json('message') ?? 'No se pudo consultar el paquete.']);
        }
    }

    public function removeSelection(Request $request, string $codigo)
    {
        [, $key] = $this->context($request);
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

    public function operate(Request $request, SitraIpsClient $ips)
    {
        [$link, $key] = $this->context($request);
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:35'],
            'action' => ['required', 'in:EMG,EDH,EDG,EMI'],
            'confirmed' => ['required', 'accepted'],
            'signatory' => ['nullable', 'string', 'max:64'],
        ]);
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
                    'occurred_at' => now()->toIso8601String(),
                    'office_cd' => (int) $link->ips_office_cd,
                    'actor_user_pid' => (int) $link->ips_user_pid,
                    'external_actor_id' => 'bolipost:'.$request->user()->id,
                    'expected_event_cd' => $current['event_cd'], 'expected_event_at' => $current['event_at'],
                    'idempotency_key' => 'bolipost-'.Str::uuid(),
                ];
                if ($data['action'] === 'EMG') {
                    $payload['physical_receipt_confirmed'] = true;
                }
                // Signatory is the actual recipient, never the employee or an assumed name.
                if ($data['action'] === 'EMI' && ! empty($data['signatory'])) {
                    $payload['signatory'] = $data['signatory'];
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
