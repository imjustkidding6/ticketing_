<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\DepartmentAiAgent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assigning an agent to a department (spec §34).
 */
class StoreDepartmentAiAgentRequest extends FormRequest
{
    use ScopesRulesToTenant;

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
        $departmentId = $this->route('department')?->getKey();

        return [
            'ai_agent_id' => [
                'required', 'integer',
                // tenantExists, not exists:ai_agents,id — the plain rule queries the raw
                // table and accepts another workspace's agent id. Department::assignAiAgent()
                // would then throw a 500 where the administrator should see a message.
                $this->tenantExists('ai_agents'),
                // One row per (department, agent). assignAiAgent() is an updateOrCreate, so
                // without this a re-add silently overwrites the existing role and priority
                // instead of telling the administrator the agent is already there.
                Rule::unique('department_ai_agent', 'ai_agent_id')->where('department_id', $departmentId),
            ],
            'role' => ['required', Rule::in(DepartmentAiAgent::ROLES)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'auto_invoke' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_agent_id.exists' => 'That AI agent does not belong to this workspace.',
            'ai_agent_id.unique' => 'That AI agent is already assigned to this department.',
        ];
    }
}
