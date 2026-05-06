<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class GoogleSocialiteController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('google_intent', $request->query('intent', 'login'));

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth error: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('login')
                ->withErrors(['email' => 'Google authentication failed. Please try again.']);
        }

        $request->session()->pull('google_intent', 'login');

        // Already linked account
        $user = User::where('google_id', $googleUser->getId())->first();
        if ($user) {
            return $this->loginUser($user, $request);
        }

        // Existing account by email — link and log in
        $user = User::where('email', strtolower($googleUser->getEmail()))->first();
        if ($user) {
            $user->update(['google_id' => $googleUser->getId()]);
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            return $this->loginUser($user, $request);
        }

        // New user — store profile and show registration form
        $request->session()->put('google_profile', [
            'google_id' => $googleUser->getId(),
            'name'      => $googleUser->getName(),
            'email'     => strtolower($googleUser->getEmail()),
        ]);

        return redirect()->route('auth.google.register.form');
    }

    public function showRegisterForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('google_profile')) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Google sign-in session expired. Please try again.']);
        }

        return view('auth.google-register', [
            'profile' => $request->session()->get('google_profile'),
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        if (! $request->session()->has('google_profile')) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Session expired. Please sign in with Google again.']);
        }

        $profile = $request->session()->get('google_profile');

        $request->validate([
            'license_key'  => ['required', 'string', 'size:24'],
            'company_name' => ['required', 'string', 'max:255'],
            'app_slug'     => [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/',
                'unique:tenants,slug',
                Rule::notIn(['admin', 'www', 'mail', 'api', 'portal', 'app',
                    'support', 'help', 'status', 'login', 'register',
                    'profile', 'up', 'logout']),
            ],
        ]);

        // Race condition: email registered while user was filling the form
        $existing = User::where('email', $profile['email'])->first();
        if ($existing) {
            $existing->update(['google_id' => $profile['google_id']]);
            $request->session()->forget('google_profile');
            return $this->loginUser($existing, $request);
        }

        $licenseKey = strtoupper($request->license_key);
        $license    = License::where('license_key', $licenseKey)->first();

        if (! $license) {
            return back()->withErrors(['license_key' => 'Invalid license key.'])->withInput();
        }
        if ($license->status !== License::STATUS_PENDING) {
            return back()->withErrors(['license_key' => 'This license key has already been activated or is no longer valid.'])->withInput();
        }
        if ($license->isFullyExpired()) {
            return back()->withErrors(['license_key' => 'This license key has expired.'])->withInput();
        }

        /** @var array{user: User, tenant: Tenant} $result */
        $result = DB::transaction(function () use ($request, $license, $profile) {
            $tenant = Tenant::create([
                'name' => $request->company_name,
                'slug' => $request->app_slug,
            ]);

            $license->activate($tenant);

            $user = User::create([
                'name'              => $profile['name'],
                'email'             => $profile['email'],
                'google_id'         => $profile['google_id'],
                'email_verified_at' => now(),
                'password'          => null,
            ]);

            $tenant->addUser($user, 'owner');

            \Database\Seeders\DepartmentSeeder::seedForTenant($tenant);
            app(\App\Services\TenantRoleService::class)->setupDefaultRoles($tenant);

            return compact('user', 'tenant');
        });

        $request->session()->forget('google_profile');

        event(new Registered($result['user']));

        Auth::login($result['user']);
        $result['user']->setCurrentTenant($result['tenant']);

        return redirect()->to(
            app(TenantUrlHelper::class)->tenantUrl($result['tenant'], '/dashboard')
        );
    }

    private function loginUser(User $user, Request $request): RedirectResponse
    {
        $request->session()->regenerate();
        Auth::login($user);
        $user->purgeOtherSessions();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $tenants = $user->tenants()->where('is_active', true)->whereNull('suspended_at')->get();

        if ($tenants->count() === 1) {
            $tenant = $tenants->first();
            $user->setCurrentTenant($tenant);
            return redirect()->to(app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard'));
        }

        if ($tenants->count() > 1) {
            return redirect()->route('tenant.select');
        }

        return redirect()->route('dashboard.no-tenant');
    }
}
