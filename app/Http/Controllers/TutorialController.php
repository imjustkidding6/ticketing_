<?php

namespace App\Http\Controllers;

use App\Services\HelpService;
use App\Services\OnboardingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TutorialController extends Controller
{
    public const TUTORIALS = [
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
        'ai-assistant' => [
            'title' => 'AI Assistant',
            'description' => 'Meet your AI teammate — ask it anything, get charts, and resolve tickets faster.',
            'icon' => 'sparkles',
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

    public function __construct(private HelpService $help) {}

    public function index(OnboardingService $onboardingService): View
    {
        $user = Auth::user();
        $onboardingDismissed = false;
        if ($user && method_exists($user, 'currentTenant')) {
            $tenant = $user->currentTenant();
            if ($tenant) {
                $onboardingDismissed = $onboardingService->isDismissed($tenant);
            }
        }

        $viewName = request()->routeIs('admin.*') ? 'admin.help.index' : 'tutorials.index';

        $topics = $this->help->topicsFor($user);

        return view($viewName, [
            'tutorials' => self::TUTORIALS,
            'onboardingDismissed' => $onboardingDismissed,
            // Everything below is scoped to this reader. The page, the contents
            // list, the search index and the PDF all read the same result.
            'topics' => $topics,
            'groups' => $this->help->groupedTopicsFor($user),
            'tours' => $this->help->toursFor($user),
            'hiddenTopics' => $this->help->hiddenTopicsFor($user),
            'totalMinutes' => $this->help->totalMinutesFor($user),
            'searchText' => collect($topics)->map(fn ($t) => $this->help->searchTextFor($t))->all(),
        ]);
    }

    public function show(string $tutorial): View|RedirectResponse
    {
        // The tenant help centre renders every topic on one page from
        // HelpService, so a per-topic template would be a second copy of the
        // content — and the two would disagree within a month. Send old links
        // to the topic where it now lives.
        if (! request()->routeIs('admin.*')) {
            if (! array_key_exists($tutorial, config('help.topics', []))) {
                abort(404);
            }

            return redirect()->to(route('tutorials.index').'#topic-'.$tutorial);
        }

        if (! array_key_exists($tutorial, self::TUTORIALS)) {
            abort(404);
        }

        return view('admin.help.show', [
            'slug' => $tutorial,
            'tutorial' => self::TUTORIALS[$tutorial],
            'tutorials' => self::TUTORIALS,
        ]);
    }

    /**
     * Download the full Help & Tutorials guide as a single PDF.
     */
    public function downloadPdf(): Response
    {
        $user = Auth::user();
        $tenant = $user->currentTenant();

        // Generated from the same filtered content as the page. A static
        // manual handed to everyone would undo the scoping entirely.
        $pdf = Pdf::loadView('tutorials.pdf', [
            'tutorials' => self::TUTORIALS,
            'topics' => $this->help->topicsFor($user),
            'groups' => $this->help->groupedTopicsFor($user),
            'totalMinutes' => $this->help->totalMinutesFor($user),
            'tenant' => $tenant,
        ])->setPaper('a4');

        return $pdf->download('CliqueHA-Nexus-Help-and-Tutorials.pdf');
    }

    /**
     * Download the complete System Administrator User Manual as a PDF document.
     */
    public function downloadManual()
    {
        $path = public_path('docs/Admin-User-Manual.pdf');
        if (! file_exists($path) || filesize($path) < 1000) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.help.manual-pdf');
            file_put_contents($path, $pdf->output());
        }

        return response()->download($path, 'Admin-User-Manual.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
