<?php

namespace App\Http\Controllers;

use App\Services\HelpService;
use App\Services\OnboardingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TutorialController extends Controller
{
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

        // Platform staff hold no tenant role, so filtering by permission would
        // hand them an empty guide. They read the whole thing.
        $isAdminSurface = request()->routeIs('admin.*');
        $topics = $isAdminSurface ? $this->help->allTopics() : $this->help->topicsFor($user);

        return view($viewName, [
            'onboardingDismissed' => $onboardingDismissed,
            // Everything below is scoped to this reader. The page, the contents
            // list, the search index and the PDF all read the same result.
            'topics' => $topics,
            'groups' => $isAdminSurface ? $this->help->allGroupedTopics() : $this->help->groupedTopicsFor($user),
            'tours' => $this->help->toursFor($user),
            'hiddenTopics' => $this->help->hiddenTopicsFor($user),
            'totalMinutes' => $this->help->totalMinutesFor($user),
            'searchText' => collect($topics)->map(fn ($t) => $this->help->searchTextFor($t))->all(),
        ]);
    }

    public function show(string $tutorial): RedirectResponse
    {
        // Both surfaces render every topic on one page from HelpService, so a
        // per-topic template would be a second copy of the content and the two
        // would disagree within a month. Send old links where it now lives.
        if (! array_key_exists($tutorial, config('help.topics', []))) {
            abort(404);
        }

        $index = request()->routeIs('admin.*')
            ? route('admin.help.index')
            : route('tutorials.index');

        return redirect()->to($index.'#topic-'.$tutorial);
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
            $pdf = Pdf::loadView('admin.help.manual-pdf');
            file_put_contents($path, $pdf->output());
        }

        return response()->download($path, 'Admin-User-Manual.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
