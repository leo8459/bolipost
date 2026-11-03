<?php

namespace App\Http\Controllers;

use App\Models\RegistroApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistroApiController extends Controller
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    public function index(Request $request): View
    {
        $query = RegistroApi::query()->latest();
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('url', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $method = strtoupper(trim((string) $request->query('method', '')));
        if (in_array($method, self::METHODS, true)) {
            $query->where('method', $method);
        }

        return view('registros-apis.index', [
            'registros' => $query->paginate(20)->withQueryString(),
            'methods' => self::METHODS,
        ]);
    }

    public function create(): View
    {
        return view('registros-apis.create', [
            'registroApi' => new RegistroApi(['method' => 'GET']),
            'methods' => self::METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request, creating: true);
        RegistroApi::create($data);

        return redirect()->route('registros-apis.index')->with('success', 'API registrada correctamente.');
    }

    public function edit(RegistroApi $registroApi): View
    {
        return view('registros-apis.edit', [
            'registroApi' => $registroApi,
            'methods' => self::METHODS,
        ]);
    }

    public function update(Request $request, RegistroApi $registroApi): RedirectResponse
    {
        $data = $this->validatedData($request, creating: false);

        if (($data['token'] ?? null) === null) {
            unset($data['token']);
        }

        $registroApi->update($data);

        return redirect()->route('registros-apis.index')->with('success', 'Registro de API actualizado correctamente.');
    }

    public function destroy(RegistroApi $registroApi): RedirectResponse
    {
        $registroApi->delete();

        return redirect()->route('registros-apis.index')->with('success', 'Registro de API eliminado correctamente.');
    }

    private function validatedData(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', self::METHODS)],
            'url' => ['required', 'string', 'max:2048'],
            'token' => [$creating ? 'required' : 'nullable', 'string', 'max:10000'],
            'description' => ['required', 'string', 'max:5000'],
            'json_body' => ['nullable', 'string', 'max:65535', 'json'],
        ], [
            'method.in' => 'Selecciona un método HTTP válido.',
            'json_body.json' => 'El campo JSON debe contener un JSON válido.',
        ]);

        $data['method'] = strtoupper($data['method']);
        $data['url'] = trim($data['url']);
        $token = isset($data['token']) ? trim($data['token']) : null;
        $data['token'] = $token === '' ? null : $token;
        $data['description'] = trim($data['description']);
        $data['json_body'] = isset($data['json_body']) && trim($data['json_body']) !== ''
            ? trim($data['json_body'])
            : null;

        return $data;
    }
}
