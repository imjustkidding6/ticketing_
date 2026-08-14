<?php

namespace App\Http\Requests;

use App\Support\TenantValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'client_id' => ['required', TenantValidation::exists('clients')],
            'category_id' => ['nullable', TenantValidation::exists('ticket_categories')],
            'department_id' => ['nullable', TenantValidation::exists('departments')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [TenantValidation::exists('products')],
            'assigned_to' => ['nullable', TenantValidation::user()],
            'incident_date' => ['nullable', 'date'],
            'tasks' => ['nullable', 'array', 'max:20'],
            'tasks.*' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv,txt,zip'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Please select a client.',
            'client_id.exists' => 'The selected client is invalid.',
        ];
    }
}
