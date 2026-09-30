<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\AiAgentSkill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAiAgentSkillsRequest extends FormRequest
{
    use ScopesRulesToTenant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The editor's last row is an empty "Add a skill…" picker, so a save that adds nothing
     * posts one row with a blank id. Dropping those here keeps `required` meaning "you
     * chose a skill and left something else out" rather than "you didn't use the add row".
     */
    protected function prepareForValidation(): void
    {
        $rows = array_values(array_filter(
            (array) $this->input('skills', []),
            fn ($row) => is_array($row) && filled($row['skill_id'] ?? null),
        ));

        $this->merge(['skills' => $rows]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skills' => ['nullable', 'array'],
            // tenantExists, not exists: a plain exists:skills,id accepts another
            // workspace's id, and attachSkill()'s cross-tenant assertion would then
            // surface as a 500 instead of a validation message.
            'skills.*.skill_id' => ['required', 'integer', 'distinct', $this->tenantExists('skills')],
            'skills.*.proficiency' => ['required', Rule::in(AiAgentSkill::PROFICIENCIES)],
            'skills.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'skills.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skills.*.skill_id.distinct' => 'The same skill cannot be added to an agent twice.',
            'skills.*.skill_id.exists' => 'That skill does not belong to this workspace.',
        ];
    }
}
