<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;

class OnboardingService
{
    private const STEPS = [
        'customize_workspace' => [
            'label' => 'Customize your workspace',
            'description' => 'Add your company logo and configure your workspace name to make TechDesk feel like home for your team.',
            'route' => 'settings.general',
            'icon' => 'paint-brush',
        ],
        'review_departments' => [
            'label' => 'Review departments',
            'description' => 'We created default departments for you. Review them and adjust to match your organization structure.',
            'route' => 'departments.index',
            'icon' => 'building-office',
        ],
        'create_categories' => [
            'label' => 'Create ticket categories',
            'description' => 'Categories help classify tickets so your team can prioritize and route them effectively.',
            'route' => 'categories.index',
            'icon' => 'tag',
        ],
        'invite_member' => [
            'label' => 'Invite a team member',
            'description' => 'Add agents and managers to your workspace so they can start handling support requests.',
            'route' => 'members.create',
            'icon' => 'user-plus',
        ],
        'create_ticket' => [
            'label' => 'Create your first ticket',
            'description' => 'Try creating a ticket to see the full workflow — from submission to assignment to resolution.',
            'route' => 'tickets.create',
            'icon' => 'ticket',
        ],
        'explore_reports' => [
            'label' => 'Explore reports',
            'description' => 'View dashboards and reports to understand your team\'s performance and ticket trends.',
            'route' => 'reports.overview',
            'icon' => 'chart-bar',
        ],
    ];

    public function getSteps(Tenant $tenant): array
    {
        $onboarding = $tenant->settings['onboarding'] ?? [];
        $completedSteps = $onboarding['completed_steps'] ?? [];

        $steps = [];
        foreach (self::STEPS as $key => $step) {
            $steps[] = array_merge($step, [
                'key' => $key,
                'completed' => in_array($key, $completedSteps),
            ]);
        }

        return $steps;
    }

    public function completeStep(Tenant $tenant, string $step): void
    {
        if (! array_key_exists($step, self::STEPS)) {
            return;
        }

        $settings = $tenant->settings ?? [];
        $onboarding = $settings['onboarding'] ?? ['dismissed' => false, 'completed_steps' => []];

        if (! in_array($step, $onboarding['completed_steps'])) {
            $onboarding['completed_steps'][] = $step;
        }

        $settings['onboarding'] = $onboarding;
        $tenant->update(['settings' => $settings]);
    }

    public function dismiss(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        $onboarding = $settings['onboarding'] ?? ['dismissed' => false, 'completed_steps' => []];
        $onboarding['dismissed'] = true;
        $settings['onboarding'] = $onboarding;
        $tenant->update(['settings' => $settings]);
    }

    public function reset(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        $settings['onboarding'] = ['dismissed' => false, 'completed_steps' => []];
        $tenant->update(['settings' => $settings]);
    }

    public function isDismissed(Tenant $tenant): bool
    {
        return ($tenant->settings['onboarding']['dismissed'] ?? false) === true;
    }

    public function isComplete(Tenant $tenant): bool
    {
        $completedSteps = $tenant->settings['onboarding']['completed_steps'] ?? [];

        return count($completedSteps) >= count(self::STEPS);
    }

    public function progress(Tenant $tenant): array
    {
        $completedSteps = $tenant->settings['onboarding']['completed_steps'] ?? [];

        return [
            'completed' => count(array_intersect($completedSteps, array_keys(self::STEPS))),
            'total' => count(self::STEPS),
        ];
    }

    public function autoDetect(Tenant $tenant): void
    {
        $completedSteps = $tenant->settings['onboarding']['completed_steps'] ?? [];
        $changed = false;

        if (! in_array('customize_workspace', $completedSteps) && $tenant->logo_path) {
            $completedSteps[] = 'customize_workspace';
            $changed = true;
        }

        if (! in_array('create_categories', $completedSteps) && TicketCategory::count() > 0) {
            $completedSteps[] = 'create_categories';
            $changed = true;
        }

        if (! in_array('invite_member', $completedSteps)) {
            $userCount = User::whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenant->id))->count();
            if ($userCount >= 2) {
                $completedSteps[] = 'invite_member';
                $changed = true;
            }
        }

        if (! in_array('create_ticket', $completedSteps) && Ticket::count() > 0) {
            $completedSteps[] = 'create_ticket';
            $changed = true;
        }

        if ($changed) {
            $settings = $tenant->settings ?? [];
            $onboarding = $settings['onboarding'] ?? ['dismissed' => false, 'completed_steps' => []];
            $onboarding['completed_steps'] = $completedSteps;
            $settings['onboarding'] = $onboarding;
            $tenant->update(['settings' => $settings]);
        }
    }
}
