<?php

namespace App\Http\Controllers;

use App\Models\AlertaEmpresa;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlertaEmpresaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isEmpresaUser = $user?->hasRole('empresa') ?? false;
        $empresaId = $isEmpresaUser ? (int) ($user->empresa_id ?? 0) : null;

        $empresasQuery = Empresa::query()->orderBy('nombre');
        $alertasQuery = AlertaEmpresa::query();

        if ($isEmpresaUser) {
            $empresasQuery->whereKey($empresaId);
            $alertasQuery
                ->whereNotNull('aprobada_at')
                ->whereHas('empresas', fn ($query) => $query->whereKey($empresaId))
                ->with([
                    'empresas' => fn ($query) => $query->whereKey($empresaId)->select('empresa.id', 'nombre', 'sigla'),
                    'lectores' => fn ($query) => $query->where('users.empresa_id', $empresaId)->select('users.id', 'name', 'empresa_id'),
                    'lectores.empresa:id,nombre,sigla',
                    'creador:id,name',
                    'aprobador:id,name',
                ])
                ->withCount(['lectores' => fn ($query) => $query->where('users.empresa_id', $empresaId)]);
        } else {
            $alertasQuery
                ->with([
                    'empresas:id,nombre,sigla',
                    'creador:id,name',
                    'aprobador:id,name',
                    'lectores:id,name,empresa_id',
                    'lectores.empresa:id,nombre,sigla',
                ])
                ->withCount('lectores');
        }

        return view('alertas_empresa.index', [
            'empresas' => $empresasQuery->get(['id', 'nombre', 'sigla', 'codigo_cliente']),
            'alertas' => $alertasQuery
                ->latest('created_at')
                ->paginate(15),
            'isEmpresaUser' => $isEmpresaUser,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('createAlert', [
            'titulo' => ['required', 'string', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:10000'],
            'empresa_ids' => ['required', 'array', 'min:1'],
            'empresa_ids.*' => ['integer', 'distinct', 'exists:empresa,id'],
            'portada' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024)],
            'pdf' => ['nullable', File::types(['pdf'])->max(50 * 1024)],
            'vence_at' => ['nullable', 'date', 'after:now'],
        ], [
            'empresa_ids.required' => 'Selecciona al menos una empresa destinataria.',
            'portada.required' => 'La imagen de portada es obligatoria.',
            'portada.image' => 'La portada debe ser una imagen valida.',
            'pdf.mimes' => 'El documento adjunto debe ser PDF.',
            'vence_at.after' => 'La fecha de vencimiento debe ser posterior a la fecha actual.',
        ]);

        $portadaPath = $request->file('portada')->store('alertas-empresa/portadas', 'public');
        $pdfPath = $request->file('pdf')?->store('alertas-empresa/documentos', 'public');

        try {
            DB::transaction(function () use ($validated, $portadaPath, $pdfPath, $request): void {
                $alerta = AlertaEmpresa::query()->create([
                    'titulo' => trim($validated['titulo']),
                    'mensaje' => filled($validated['mensaje'] ?? null) ? trim($validated['mensaje']) : null,
                    'portada_path' => $portadaPath,
                    'pdf_path' => $pdfPath,
                    'creado_por' => $request->user()->id,
                    'publicada_at' => now(),
                    'vence_at' => $validated['vence_at'] ?? null,
                ]);

                $alerta->empresas()->sync($validated['empresa_ids']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter([$portadaPath, $pdfPath]));
            throw $exception;
        }

        return redirect()->route('alertas-empresa.index')->with('success', 'La noticia fue guardada como pendiente. Revísala y apruébala para publicarla.');
    }

    public function approve(Request $request, AlertaEmpresa $alertaEmpresa): RedirectResponse
    {
        if ($alertaEmpresa->aprobada_at) {
            return redirect()->route('alertas-empresa.index')->with('success', 'La noticia ya estaba publicada.');
        }

        $validated = $request->validateWithBag('approveAlert', [
            'titulo' => ['required', 'string', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:10000'],
        ]);

        $alertaEmpresa->update([
            'titulo' => trim($validated['titulo']),
            'mensaje' => filled($validated['mensaje'] ?? null) ? trim($validated['mensaje']) : null,
            'publicada_at' => now(),
            'aprobada_at' => now(),
            'aprobada_por' => $request->user()->id,
        ]);

        return redirect()->route('alertas-empresa.index')->with('success', 'La noticia fue corregida, aprobada y publicada para las empresas.');
    }

    public function destroy(AlertaEmpresa $alertaEmpresa): RedirectResponse
    {
        $paths = array_filter([$alertaEmpresa->portada_path, $alertaEmpresa->pdf_path]);
        $alertaEmpresa->delete();
        Storage::disk('public')->delete($paths);

        return redirect()->route('alertas-empresa.index')->with('success', 'La alerta fue eliminada.');
    }

    public function portada(Request $request, AlertaEmpresa $alertaEmpresa): StreamedResponse
    {
        $this->authorizeRecipient($request, $alertaEmpresa);

        return Storage::disk('public')->response($alertaEmpresa->portada_path, null, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pdf(Request $request, AlertaEmpresa $alertaEmpresa): StreamedResponse
    {
        $this->authorizeRecipient($request, $alertaEmpresa);
        abort_if(blank($alertaEmpresa->pdf_path) || ! Storage::disk('public')->exists($alertaEmpresa->pdf_path), 404);

        return Storage::disk('public')->response($alertaEmpresa->pdf_path, 'alerta-'.$alertaEmpresa->id.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markAsRead(Request $request, AlertaEmpresa $alertaEmpresa): RedirectResponse
    {
        $this->authorizeRecipient($request, $alertaEmpresa, false);

        DB::table('alerta_empresa_lecturas')->updateOrInsert(
            ['alerta_empresa_id' => $alertaEmpresa->id, 'user_id' => $request->user()->id],
            ['leida_at' => now()]
        );

        return back()->with('success', 'Alerta marcada como vista.');
    }

    private function authorizeRecipient(Request $request, AlertaEmpresa $alertaEmpresa, bool $allowManager = true): void
    {
        $user = $request->user();
        $isManager = false;
        if ($allowManager) {
            $isEmpresaUser = $user->hasRole('empresa');
            $isManager = ! $isEmpresaUser
                && ($user->isSuperAdmin() || $user->can('feature.alertas-empresa.manage'));
        }
        $isRecipient = $user->empresa_id
            && $alertaEmpresa->aprobada_at !== null
            && $alertaEmpresa->empresas()->whereKey($user->empresa_id)->exists();

        abort_unless($isManager || $isRecipient, 403);
    }
}
