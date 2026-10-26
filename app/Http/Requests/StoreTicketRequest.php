<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\TicketCategory;
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
        // One read, one query: both halves come off the same row, so they cannot be
        // resolved against different categories.
        $flags = TicketCategory::detailFlagsFor((int) $this->currentTenantId(), $this->categoryId());

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
            // Applicability is resolved from the CATEGORY ROW, not from the browser:
            // `tenantExists('ticket_categories')` above is the first defence and
            // `TicketCategory::detailFlagsFor()`'s own explicit `tenant_id` predicate is
            // the second, so another workspace's category resolves to the no-category
            // answer rather than to its configuration.
            //
            // A ticket with NO category is asked NOTHING — both halves resolve false, so
            // this form requires none of the five. That is the whole point of moving the
            // gate: the questions belong to a category, and a ticket nobody has placed in
            // one has not been placed in a bucket that asks them. The five rules are still
            // EMITTED (as `nullable`), so a stray value is still refused.
        ] + TicketTechnicalFields::rules(
            internal: true,
            technical: $flags['technical'],
            asksImpact: $flags['asks_impact'],
        );
    }

    /**
     * The submitted category id as an int, or null when none was chosen — an unselected
     * select posts '', which is not a category.
     */
    private function categoryId(): ?int
    {
        $raw = $this->input('category_id');

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
