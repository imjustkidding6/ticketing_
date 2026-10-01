{{--
    The department edit tabs (spec §33).

    The repo's settings screens render their tab bar inline, one tab per page, so this is
    the same shape extracted to a partial because two pages now share it. The AI Agents tab
    is both plan-gated and permission-gated: an agent who may edit a department but not the
    AI configuration sees one tab, not a tab that 403s.
--}}
@php($isAiTab = request()->routeIs('departments.ai-agents.*'))
<div class="mb-6 flex gap-4 border-b border-gray-200">
    <a href="{{ route('departments.edit', $department) }}"
       class="border-b-2 px-4 py-2 text-sm font-medium {{ $isAiTab ? 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' : 'border-indigo-500 text-indigo-600' }}">
        {{ __('General') }}
    </a>
    {{-- $canManageAiAgents comes from the controller's userCan(), which carries the owner
         bypass a bare $user->can() in a view would miss. --}}
    @if(($canManageAiAgents ?? false) && app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AiAgents))
        <a href="{{ route('departments.ai-agents.index', $department) }}"
           class="border-b-2 px-4 py-2 text-sm font-medium {{ $isAiTab ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
            {{ __('AI Agents') }}
        </a>
    @endif
</div>
