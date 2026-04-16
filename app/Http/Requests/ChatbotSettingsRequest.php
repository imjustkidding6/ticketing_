<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chatbot_provider' => ['nullable', 'string', 'max:50'],
            'chatbot_model' => ['nullable', 'string', 'max:100'],
            'chatbot_api_key' => ['nullable', 'string', 'max:255'],
            'chatbot_system_prompt' => ['nullable', 'string', 'max:2000'],
            'chatbot_confidence_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
