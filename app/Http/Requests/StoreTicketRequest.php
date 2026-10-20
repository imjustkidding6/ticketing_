<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\Department;
use App\Support\TicketTechnicalFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'client_id' => ['required', $this->tenantExists('clients')],
            'category_id' => ['nullable', $this->tenantExists('ticket_categories')],
            'department_id' => ['nullable', $this->tenantExists('departments')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [$this->tenantExists('products')],
            'assigned_to' => array_merge(['nullable'], $this->tenantMemberRules()),
            'incident_date' => ['nullable', 'date'],
            'tasks' => ['nullable', 'array', 'max:20'],
            'tasks.*' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            // An opaque cache KEY, re-scoped to this workspace and this user when it is
            // redeemed — never an id, and never the suggestion data itself. A tampered
            // value simply finds nothing and the ticket is created exactly as it is today.
            'ai_draft_token' => ['nullable', 'string', 'size:32', 'alpha_num'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv,txt,zip'],
            // The declared technical detail set, with `required` where the declaration says
            // so — nothing is hand-written here, so the required selects and the optional
            // text fields cannot drift from the forms or the ticket page.
            //
            // Applicability is resolved from the DEPARTMENT ROW, not from the browser:
            // `tenantExists('departments')` above is the first defence and
            // `Department::isTechnicalFor()`'s own explicit `tenant_id` predicate is the
            // second, so another workspace's department resolves to false (fewer required
            // fields) rather than to its answer. A ticket with no department is not
            // technical, which asks for less rather than fabricating a requirement.
            //
            // The impact pair is gated the same way and resolved from the same row, but
            // its absent-department fallback is TRUE rather than false (see
            // Department::asksImpactFor()): a ticket with no department is still asked who
            // is affected, because those two answers are what the priority rubric
            // separates critical from high on.
        ] + TicketTechnicalFields::rules(
            internal: true,
            technical: Department::isTechnicalFor(
                (int) $this->currentTenantId(),
                $this->departmentId(),
            ),
            asksImpact: Department::asksImpactFor(
                (int) $this->currentTenantId(),
                $this->departmentId(),
            ),
        );
    }

    /**
     * The submitted department id as an int, or null when none was chosen — read once so
     * the two applicability resolvers cannot be given different ids.
     */
    private function departmentId(): ?int
    {
        $raw = $this->input('department_id');

        return $raw === null || $raw === '' ? null : (int) $raw;
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
