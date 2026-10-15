<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Services\AI\DraftSuggestionService;
use App\Support\TicketTechnicalFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The "Polish with Nexus AI" request from the ticket create/edit forms.
 *
 * Replaces an inline `$request->validate()` because this surface now accepts a FOREIGN
 * KEY from the browser: `client_id` decides which SLA policies the admissible-priority set
 * is computed against, and `department_id` decides which staff become engineer
 * candidates. A bare `exists:clients,id` queries the raw table before any model,
 * so the global TenantScope never sees the value and another workspace's id is accepted —
 * `tenantExists()` is what closes that, and it is available here because this route, unlike
 * the automatic-analysis job, has a session.
 */
class StructureTicketDraftRequest extends FormRequest
{
    use ScopesRulesToTenant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:2000'],
            'description' => ['required', 'string', 'max:8000'],
            'client_id' => ['nullable', $this->tenantExists('clients')],
            // The engineer suggestion's whole scope. A bare `exists:departments,id` would
            // accept another workspace's id and hand back its staff as candidates;
            // `departmentOwnedBy()` in the service is the second defence, not the first.
            'department_id' => ['nullable', $this->tenantExists('departments')],
            'context' => ['nullable', 'in:'.DraftSuggestionService::CONTEXT_CREATE.','.DraftSuggestionService::CONTEXT_EDIT],
            // Always the nullable variant: Polish is pressed mid-draft on a half-filled
            // form, so a `required` rule here would 422 the button instead of polishing.
        ] + TicketTechnicalFields::rules(internal: false);
    }
}
