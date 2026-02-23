<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'license_key' => ['required', 'string', 'size:24'],
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $licenseKey = strtoupper($request->license_key);

        $license = License::where('license_key', $licenseKey)->first();

        if (! $license) {
            return back()->withErrors(['license_key' => 'Invalid license key.'])->withInput();
        }

        if ($license->status !== License::STATUS_PENDING) {
            return back()->withErrors(['license_key' => 'This license key has already been activated or is no longer valid.'])->withInput();
        }

        if ($license->isFullyExpired()) {
            return back()->withErrors(['license_key' => 'This license key has expired.'])->withInput();
        }

        $user = DB::transaction(function () use ($request, $license) {
            $tenant = Tenant::create([
                'name' => $request->company_name,
            ]);

            $license->activate($tenant);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $tenant->addUser($user, 'owner');

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
