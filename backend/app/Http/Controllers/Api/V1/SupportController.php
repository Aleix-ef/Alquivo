<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\UploadScanner;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportRequest;
use App\Mail\SupportMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportController extends Controller
{
    public function __invoke(): array
    {
        $email = config('support.email');

        return ['email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null];
    }

    public function store(SupportRequest $request, UploadScanner $scanner)
    {
        $recipient = config('support.email');
        abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL), 503, 'El envío de soporte no está disponible. Inténtalo más tarde.');
        // Never write messages / base64 attachments to a development mail log.
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        abort_unless(in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail'], true), 503, 'El envío desde la aplicación aún no está configurado. Puedes escribirnos por correo; tu mensaje no se ha enviado.');
        $files = array_values($request->file('attachments', []));
        foreach ($files as $index => $file) {
            try {
                $scanner->scan($file);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['attachments.'.$index => ['El archivo no ha superado el análisis de seguridad.']]);
            }
        }
        $reference = (string) Str::uuid();
        $details = [
            ...$request->safe()->only(['name', 'email', 'subject', 'message']),
            'reference' => $reference,
            'source' => $request->is('api/v1/support')
                ? 'Cuenta de Alquivo #'.$request->user()->id.($request->user()->hasVerifiedEmail() ? ' (correo verificado)' : ' (correo sin verificar)')
                : 'Formulario público (correo indicado por el visitante)',
        ];
        try {
            Mail::to($recipient)->send(new SupportMessage($details, $files));
        } catch (\Throwable $exception) {
            Log::warning('Support delivery failed', ['reference' => $reference, 'type' => get_class($exception)]);

            return response()->json(['message' => 'No hemos podido confirmar el envío. Conservamos el texto en el formulario para que puedas intentarlo de nuevo o escribirnos por correo.'], 503);
        }

        return response()->json(['message' => 'Tu consulta se ha enviado a soporte. Te responderemos por correo.', 'reference' => $reference], 201);
    }
}
