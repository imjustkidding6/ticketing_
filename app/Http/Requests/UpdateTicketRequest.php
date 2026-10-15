<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\Ticket;
use App\Support\TicketTechnicalFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    use ScopesRulesToTenant;

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
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'client_id' => ['required', $this->tenantExists('clients')],
            'category_id' => ['nullable', $this->tenantExists('ticket_categories')],
            'department_id' => ['nullable', $this->tenantExists('departments')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [$this->tenantExists('products')],
            'assigned_to' => array_merge(['nullable'], $this->tenantMemberRules()),
            'incident_date' => ['nullable', 'date'],
            // ALL FIVE nullable on update, including the three required at creation, for
            // three reasons: the "information needed" list has to have somewhere to send
            // the engineer, which is this form; `_ai-analyze`'s Apply posts the whole
            // update form as rendered and its hidden snapshot carries none of these keys,
            // so `required` would turn every Apply into a 422; and a ticket created before
            // the migration has all five NULL and must stay editable. `nullable` also means
            // an absent key never reaches validated(), so Apply cannot wipe a stored value.
        ] + TicketTechnicalFields::rules(internal: false);
    }
}
