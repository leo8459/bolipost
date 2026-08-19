<?php

namespace App\Http\Controllers;

use App\Models\TrackingLocalEventRule;
use App\Services\TrackingLocalEventRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingLocalEventRuleController extends Controller
{
    public function __construct(
        private readonly TrackingLocalEventRuleService $ruleService
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $query = TrackingLocalEventRule::query()
            ->orderBy('source_table')
            ->orderBy('event_id')
            ->orderBy('raw_name');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('raw_name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('source_table', 'like', "%{$search}%");
            });
        }

        if ($source = trim((string) $request->query('source_table', ''))) {
            $query->where('source_table', $source);
        }

        $visibility = $request->query('visible');
        if (in_array((string) $visibility, ['0', '1'], true)) {
            $query->where('is_visible', $visibility === '1');
        }

        return view('tracking-local-event-rules.index', [
            'rules' => $query->paginate(25)->withQueryString(),
            'sourceOptions' => $this->ruleService->commonSourceOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('tracking-local-event-rules.create', [
            'rule' => new TrackingLocalEventRule([
                'source_table' => '*',
                'is_visible' => true,
                'sort_order' => 0,
            ]),
            'sourceOptions' => $this->ruleService->commonSourceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        TrackingLocalEventRule::create($this->validatedData($request));
        $this->ruleService->clearCache();

        return redirect()->route('tracking-local-event-rules.index')
            ->with('success', 'Regla local creada correctamente.');
    }

    public function edit(Request $request, TrackingLocalEventRule $trackingLocalEventRule): View
    {
        $this->ensureAdmin($request);

        return view('tracking-local-event-rules.edit', [
            'rule' => $trackingLocalEventRule,
            'sourceOptions' => $this->ruleService->commonSourceOptions(),
        ]);
    }

    public function update(Request $request, TrackingLocalEventRule $trackingLocalEventRule): RedirectResponse
    {
        $this->ensureAdmin($request);

        $trackingLocalEventRule->update($this->validatedData($request));
        $this->ruleService->clearCache();

        return redirect()->route('tracking-local-event-rules.index')
            ->with('success', 'Regla local actualizada correctamente.');
    }

    public function toggleVisibility(Request $request, TrackingLocalEventRule $trackingLocalEventRule): RedirectResponse
    {
        $this->ensureAdmin($request);

        $trackingLocalEventRule->update([
            'is_visible' => ! $trackingLocalEventRule->is_visible,
        ]);

        $this->ruleService->clearCache();

        return redirect()->to($request->input('redirect_to', route('tracking-local-event-rules.index')))
            ->with('success', 'Visibilidad actualizada correctamente.');
    }

    public function destroy(Request $request, TrackingLocalEventRule $trackingLocalEventRule): RedirectResponse
    {
        $this->ensureAdmin($request);

        $trackingLocalEventRule->delete();
        $this->ruleService->clearCache();

        return redirect()->route('tracking-local-event-rules.index')
            ->with('success', 'Regla local eliminada correctamente.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $stats = $this->ruleService->syncFromLocalCatalog();

        return redirect()->route('tracking-local-event-rules.index')
            ->with('success', "Sincronizacion completada. Total: {$stats['total']}, creados: {$stats['created']}, actualizados: {$stats['updated']}.");
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'source_table' => ['required', 'string', 'max:80'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'raw_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['raw_name'] = trim((string) ($data['raw_name'] ?? ''));
        $data['display_name'] = trim((string) ($data['display_name'] ?? '')) ?: null;
        $data['notes'] = trim((string) ($data['notes'] ?? '')) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if (($data['event_id'] ?? null) === null && $data['raw_name'] === '') {
            abort(422, 'Debes indicar event_id o raw_name.');
        }

        return $data;
    }

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user() || $request->user()->role !== 'admin') {
            abort(403, 'Solo los administradores pueden administrar eventos.');
        }
    }
}
