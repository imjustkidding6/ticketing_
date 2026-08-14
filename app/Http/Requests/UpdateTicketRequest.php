<?php

namespace App\Http\Requests;

use App\Support\TenantValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
            'status' => ['required', 'in:open,assigned,in_progress,on_hold,closed,cancelled'],
            'client_id' => ['required', TenantValidation::exists('clients')],
            'category_id' => ['nullable', TenantValidation::exists('ticket_categories')],
            'department_id' => ['nullable', TenantValidation::exists('departments')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [TenantValidation::exists('products')],
            'assigned_to' => ['nullable', TenantValidation::user()],
            'incident_date' => ['nullable', 'date'],
        ];
    }
}
