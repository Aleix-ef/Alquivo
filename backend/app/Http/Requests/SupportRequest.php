<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The authenticated endpoint cannot be used to impersonate another sender.
        if ($this->is('api/v1/support') && $this->user()) {
            $this->merge(['email' => $this->user()->email]);
        } elseif (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'subject' => ['required', 'string', 'min:3', 'max:150', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy_acknowledged' => ['required', 'accepted'],
            'company_website' => ['nullable', 'string', 'max:0'],
            'attachments' => ['sometimes', 'array', 'max:3'],
            'attachments.*' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Indica tu nombre.',
            'name.max' => 'El nombre no puede superar 100 caracteres.',
            'email.required' => 'Indica un correo para poder responderte.',
            'email.email' => 'Revisa la dirección de correo.',
            'subject.required' => 'Indica el asunto de tu consulta.',
            'subject.min' => 'El asunto debe tener al menos 3 caracteres.',
            'subject.max' => 'El asunto no puede superar 150 caracteres.',
            'message.required' => 'Cuéntanos en qué podemos ayudarte.',
            'message.min' => 'Añade algo más de detalle (al menos 10 caracteres).',
            'message.max' => 'El mensaje no puede superar 5.000 caracteres.',
            'privacy_acknowledged.accepted' => 'Confirma que has leído la información de privacidad.',
            'attachments.max' => 'Puedes adjuntar hasta 3 archivos.',
            'attachments.*.max' => 'Cada archivo puede ocupar como máximo 2 MB.',
            'attachments.*.mimes' => 'Solo se admiten imágenes JPG, PNG, WebP y documentos PDF.',
            'attachments.*.mimetypes' => 'El contenido del archivo no corresponde a un formato permitido.',
            '*.not_regex' => 'Este campo no admite saltos de línea.',
        ];
    }
}
