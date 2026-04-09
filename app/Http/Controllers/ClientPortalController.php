<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\PlanService;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    private const DANGEROUS_EXTENSIONS = ['php', 'exe', 'sh', 'bat', 'cmd', 'js', 'py', 'rb'];

    private const ALLOWED_COMMENT_ATTACHMENT_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    public function __construct(
        private TicketService $ticketService,
        private PlanService $planService,
    ) {}

    /**
     * Public landing page for the tenant portal.
     *
     * @deprecated This method serves the old /portal/{tenant}/ routes. Use publicLanding() instead.
     */
    public function index(Tenant $tenant): View|RedirectResponse
    {
        if ($tenant->isSuspended()) {
            abort(404);
        }

        $this->abortIfStarter($tenant);

        [$departments, $categories] = $this->loadDepartmentsAndCategories($tenant);

        return view('client-portal.index', compact('tenant', 'departments', 'categories'));
    }

    /**
     * Handle guest ticket submission (no login required).
     *
     * @deprecated This method is not referenced by any route. Use publicSubmitStore() instead.
     */
    public function storeGuestTicket(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->isSuspended()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'department_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $client = $this->findOrCreateGuestClient($tenant, $validated['email'], $validated['name']);
        $trackingToken = Str::random(64);

        $ticket = $this->ticketService->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'department_id' => $validated['department_id'],
            'category_id' => $validated['category_id'],
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => $trackingToken,
        ]);

        $this->notifyClientOfNewTicket($tenant, $client->email, $ticket);

        return redirect()->route('tenant.track-ticket.token', [
            'slug' => $tenant->slug,
            'token' => $trackingToken,
        ])->with('success', 'Your ticket has been submitted successfully! Ticket number: '.$ticket->ticket_number);
    }

    /**
     * Handle authenticated ticket submission.
     */
    public function storeTicket(Request $request, Tenant $tenant): RedirectResponse
    {
        $client = $request->get('portal_client');

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'department_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $ticket = $this->ticketService->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'department_id' => $validated['department_id'],
            'category_id' => $validated['category_id'],
            'client_id' => $client->id,
            'created_by' => Auth::id(),
        ]);

        if ($this->planService->tenantHasFeature($tenant, PlanFeature::EmailNotifications)) {
            Auth::user()->notify(new TicketCreatedNotification($ticket));
        }

        return redirect()->route('portal.dashboard', ['tenant' => $tenant->slug])
            ->with('success', 'Ticket submitted successfully.');
    }

    /**
     * Track a ticket by number and email (public, no auth required).
     *
     * @deprecated This method serves the old /portal/{tenant}/ routes. Use publicTrackForm() instead.
     */
    public function trackTicket(Request $request, Tenant $tenant): View
    {
        $ticket = null;
        $searched = false;

        if ($request->filled('ticket_number') && $request->filled('email')) {
            $searched = true;

            $ticket = Ticket::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('ticket_number', $request->input('ticket_number'))
                ->whereHas('client', function ($query) use ($request) {
                    $query->withoutGlobalScopes()->where('email', $request->input('email'));
                })
                ->with(['department', 'category'])
                ->first();
        }

        return view('client-portal.track-ticket', compact('tenant', 'ticket', 'searched'));
    }

    /**
     * Track a ticket by its unique tracking token.
     *
     * @deprecated This method serves the old /portal/{tenant}/ routes. Use publicTrackByToken() instead.
     */
    public function trackByToken(Tenant $tenant, string $token): View
    {
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tracking_token', $token)
            ->with(['department', 'category'])
            ->firstOrFail();

        return view('client-portal.track-result', compact('tenant', 'ticket'));
    }

    /**
     * Show the portal login form.
     */
    public function showLogin(Tenant $tenant): View
    {
        $this->abortIfStarter($tenant);

        return view('client-portal.login', compact('tenant'));
    }

    /**
     * Handle portal login.
     */
    public function login(Request $request, Tenant $tenant): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $client = Client::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', Auth::id())
                ->where('status', Client::STATUS_ACTIVE)
                ->first();

            if (! $client) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'You do not have access to this portal.',
                ]);
            }

            return redirect()->route('portal.dashboard', ['tenant' => $tenant->slug]);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show the portal registration form.
     */
    public function showRegister(Tenant $tenant): View
    {
        $this->abortIfStarter($tenant);

        return view('client-portal.register', compact('tenant'));
    }

    /**
     * Handle portal registration.
     */
    public function register(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Link existing guest client to this user account, or create new
        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->whereNull('user_id')
            ->first();

        if ($client) {
            $client->update(['user_id' => $user->id]);
        } else {
            Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'name' => $validated['company'] ?? $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'contact_person' => $validated['name'],
                'tier' => Client::TIER_BASIC,
                'status' => Client::STATUS_ACTIVE,
            ]);
        }

        Auth::login($user);

        return redirect()->route('portal.dashboard', ['tenant' => $tenant->slug]);
    }

    /**
     * Show the client portal dashboard.
     */
    public function dashboard(Request $request, Tenant $tenant): View
    {
        $this->abortIfStarter($tenant);
        $client = $request->get('portal_client');

        $recentTickets = $client->tickets()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'open' => $client->tickets()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
            'closed' => $client->tickets()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'closed')->count(),
            'total' => $client->tickets()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
        ];

        return view('client-portal.dashboard', compact('tenant', 'client', 'recentTickets', 'stats'));
    }

    /**
     * Show ticket submission form (authenticated).
     */
    public function createTicket(Request $request, Tenant $tenant): View
    {
        [$departments, $categories] = $this->loadDepartmentsAndCategories($tenant);

        $kbSearchUrl = $this->planService->tenantHasFeature($tenant, PlanFeature::KnowledgeBase)
            ? route('portal.knowledge-base.search', ['slug' => $tenant->slug])
            : null;

        return view('client-portal.create-ticket', compact('tenant', 'departments', 'categories', 'kbSearchUrl'));
    }

    /**
     * Show a specific ticket (authenticated).
     */
    public function showTicket(Request $request, Tenant $tenant, int $ticketId): View
    {
        $client = $request->get('portal_client');

        $ticket = $client->tickets()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('id', $ticketId)
            ->with(['category', 'department'])
            ->firstOrFail();

        return view('client-portal.show-ticket', compact('tenant', 'client', 'ticket'));
    }

    /**
     * Handle portal logout.
     */
    public function logout(Request $request, Tenant $tenant): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.index', ['tenant' => $tenant->slug]);
    }

    /**
     * Public landing page for the tenant (under /{slug}/).
     */
    public function publicLanding(string $slug): View|RedirectResponse
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        return view('tenant.landing', compact('tenant'));
    }

    /**
     * Public submit ticket form (no auth required, under /{slug}/submit-ticket).
     */
    public function publicSubmitForm(string $slug): View
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        [$departments, $categories] = $this->loadDepartmentsAndCategories($tenant);

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->ordered()
            ->get();

        $kbSearchUrl = $this->planService->tenantHasFeature($tenant, PlanFeature::KnowledgeBase)
            ? route('portal.knowledge-base.search', ['slug' => $tenant->slug])
            : null;

        return view('tenant.submit-ticket', compact('tenant', 'departments', 'categories', 'products', 'kbSearchUrl'));
    }

    /**
     * Handle public guest ticket submission (under /{slug}/submit-ticket).
     */
    public function publicSubmitStore(Request $request, string $slug): RedirectResponse
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'department_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'incident_date' => ['nullable', 'date'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        $client = $this->findOrCreateGuestClient($tenant, $validated['email'], $validated['name']);
        $trackingToken = Str::random(64);

        $ticket = $this->ticketService->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'department_id' => $validated['department_id'],
            'category_id' => $validated['category_id'],
            'incident_date' => $validated['incident_date'] ?? null,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => $trackingToken,
        ]);

        if (! empty($validated['product_ids'])) {
            $ticket->products()->sync($validated['product_ids']);
        }

        $this->notifyClientOfNewTicket($tenant, $client->email, $ticket);

        return redirect()->route('tenant.track-ticket.token', [
            'slug' => $tenant->slug,
            'token' => $trackingToken,
        ])->with('success', 'Your ticket has been submitted successfully! Ticket number: '.$ticket->ticket_number);
    }

    /**
     * Public track ticket form (no auth required, under /{slug}/track-ticket).
     */
    public function publicTrackForm(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        if (! $request->filled('ticket_number') || ! $request->filled('email')) {
            return view('tenant.track-ticket', ['tenant' => $tenant, 'searched' => false]);
        }

        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('ticket_number', $request->input('ticket_number'))
            ->whereHas('client', fn ($query) => $query->withoutGlobalScopes()->where('email', $request->input('email')))
            ->first();

        if (! $ticket) {
            return view('tenant.track-ticket', ['tenant' => $tenant, 'searched' => true]);
        }

        if (! $ticket->tracking_token) {
            $ticket->update(['tracking_token' => Str::random(64)]);
        }

        return redirect()->route('tenant.track-ticket.token', [
            'slug' => $tenant->slug,
            'token' => $ticket->tracking_token,
        ]);
    }

    /**
     * Public track ticket by token (under /{slug}/track-ticket/{token}).
     */
    public function publicTrackByToken(string $slug, string $token): View
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tracking_token', $token)
            ->with(['department', 'category', 'client', 'comments' => fn ($q) => $q->where('is_public', true)->with('user')->oldest()])
            ->firstOrFail();

        $canReply = $this->planService->tenantHasFeature($tenant, PlanFeature::ClientComments)
            && ! in_array($ticket->status, ['closed', 'cancelled']);

        return view('tenant.track-result', compact('tenant', 'ticket', 'canReply'));
    }

    /**
     * Handle public client reply on a ticket (under /{slug}/track-ticket/{token}/reply).
     */
    public function publicReply(Request $request, string $slug, string $token): RedirectResponse
    {
        $tenant = $this->resolvePublicTenant($slug);
        $this->abortIfStarter($tenant);

        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::ClientComments)) {
            abort(403);
        }

        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tracking_token', $token)
            ->firstOrFail();

        if (in_array($ticket->status, ['closed', 'cancelled'])) {
            return redirect()->route('tenant.track-ticket.token', ['slug' => $slug, 'token' => $token])
                ->with('error', 'This ticket is closed and cannot receive new replies.');
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile && $this->hasDangerousExtension($value)) {
                        $fail('File type not allowed for security reasons');
                    }
                },
            ],
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $index => $file) {
                $detectedMime = $this->validateSafeAttachment($file, "attachments.{$index}", self::ALLOWED_COMMENT_ATTACHMENT_MIME_TYPES);
                $path = $file->store('tenants/' . $tenant->id . '/comment-attachments', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $detectedMime,
                ];
            }
        }

        TicketComment::create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'content' => $validated['content'],
            'type' => 'public',
            'is_public' => true,
            'attachments' => ! empty($attachments) ? $attachments : null,
        ]);

        return redirect()->route('tenant.track-ticket.token', ['slug' => $slug, 'token' => $token])
            ->with('success', 'Your reply has been submitted.');
    }

    /**
     * Abort if tenant is on the Starter plan (no public portal access).
     */
    private function abortIfStarter(Tenant $tenant): void
    {
        $planSlug = $tenant->plan()?->slug;
        if ($planSlug === 'start' || $planSlug === null) {
            abort(404);
        }
    }

    /**
     * Resolve a tenant from a slug string for public (non-authenticated) pages.
     */
    private function resolvePublicTenant(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->firstOrFail();
    }

    /**
     * Load active, ordered departments and categories for a tenant.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: \Illuminate\Database\Eloquent\Collection}
     */
    private function loadDepartmentsAndCategories(Tenant $tenant): array
    {
        $departments = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->ordered()
            ->get();

        $categories = TicketCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->ordered()
            ->get();

        return [$departments, $categories];
    }

    private function validateSafeAttachment(UploadedFile $file, string $field, array $allowedMimeTypes): string
    {
        if ($this->hasDangerousExtension($file)) {
            throw ValidationException::withMessages([
                $field => 'File type not allowed for security reasons',
            ]);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $declaredMime = $file->getClientMimeType();

        if (! is_string($detectedMime)) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file content type is not allowed.',
            ]);
        }

        $normalizedDetectedMime = $this->normalizeMimeType($detectedMime);
        $normalizedDeclaredMime = is_string($declaredMime) ? $this->normalizeMimeType($declaredMime) : null;

        if (
            ! in_array($normalizedDetectedMime, $allowedMimeTypes, true)
            || ! is_string($normalizedDeclaredMime)
            || ! in_array($normalizedDeclaredMime, $allowedMimeTypes, true)
            || $normalizedDeclaredMime !== $normalizedDetectedMime
        ) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file content does not match its declared type.',
            ]);
        }

        return $normalizedDetectedMime;
    }

    private function hasDangerousExtension(UploadedFile $file): bool
    {
        $segments = array_filter(
            array_map('trim', explode('.', strtolower($file->getClientOriginalName()))),
            static fn (string $segment): bool => $segment !== ''
        );

        foreach ($segments as $segment) {
            if (in_array($segment, self::DANGEROUS_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'image/jpg' => 'image/jpeg',
            default => strtolower(trim($mimeType)),
        };
    }

    /**
     * Find an existing client by email for the tenant, or create a new guest client.
     */
    private function findOrCreateGuestClient(Tenant $tenant, string $email, string $name): Client
    {
        return Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first()
            ?? Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'contact_person' => $name,
                'tier' => Client::TIER_BASIC,
                'status' => Client::STATUS_ACTIVE,
            ]);
    }

    /**
     * Send a ticket-created notification to the client if the tenant has the feature enabled.
     */
    private function notifyClientOfNewTicket(Tenant $tenant, string $email, Ticket $ticket): void
    {
        if ($this->planService->tenantHasFeature($tenant, PlanFeature::EmailNotifications)) {
            Notification::route('mail', $email)
                ->notify(new TicketCreatedNotification($ticket));
        }
    }
}
