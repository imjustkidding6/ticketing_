<?php

namespace App\Http\Requests;

use App\Models\DepartmentAiAgent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing one assignment's role, priority and flags (spec §34).
 *
 * No `ai_agent_id` rule: the agent is the route's own bound model, so there is no
 * request-supplied foreign key to scope here — and the binding is on a BelongsToTenant
 * model, which is what makes another workspace's id a 404 rather than a validation error.
 */
class UpdateDepartmentAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'auto_invoke' => $this->boolean('auto_invoke'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(DepartmentAiAgent::ROLES)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'auto_invoke' => ['boolean'],
        ];
    }
}
