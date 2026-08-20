<?php

namespace App\Http\Controllers;

use App\Services\ContractExpirationMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractExpirationEmailController extends Controller
{
    public function index(ContractExpirationMailService $mailService)
    {
        return view('configuracion.contract-expiration-email', [
            'recipients' => $mailService->recipients(),
            'alerts' => $mailService->upcomingAlerts(),
            'automaticSendingEnabled' => $mailService->automaticSendingEnabled(),
        ]);
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
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:254'],
        ], [
            'recipient.required' => 'Ingrese un correo electronico.',
            'recipient.email' => 'Ingrese un correo electronico valido.',
        ]);

        $recipient = mb_strtolower(trim($data['recipient']));
        $recipients = collect($mailService->recipients());

        if ($recipients->contains($recipient)) {
            return back()->with('warning', 'Ese correo electronico ya se encuentra guardado.');
        }

        if ($recipients->count() >= 50) {
            return back()->with('warning', 'Solo se pueden guardar hasta 50 correos electronicos.');
        }

        $mailService->saveRecipients($recipients->push($recipient)->values()->all());

        return back()->with('status', 'Correo electronico agregado correctamente.');
    }

    public function removeRecipient(Request $request, ContractExpirationMailService $mailService)
    {
        $data = $request->validate([
            'recipient' => ['required', 'email:rfc', 'max:254'],
        ]);

        $recipient = mb_strtolower(trim($data['recipient']));
        $recipients = collect($mailService->recipients());

        if (! $recipients->contains($recipient)) {
            return back()->with('warning', 'El correo electronico ya no se encuentra en la lista.');
        }

        $mailService->saveRecipients(
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
