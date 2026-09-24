<?php

namespace App\Http\Requests;

use App\Domain\Support\Services\SupportAccess;
use Illuminate\Foundation\Http\FormRequest;

class SupportChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['client_id', 'conversation_id'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => strtolower($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        $creating = ! $this->route('conversation');

        return [
            'client_id' => ['required', 'uuid'],
            'conversation_id' => [$creating ? 'required' : 'sometimes', 'uuid'],
            'subject' => [$creating ? 'required' : 'sometimes', 'string', 'min:3', 'max:150', 'not_regex:/[\r\n]/'],
            'name' => [$creating && app(SupportAccess::class)->isPublic($this) ? 'required' : 'sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'message' => ['required', 'string', 'min:1', 'max:5000', 'not_regex:/^\s*$/u'],
            'privacy_acknowledged' => [$creating ? 'required' : 'sometimes', 'accepted'],
            'company_website' => ['nullable', 'string', 'max:0'],
            'attachments' => ['sometimes', 'array', 'max:3'],
            'attachments.*' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
    }
}
