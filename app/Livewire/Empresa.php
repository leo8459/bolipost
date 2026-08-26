<?php

namespace App\Livewire;

use App\Models\Empresa as EmpresaModel;
use App\Services\EmpresaContractUserSyncService;
use App\Services\EmpresaHistoryService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class Empresa extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const DOCUMENTO_PDF_MAX_KB = 51200;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre = '';
    public $sigla = '';
    public $codigo_cliente = '';
    public $nit = '';
    public $clasificacion = '';
    public $documentacion_legal = '';
    public $inicio_contrato = '';
    public $fin_contrato = '';
    public $cobertura = '';
    public $presupuesto = '';
    public $documento_pdf_file = null;
    public $documento_pdf_path = '';
    public $openCreateModalOnLoad = false;
    public $archivingToHistory = false;
    public $originalInicioContrato = '';
    public $originalFinContrato = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->openCreateModalOnLoad = request()->boolean('create');
    }

    public function rendered(): void
    {
        if (! $this->openCreateModalOnLoad) {
            return;
        }

        $this->openCreateModalOnLoad = false;
        if (! $this->canCreateEmpresa()) {
            return;
        }

        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEmpresaModal');
    }

    public function searchEmpresas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->archivingToHistory = false;
        $this->dispatch('openEmpresaModal');
    }

    public function openEditModal($id)
    {
        $empresa = EmpresaModel::findOrFail($id);

        $this->loadEmpresaForm($empresa);
        $this->archivingToHistory = false;

        $this->dispatch('openEmpresaModal');
    }

    public function openHistoryModal($id): void
    {
        $this->authorizeHistoryAction();
        $empresa = EmpresaModel::findOrFail($id);

        $this->loadEmpresaForm($empresa);
        $this->archivingToHistory = true;
        $this->originalInicioContrato = $this->inicio_contrato;
        $this->originalFinContrato = $this->fin_contrato;

        $this->dispatch('openEmpresaModal');
    }

    protected function loadEmpresaForm(EmpresaModel $empresa): void
    {
        $this->resetValidation();

        $this->editingId = $empresa->id;
        $this->nombre = $empresa->nombre;
        $this->sigla = $empresa->sigla;
        $this->codigo_cliente = $empresa->codigo_cliente;
        $this->nit = (string) ($empresa->nit ?? '');
        $this->clasificacion = (string) ($empresa->clasificacion ?? '');
        $this->documentacion_legal = (string) ($empresa->documentacion_legal ?? '');
        $this->inicio_contrato = !empty($empresa->inicio_contrato) ? (string) $empresa->inicio_contrato : '';
        $this->fin_contrato = !empty($empresa->fin_contrato) ? (string) $empresa->fin_contrato : '';
        $this->cobertura = (string) ($empresa->cobertura ?? '');
        $this->presupuesto = !is_null($empresa->presupuesto) ? (string) $empresa->presupuesto : '';
        $this->documento_pdf_file = null;
        $this->documento_pdf_path = (string) ($empresa->documento_pdf_path ?? '');
    }

    public function save(EmpresaHistoryService $historyService)
    {
        $this->validate($this->rules());

        if ($this->archivingToHistory) {
            $this->authorizeHistoryAction();
            if ($this->inicio_contrato === $this->originalInicioContrato) {
                $this->addError('inicio_contrato', 'Debes registrar una nueva fecha de inicio para enviar el contrato vigente al historial.');
            }
            if ($this->fin_contrato === $this->originalFinContrato) {
                $this->addError('fin_contrato', 'Debes registrar una nueva fecha de finalizacion para enviar el contrato vigente al historial.');
            }
            if ($this->getErrorBag()->has('inicio_contrato') || $this->getErrorBag()->has('fin_contrato')) {
                return;
            }
        }

        $payload = $this->payload();

        if ($this->editingId) {
            $empresa = EmpresaModel::findOrFail($this->editingId);

            if ($this->archivingToHistory) {
                try {
                    $historyService->archiveAndRenew(
                        $empresa,
                        $payload,
                        $this->documento_pdf_file,
                        auth()->id()
                    );
                } catch (Throwable $exception) {
                    report($exception);
                    $this->addError('documento_pdf_file', 'No se pudo guardar el historial. No se modifico la empresa; intenta nuevamente.');

                    return;
                }

                session()->flash('success', 'El contrato anterior se guardo en el historial y la empresa fue actualizada.');
            } elseif ($this->documento_pdf_file) {
                if (!empty($empresa->documento_pdf_path)) {
                    Storage::disk('public')->delete($empresa->documento_pdf_path);
                }

                $payload['documento_pdf_path'] = (string) $this->documento_pdf_file->store('empresa-documentos', 'public');
            }

            if (! $this->archivingToHistory) {
                $empresa->update($payload);
                app(EmpresaContractUserSyncService::class)->syncCompanyById((int) $empresa->id);
                session()->flash('success', 'Empresa actualizada correctamente.');
            }
        } else {
            if ($this->documento_pdf_file) {
                $payload['documento_pdf_path'] = (string) $this->documento_pdf_file->store('empresa-documentos', 'public');
            }

            $empresa = EmpresaModel::create($payload);
            app(EmpresaContractUserSyncService::class)->syncCompanyById((int) $empresa->id);
            session()->flash('success', 'Empresa creada correctamente.');
        }

        $this->dispatch('closeEmpresaModal');
        $this->resetForm();
    }

    public function delete($id)
    {
        $empresa = EmpresaModel::findOrFail($id);
        if (!empty($empresa->documento_pdf_path)) {
            Storage::disk('public')->delete($empresa->documento_pdf_path);
        }

        $empresa->delete();
        session()->flash('success', 'Empresa eliminada correctamente.');
    }

    public function resetForm()
    {
        $this->reset([
            'nombre',
            'sigla',
            'codigo_cliente',
            'nit',
            'clasificacion',
            'documentacion_legal',
            'inicio_contrato',
            'fin_contrato',
            'cobertura',
            'presupuesto',
            'documento_pdf_file',
            'documento_pdf_path',
            'archivingToHistory',
            'originalInicioContrato',
            'originalFinContrato',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
            'sigla' => 'required|string|max:255',
            'codigo_cliente' => 'required|string|max:255',
            'nit' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'clasificacion' => 'required|in:PUBLICA,PRIVADA',
            'documentacion_legal' => 'required|in:CONTRATO,CONVENIO,ADENDA',
            'inicio_contrato' => 'required|date',
            'fin_contrato' => 'required|date|after_or_equal:inicio_contrato',
            'cobertura' => 'required|string|max:255',
            'presupuesto' => 'nullable|numeric|min:0',
            'documento_pdf_file' => 'nullable|file|mimes:pdf|max:' . self::DOCUMENTO_PDF_MAX_KB,
        ];
    }

    protected function messages(): array
    {
        return [
            'documento_pdf_file.max' => 'El documento PDF no debe superar los 50 MB.',
            'documento_pdf_file.mimes' => 'El documento debe ser un archivo PDF.',
            'nit.regex' => 'El NIT solo debe contener numeros.',
        ];
    }

    protected function payload()
    {
        return [
            'nombre' => $this->upper($this->nombre),
            'sigla' => $this->upper($this->sigla),
            'codigo_cliente' => $this->upper($this->codigo_cliente),
            'nit' => trim((string) $this->nit),
            'clasificacion' => $this->upper($this->clasificacion),
            'documentacion_legal' => $this->upper($this->documentacion_legal),
            'inicio_contrato' => $this->inicio_contrato,
            'fin_contrato' => $this->fin_contrato,
            'cobertura' => $this->upper($this->cobertura),
            'presupuesto' => $this->normalizeDecimal($this->presupuesto),
            'documento_pdf_path' => $this->documento_pdf_path !== '' ? $this->documento_pdf_path : null,
        ];
    }

    protected function upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    protected function normalizeDecimal($value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $empresas = EmpresaModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'ILIKE', "%{$q}%")
                    ->orWhere('sigla', 'ILIKE', "%{$q}%")
                    ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%")
                    ->orWhere('nit', 'ILIKE', "%{$q}%")
                    ->orWhere('clasificacion', 'ILIKE', "%{$q}%")
                    ->orWhere('documentacion_legal', 'ILIKE', "%{$q}%")
                    ->orWhere('cobertura', 'ILIKE', "%{$q}%");
            })
            ->orderBy('codigo_cliente')
            ->paginate(100);

        return view('livewire.empresa', [
            'empresas' => $empresas,
        ]);
    }

    private function canCreateEmpresa(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('feature.empresas.create') || $user->can('empresas.index'));
    }

    private function authorizeHistoryAction(): void
    {
        abort_unless(auth()->user()?->can('feature.empresas.history'), 403, 'No tienes permiso para añadir empresas al historial.');
    }
}
