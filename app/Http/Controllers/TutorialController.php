<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TutorialController extends Controller
{
    private const TUTORIALS = [
        'getting-started' => [
            'title' => 'Getting Started',
            'description' => 'Set up your workspace, departments, categories, and invite your team.',
            'icon' => 'rocket-launch',
        ],
        'managing-tickets' => [
            'title' => 'Managing Tickets',
            'description' => 'Create, assign, and resolve tickets. Learn the full ticket lifecycle.',
            'icon' => 'ticket',
        ],
        'client-management' => [
            'title' => 'Client Management',
            'description' => 'Add clients, set up the public portal, and enable ticket tracking.',
            'icon' => 'users',
        ],
        'reports-analytics' => [
            'title' => 'Reports & Analytics',
            'description' => 'View dashboards, generate reports, and export data for analysis.',
            'icon' => 'chart-bar',
        ],
        'settings-configuration' => [
            'title' => 'Settings & Configuration',
            'description' => 'Configure general, ticket, notification, and branding settings.',
            'icon' => 'cog',
        ],
        'sla-management' => [
            'title' => 'SLA Management',
            'description' => 'Set up SLA policies, track breaches, and monitor compliance.',
            'icon' => 'clock',
        ],
    ];

    public function index(OnboardingService $onboardingService): View
    {
        $tenant = Auth::user()->currentTenant();
        $onboardingDismissed = $onboardingService->isDismissed($tenant);

        return view('tutorials.index', [
            'tutorials' => self::TUTORIALS,
            'onboardingDismissed' => $onboardingDismissed,
        ]);
    }

    public function show(string $tutorial): View
    {
        if (! array_key_exists($tutorial, self::TUTORIALS)) {
            abort(404);
        }

        return view('tutorials.show', [
            'slug' => $tutorial,
            'tutorial' => self::TUTORIALS[$tutorial],
            'tutorials' => self::TUTORIALS,
        ]);
    }
}
