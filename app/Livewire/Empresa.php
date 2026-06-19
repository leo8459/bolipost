<?php

namespace App\Livewire;

use App\Models\Empresa as EmpresaModel;
use App\Services\EmpresaContractUserSyncService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Empresa extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $editingId = null;

    public $nombre = '';
    public $sigla = '';
    public $codigo_cliente = '';
    public $clasificacion = '';
    public $documentacion_legal = '';
    public $inicio_contrato = '';
    public $fin_contrato = '';
    public $cobertura = '';
    public $presupuesto = '';
    public $documento_pdf_file = null;
    public $documento_pdf_path = '';

    protected $paginationTheme = 'bootstrap';

    public function searchEmpresas()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEmpresaModal');
    }

    public function openEditModal($id)
    {
        $empresa = EmpresaModel::findOrFail($id);

        $this->editingId = $empresa->id;
        $this->nombre = $empresa->nombre;
        $this->sigla = $empresa->sigla;
        $this->codigo_cliente = $empresa->codigo_cliente;
        $this->clasificacion = (string) ($empresa->clasificacion ?? '');
        $this->documentacion_legal = (string) ($empresa->documentacion_legal ?? '');
        $this->inicio_contrato = !empty($empresa->inicio_contrato) ? (string) $empresa->inicio_contrato : '';
        $this->fin_contrato = !empty($empresa->fin_contrato) ? (string) $empresa->fin_contrato : '';
        $this->cobertura = (string) ($empresa->cobertura ?? '');
        $this->presupuesto = !is_null($empresa->presupuesto) ? (string) $empresa->presupuesto : '';
        $this->documento_pdf_file = null;
        $this->documento_pdf_path = (string) ($empresa->documento_pdf_path ?? '');

        $this->dispatch('openEmpresaModal');
    }

    public function save()
    {
        $this->validate($this->rules());

        $payload = $this->payload();

        if ($this->editingId) {
            $empresa = EmpresaModel::findOrFail($this->editingId);
            if ($this->documento_pdf_file) {
                if (!empty($empresa->documento_pdf_path)) {
                    Storage::disk('public')->delete($empresa->documento_pdf_path);
                }

                $payload['documento_pdf_path'] = (string) $this->documento_pdf_file->store('empresa-documentos', 'public');
            }

            $empresa->update($payload);
            app(EmpresaContractUserSyncService::class)->syncCompanyById((int) $empresa->id);
            session()->flash('success', 'Empresa actualizada correctamente.');
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
            'clasificacion',
            'documentacion_legal',
            'inicio_contrato',
            'fin_contrato',
            'cobertura',
            'presupuesto',
            'documento_pdf_file',
            'documento_pdf_path',
        ]);

        $this->resetValidation();
    }

    protected function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
            'sigla' => 'required|string|max:255',
            'codigo_cliente' => 'required|string|max:255',
            'clasificacion' => 'required|in:PUBLICA,PRIVADA',
            'documentacion_legal' => 'required|in:CONTRATO,CONVENIO,ADENDA',
            'inicio_contrato' => 'required|date',
            'fin_contrato' => 'required|date|after_or_equal:inicio_contrato',
            'cobertura' => 'required|string|max:255',
            'presupuesto' => 'required|numeric|min:0',
            'documento_pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    protected function payload()
    {
        return [
            'nombre' => $this->upper($this->nombre),
            'sigla' => $this->upper($this->sigla),
            'codigo_cliente' => $this->upper($this->codigo_cliente),
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

    protected function normalizeDecimal($value): float
    {
        return (float) str_replace(',', '.', trim((string) $value));
    }

    public function render()
    {
        $q = trim($this->searchQuery);

        $empresas = EmpresaModel::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'ILIKE', "%{$q}%")
                    ->orWhere('sigla', 'ILIKE', "%{$q}%")
                    ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%")
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
}
