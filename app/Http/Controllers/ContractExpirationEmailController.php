<?php

namespace App\Http\Controllers;

use App\Services\ContractExpirationMailService;
use App\Services\DailyClosingMailService;
use App\Services\DailyDeliveryMailService;
use App\Services\DailyDeliveryReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractExpirationEmailController extends Controller
{
    public function index(ContractExpirationMailService $mailService, DailyClosingMailService $closingService)
    {
        return view('configuracion.contract-expiration-email', [
            'recipients' => $mailService->recipients(),
            'alerts' => $mailService->upcomingAlerts(),
            'automaticSendingEnabled' => $mailService->automaticSendingEnabled(),
            'dailyClosingEnabled' => $closingService->automaticSendingEnabled(),
            'isDailyClosing' => false,
        ]);
    }

    public function dailyClosing(Request $request, DailyClosingMailService $closingService)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'date.date_format' => 'Seleccione una fecha válida.',
            'date.before_or_equal' => 'No se puede preparar un cierre de una fecha futura.',
        ]);
        $selectedDate = $data['date'] ?? now('America/La_Paz')->toDateString();

        return view('configuracion.daily-closing-email', [
            'recipients' => $closingService->recipients(),
            'dailyClosingEnabled' => $closingService->automaticSendingEnabled(),
            'selectedDate' => $selectedDate,
            'report' => $closingService->reportForDate($selectedDate),
        ]);
    }

    public function updateDailyClosing(Request $request, DailyClosingMailService $service)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $service->setAutomaticSendingEnabled((bool) $data['enabled']);

        return back()->with('status', 'Configuración del cierre diario guardada correctamente.');
    }

    public function sendDailyClosing(Request $request, DailyClosingMailService $service)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'date.required' => 'Seleccione el día que desea enviar.',
            'date.date_format' => 'Seleccione una fecha válida.',
            'date.before_or_equal' => 'No se puede enviar un cierre de una fecha futura.',
        ]);
        $recipients = $service->recipients();
        if ($recipients === []) {
            return back()->with('warning', 'Primero debe guardar al menos un correo electrónico.');
        }
        try {
            $report = $service->reportForDate($data['date']);
            $sent = $service->send($recipients, $report);
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar el cierre diario.', ['exception' => $exception]);

            return back()->with('error', 'No se pudo enviar el cierre diario. Revise la configuración del servidor de correo e intente nuevamente.');
        }

        return redirect()
            ->route('contract-expiration-email.daily-closing.index', ['date' => $data['date']])
            ->with('status', "Cierre del {$data['date']} enviado correctamente a {$sent} destinatario(s).");
    }

    public function dailyDeliveries(Request $request, DailyDeliveryMailService $mail, DailyDeliveryReport $reports)
    {
        $date = $this->dailyDeliveryDate($request);
        $recipients = $mail->recipients();
        $nationalRecipients = $recipients[DailyDeliveryMailService::NATIONAL] ?? [];
        unset($recipients[DailyDeliveryMailService::NATIONAL]);
        $regionals = DailyDeliveryReport::regionals();
        $filters = $request->validate(['regional' => ['nullable', Rule::in($regionals)]]);
        $regional = $filters['regional'] ?? null;

        return view('configuracion.daily-deliveries-email', [
            'regionalRecipients' => $recipients,
            'nationalRecipients' => $nationalRecipients,
            'nationalScope' => DailyDeliveryMailService::NATIONAL,
            'regionals' => $regionals,
            'automaticEnabled' => $mail->automaticSendingEnabled(),
            'sendAt' => DailyDeliveryMailService::SEND_AT,
            'selectedDate' => $date,
            'selectedRegional' => $regional,
            'report' => $reports->forDate($date, $regional),
        ]);
    }

    public function updateDailyDeliveries(Request $request, DailyDeliveryMailService $mail)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $mail->setAutomaticSendingEnabled((bool) $data['enabled']);

        return back()->with('status', 'Configuración de Entregas diarias guardada.');
    }

    public function sendDailyDeliveries(Request $request, DailyDeliveryMailService $mail, DailyDeliveryReport $reports)
    {
        $date = $this->dailyDeliveryDate($request, required: true);
        $recipients = $mail->recipients();
        if ($recipients === []) {
            return back()->with('warning', 'Agregue un destinatario regional o nacional para enviar el reporte.');
        }

        ['sent' => $sent, 'failed' => $failed] = $mail->sendReports($recipients, $reports->cutoffForDate($date));
        $redirect = redirect()->route('daily-deliveries-email.index', ['date' => $date]);

        if ($failed > 0) {
            return $redirect->with($sent > 0 ? 'warning' : 'error', "Reportes del {$date}. Correos enviados: {$sent}. Fallidos: {$failed}.");
        }

        return $redirect->with('status', "Reportes del {$date} enviados. Total de correos: {$sent}.");
    }

    public function addDailyDeliveriesRecipient(Request $request, DailyDeliveryMailService $mail)
    {
        $data = $this->dailyDeliveryRecipient($request);
        if (! $mail->addRecipient($data['scope'], $data['recipient'], $data['name'] ?? null)) {
            return redirect()->route('daily-deliveries-email.index')
                ->withErrors(['recipient' => 'Ese correo ya figura en la lista seleccionada.'])
                ->withInput($data);
        }

        return redirect()->route('daily-deliveries-email.index')->with('status', 'Destinatario agregado correctamente.');
    }

    public function removeDailyDeliveriesRecipient(Request $request, DailyDeliveryMailService $mail)
    {
        $data = $this->dailyDeliveryRecipient($request);
        if (! $mail->removeRecipient($data['scope'], $data['recipient'])) {
            return redirect()->route('daily-deliveries-email.index')
                ->withErrors(['recipient' => 'El correo no figura entre los destinatarios agregados manualmente.']);
        }

        return redirect()->route('daily-deliveries-email.index')->with('status', 'Destinatario manual eliminado correctamente.');
    }

    private function dailyDeliveryRecipient(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in([DailyDeliveryMailService::NATIONAL, ...DailyDeliveryReport::regionals()])],
            'recipient' => ['required', 'email:rfc', 'max:254'],
            'name' => ['nullable', 'string', 'max:120'],
        ], [
            'scope.required' => 'Seleccione la regional del destinatario.',
            'scope.in' => 'Seleccione una regional válida o el nivel nacional.',
            'recipient.required' => 'Ingrese el correo electrónico del destinatario.',
            'recipient.email' => 'Ingrese un correo electrónico válido.',
        ]);
    }

    private function dailyDeliveryDate(Request $request, bool $required = false): string
    {
        $today = now(DailyDeliveryReport::TIMEZONE)->toDateString();
        $data = $request->validate([
            'date' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
        ], [
            'date.required' => 'Seleccione el día que desea enviar.',
            'date.date_format' => 'Seleccione una fecha válida.',
            'date.before_or_equal' => 'Seleccione el día de hoy o una fecha anterior.',
        ]);

        return $data['date'] ?? $today;
    }

    public function updateAutomaticSending(Request $request, ContractExpirationMailService $mailService)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $data['enabled'];
        $mailService->setAutomaticSendingEnabled($enabled);

        return back()->with(
            'status',
            $enabled
                ? 'El envio automatico fue activado correctamente.'
                : 'El envio automatico fue desactivado correctamente.'
        );
    }

    public function update(Request $request, ContractExpirationMailService $mailService)
    {
        $recipients = $this->validatedRecipients($request);
        $mailService->saveRecipients($recipients);

        return back()->with('status', 'Destinatarios guardados correctamente.');
    }

    public function addRecipient(Request $request, ContractExpirationMailService $mailService)
    {
        return $this->storeRecipient($request, $mailService->recipients(), $mailService->saveRecipients(...));
    }

    public function addDailyClosingRecipient(Request $request, DailyClosingMailService $service)
    {
        return $this->storeRecipient($request, $service->recipients(), $service->saveRecipients(...));
    }

    private function storeRecipient(Request $request, array $savedRecipients, callable $save)
    {
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:254'],
        ], [
            'recipient.required' => 'Ingrese un correo electronico.',
            'recipient.email' => 'Ingrese un correo electronico valido.',
        ]);

        $recipient = mb_strtolower(trim($data['recipient']));
        $recipients = collect($savedRecipients);

        if ($recipients->contains($recipient)) {
            return back()->with('warning', 'Ese correo electronico ya se encuentra guardado.');
        }

        if ($recipients->count() >= 50) {
            return back()->with('warning', 'Solo se pueden guardar hasta 50 correos electronicos.');
        }

        $save($recipients->push($recipient)->values()->all());

        return back()->with('status', 'Correo electronico agregado correctamente.');
    }

    public function removeRecipient(Request $request, ContractExpirationMailService $mailService)
    {
        return $this->destroyRecipient($request, $mailService->recipients(), $mailService->saveRecipients(...));
    }

    public function removeDailyClosingRecipient(Request $request, DailyClosingMailService $service)
    {
        return $this->destroyRecipient($request, $service->recipients(), $service->saveRecipients(...));
    }

    private function destroyRecipient(Request $request, array $savedRecipients, callable $save)
    {
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:254'],
        ]);

        $recipient = mb_strtolower(trim($data['recipient']));
        $recipients = collect($savedRecipients);

        if (! $recipients->contains($recipient)) {
            return back()->with('warning', 'El correo electronico ya no se encuentra en la lista.');
        }

        $save(
            $recipients->reject(fn (string $email) => $email === $recipient)->values()->all()
        );

        return back()->with('status', 'Correo electronico eliminado correctamente.');
    }

    public function send(Request $request, ContractExpirationMailService $mailService)
    {
        $recipients = $mailService->recipients();
        $alerts = $mailService->upcomingAlerts();

        if ($recipients === []) {
            return back()->with('warning', 'Primero debe guardar al menos un correo electronico.');
        }

        if ($alerts->isEmpty()) {
            return back()->with('warning', 'No existen contratos que venzan en los proximos 90 dias.');
        }

        try {
            $sent = $mailService->send($recipients, $alerts);
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar el aviso de vencimiento de contratos.', [
                'exception' => $exception,
            ]);

            $message = str_contains($exception->getMessage(), '535')
                ? 'Gmail rechazo el usuario o la contraseña SMTP. Genere una contraseña de aplicacion de Google y actualice MAIL_PASSWORD.'
                : 'No se pudo enviar el correo. Revise la configuracion del servidor de correo e intente nuevamente.';

            return back()->with('error', $message);
        }

        return back()->with('status', "Aviso enviado correctamente a {$sent} destinatario(s).");
    }

    private function validatedRecipients(Request $request): array
    {
        $request->validate([
            'recipients' => ['required', 'string', 'max:5000'],
        ]);

        $recipients = collect(preg_split('/[\s,;]+/', (string) $request->input('recipients'), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        if ($recipients->isEmpty() || $recipients->count() > 50) {
            throw ValidationException::withMessages([
                'recipients' => 'Ingrese entre 1 y 50 correos electronicos.',
            ]);
        }

        $invalid = $recipients->first(fn (string $email) => ! filter_var($email, FILTER_VALIDATE_EMAIL));
        if ($invalid !== null) {
            throw ValidationException::withMessages([
                'recipients' => "El correo {$invalid} no es valido.",
            ]);
        }

        return $recipients->all();
    }
}
