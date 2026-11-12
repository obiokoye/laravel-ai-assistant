<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => trim((string) $this->input('message'))]);
    }
}
