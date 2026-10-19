<?php

namespace App\Http\Controllers;

use App\Models\IpsVerificationNote;
use App\Services\SitraIpsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IpsReceptacleIncidentController extends Controller
{
    private const INCIDENTS = [
        'receptacle_damaged' => ['summary_code' => 35, 'level' => 'receptacle'],
        'item_damaged' => ['summary_code' => 35, 'level' => 'item'],
        'item_missing' => ['summary_code' => 40, 'level' => 'item'],
        'item_missing_theft' => ['summary_code' => 42, 'level' => 'item'],
        'item_excess' => ['summary_code' => 41, 'level' => 'item'],
        'item_missent' => ['summary_code' => 43, 'level' => 'item'],
        'bulk_count_difference' => ['summary_code' => 99, 'level' => 'bulk'],
        'other' => ['summary_code' => 99, 'level' => 'other'],
    ];

    public function store(Request $request, string $codigo, SitraIpsClient $ips)
    {
        [$link] = $this->context($request);
        $code = strtoupper(trim($codigo));
        $inputType = (string) $request->input('incident_type', '');
        $definition = self::INCIDENTS[$inputType] ?? ['summary_code' => 99, 'level' => 'other'];
        $needsPackage = in_array($definition['level'], ['item'], true);
        $needsComparison = in_array($inputType, ['item_missing', 'item_missing_theft', 'item_excess', 'item_missent', 'bulk_count_difference'], true);
        $isBulk = $definition['level'] === 'bulk';

        $data = $request->validate([
            'document_type' => ['required', Rule::in(['CP78', 'CN43'])],
            'incident_type' => ['required', Rule::in(array_keys(self::INCIDENTS))],
            'package_code' => [$needsPackage ? 'required' : 'nullable', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'comparison_source' => [$needsComparison ? 'required' : 'nullable', Rule::in(['ips', 'dispatch_document', 'other'])],
            'comparison_reference' => ['nullable', 'string', 'max:120'],
            'basis_confirmed' => [$needsComparison ? 'required' : 'nullable', 'accepted'],
            'declared_count' => [$isBulk ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'observed_count' => [$isBulk ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'outer_condition' => ['nullable', 'string', 'max:2000'],
            'contents_condition' => ['nullable', 'string', 'max:2000'],
            'contents_at_risk' => ['nullable', 'boolean'],
            'evidence' => ['nullable', 'array', 'max:4'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'basis_confirmed.accepted' => 'Confirma que cotejaste el marbete y la documentación antes de declarar la diferencia.',
            'evidence.*.mimes' => 'Adjunta imágenes JPG, PNG, WEBP o un PDF.',
            'evidence.*.max' => 'Cada evidencia puede pesar hasta 5 MB.',
        ]);

        if ($isBulk && $data['document_type'] !== 'CP78') {
            return back()->withInput()->withErrors(['document_type' => 'La diferencia de totales de paquetería se documenta en CP 78, junto con la hoja de despacho CP 87.']);
        }

        if ($needsComparison && in_array($data['comparison_source'], ['dispatch_document', 'other'], true) && blank($data['comparison_reference'] ?? null)) {
            return back()->withInput()->withErrors(['comparison_reference' => 'Indica qué documento o verificación respalda esta diferencia.']);
        }

        if ($isBulk && (int) $data['declared_count'] === (int) $data['observed_count']) {
            return back()->withInput()->withErrors(['observed_count' => 'Las cantidades coinciden; no hay diferencia que documentar.']);
        }

        $detail = $ips->receptacle($code, (int) $link->ips_office_cd);
        $receptacle = $detail['receptacle'] ?? [];
        abort_unless(strtoupper((string) ($receptacle['codigo'] ?? $code)) === $code, 404);
        abort_unless((int) ($receptacle['office_cd'] ?? 0) === (int) $link->ips_office_cd, 403, 'La saca no pertenece a la oficina vinculada.');

        $eventCode = (int) ($receptacle['event_cd'] ?? 0);
        $stateCode = (int) ($receptacle['state_cd'] ?? 0);
        $isReceivedHere = $stateCode === 22 && in_array($eventCode, [130, 133], true);
        $isOpenedHere = $stateCode === 20 && $eventCode === 131;
        abort_unless($isReceivedHere || $isOpenedHere, 409, 'Registra la incidencia después de recibir la saca en esta oficina.');
        if ($definition['level'] !== 'receptacle') {
            abort_unless($isOpenedHere, 409, 'Abre físicamente la saca antes de registrar incidencias de paquetes.');
        }

        $packageCode = strtoupper(trim((string) ($data['package_code'] ?? '')));
        $linkedCodes = collect($detail['items'] ?? [])
            ->map(fn ($item) => strtoupper(trim((string) ($item['MAILITM_FID'] ?? ''))))
            ->filter()
            ->all();
        $truncated = (bool) ($detail['items_truncated'] ?? false);
        $ipsDeclaredCount = isset($receptacle['items_count']) ? (int) $receptacle['items_count'] : null;
        $comparisonSource = $data['comparison_source'] ?? null;

        if ($needsComparison && $comparisonSource === 'ips') {
            if (in_array($inputType, ['item_excess', 'item_missent'], true)) {
                return back()->withInput()->withErrors(['comparison_source' => 'Para declarar un excedente o un mal encaminamiento, coteja el documento de despacho y la ruta; la asociación en IPS no basta.']);
            }

            if (in_array($inputType, ['item_missing', 'item_missing_theft'], true)) {
                if (! in_array($packageCode, $linkedCodes, true)) {
                    return back()->withInput()->withErrors(['package_code' => 'Ese marbete no aparece asociado a esta saca en IPS. La ausencia de la lista no demuestra que falte; coteja el documento de despacho.']);
                }
            }

            if ($isBulk) {
                if ($ipsDeclaredCount === null) {
                    return back()->withInput()->withErrors(['comparison_source' => 'IPS no informa una cantidad declarada. Usa el documento de despacho; cero asociaciones no significa cero declarados.']);
                }
                if ((int) $data['declared_count'] !== $ipsDeclaredCount) {
                    return back()->withInput()->withErrors(['declared_count' => 'La cantidad declarada debe coincidir con la que informa IPS, o debes cotejar un documento de despacho.']);
                }
            }
        }

        if ($inputType === 'item_missing_theft' && empty($request->file('evidence', []))) {
            return back()->withInput()->withErrors(['evidence' => 'Adjunta evidencia del indicio de robo para usar el código 42. Si solo confirmaste que falta, registra faltante sin indicio de robo.']);
        }

        $storedEvidence = [];
        try {
            foreach ($request->file('evidence', []) as $file) {
                $storedEvidence[] = $file->store('ips-verification-notes/'.$code.'/'.now()->format('Y/m'), 'local');
            }

            IpsVerificationNote::create([
                'receptacle_code' => $code,
                'receptacle_pid' => $receptacle['id'] ?? null,
                'dispatch_code' => $receptacle['dispatch_code'] ?? null,
                'office_cd' => (int) $link->ips_office_cd,
                'document_type' => $data['document_type'],
                'incident_type' => $inputType,
                'summary_code' => $definition['summary_code'],
                'package_code' => $needsPackage && $packageCode !== '' ? $packageCode : null,
                'comparison_source' => $needsComparison ? $comparisonSource : null,
                'comparison_reference' => $needsComparison ? (trim((string) ($data['comparison_reference'] ?? '')) ?: null) : null,
                'basis_confirmed' => $needsComparison && (bool) ($data['basis_confirmed'] ?? false),
                'declared_count' => $isBulk ? (int) $data['declared_count'] : null,
                'observed_count' => $isBulk ? (int) $data['observed_count'] : null,
                'description' => trim($data['description']),
                'outer_condition' => trim((string) ($data['outer_condition'] ?? '')) ?: null,
                'contents_condition' => in_array($inputType, ['receptacle_damaged', 'item_damaged', 'item_missing_theft'], true)
                    ? (trim((string) ($data['contents_condition'] ?? '')) ?: null)
                    : null,
                'contents_at_risk' => in_array($inputType, ['receptacle_damaged', 'item_damaged', 'item_missing_theft'], true)
                    && (bool) ($data['contents_at_risk'] ?? false),
                'cn24_required' => $inputType === 'item_missing_theft'
                    || (in_array($inputType, ['receptacle_damaged', 'item_damaged'], true) && (bool) ($data['contents_at_risk'] ?? false)),
                'evidence_paths' => $storedEvidence,
                'status' => 'pending_transmission',
                'reported_by' => $request->user()->id,
                'ips_snapshot' => [
                    'event_cd' => $eventCode,
                    'state_cd' => $stateCode,
                    'event_at' => $receptacle['event_at'] ?? null,
                    'linked_items_count' => $detail['linked_items_count'] ?? count($linkedCodes),
                    'items_truncated' => $truncated,
                    'package_was_associated_in_ips' => $packageCode !== '' && in_array($packageCode, $linkedCodes, true),
                    'declared_items_count' => $receptacle['items_count'] ?? null,
                ],
            ]);
        } catch (\Throwable $exception) {
            foreach ($storedEvidence as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('internacional.sacas', ['paso' => 'abrir', 'q' => $code])
            ->with('success', 'Incidencia registrada localmente. Prepara la CP 78/CN 43 y transmite la nota por el canal postal autorizado.');
    }

    public function markSent(Request $request, string $codigo, IpsVerificationNote $note)
    {
        [$link] = $this->context($request);
        $this->assertNoteBelongsToOffice($note, $codigo, (int) $link->ips_office_cd);
        abort_unless($note->status === 'pending_transmission', 409, 'La nota ya no está pendiente de transmisión.');

        $data = $request->validate([
            'external_vn_number' => ['required', 'string', 'max:40'],
            'transmission_method' => ['required', Rule::in(['PIMS', 'eVN', 'correo', 'papel', 'otro'])],
            'sent_at' => ['required', 'date', 'before_or_equal:today'],
            'confirmed_transmission' => ['required', 'accepted'],
        ]);

        $note->update([
            'external_vn_number' => trim($data['external_vn_number']),
            'transmission_method' => $data['transmission_method'],
            'sent_at' => Carbon::parse($data['sent_at'], 'America/La_Paz'),
            'status' => 'sent',
        ]);

        return redirect()->route('internacional.sacas', ['paso' => 'abrir', 'q' => strtoupper($codigo)])
            ->with('success', 'Transmisión externa registrada con el número de nota indicado.');
    }

    public function resolve(Request $request, string $codigo, IpsVerificationNote $note)
    {
        [$link] = $this->context($request);
        $this->assertNoteBelongsToOffice($note, $codigo, (int) $link->ips_office_cd);
        abort_unless($note->status === 'sent', 409, 'Primero registra el envío externo de la nota.');

        $data = $request->validate([
            'resolution' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $note->update([
            'resolution' => trim($data['resolution']),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);

        return redirect()->route('internacional.sacas', ['paso' => 'abrir', 'q' => strtoupper($codigo)])
            ->with('success', 'Resolución de la nota guardada.');
    }

    public function print(Request $request, string $codigo, IpsVerificationNote $note)
    {
        [$link] = $this->context($request);
        $this->assertNoteBelongsToOffice($note, $codigo, (int) $link->ips_office_cd);
        $note->load('reporter');

        return view('paquetes_ips.verification-note-print', compact('note'));
    }

    public function evidence(Request $request, string $codigo, IpsVerificationNote $note, int $index)
    {
        [$link] = $this->context($request);
        $this->assertNoteBelongsToOffice($note, $codigo, (int) $link->ips_office_cd);
        $path = data_get($note->evidence_paths, $index);
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    private function context(Request $request): array
    {
        $link = $request->user()->ipsLink;
        abort_unless($link && $link->active && $link->ips_office_cd !== null, 403, 'Necesita un vínculo IPS activo con oficina asignada.');

        return [$link];
    }

    private function assertNoteBelongsToOffice(IpsVerificationNote $note, string $codigo, int $office): void
    {
        abort_unless(
            strtoupper($note->receptacle_code) === strtoupper($codigo) && (int) $note->office_cd === $office,
            404
        );
    }
}
