<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
{
    use ScopesRulesToTenant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The slug is derived, never posted — one mechanical rule (Str::slug) so a skill an
     * administrator re-creates by name collides with the seeded row instead of shadowing
     * it. It is validated for uniqueness too, so a derived collision is a message rather
     * than a duplicate-key 500.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->currentTenantId() ?? 0;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('skills', 'name')->where('tenant_id', $tenantId),
            ],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('skills', 'slug')->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            // Free text, not Rule::in(SkillCatalogue::CATEGORIES): the nineteen seeded
            // categories are a filter vocabulary, and an administrator may name their own.
            'skill_category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A skill with this name already exists in this workspace.',
            'slug.unique' => 'A skill with a matching name already exists in this workspace.',
        ];
    }
}
