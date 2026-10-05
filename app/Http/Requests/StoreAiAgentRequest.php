<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\AiAgent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAiAgentRequest extends FormRequest
{
    use ScopesRulesToTenant;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
            // An empty textarea stores NULL, not ''. effectiveSystemPrompt() tests with
            // filled() so both fall back, but keeping "unedited" a single representable
            // state is what makes a wording fix to DEFAULT_SYSTEM_PROMPT reach the row.
            'system_prompt' => filled($this->input('system_prompt')) ? $this->input('system_prompt') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('ai_agents', 'slug')->where('tenant_id', $this->currentTenantId() ?? 0),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            // The constant is the only enumeration — role_type is a varchar, as
            // tickets.status is, so every entry point validates against the list.
            'role_type' => ['required', Rule::in(AiAgent::ROLE_TYPES)],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Plain exists, deliberately: ai_profiles is global platform configuration
            // with no tenant_id column, so tenantExists() would predicate on a column
            // that does not exist and error at validation time.
            'ai_profile_id' => ['nullable', 'integer', 'exists:ai_profiles,id'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'An AI agent with a matching name already exists in this workspace.',
        ];
    }
}
