<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    /**
     * Display general settings.
     */
    public function general(): View
    {
        $settings = AppSetting::getByGroup('general');

        return view('settings.general', compact('settings'));
    }

    /**
     * Save general settings.
     */
    public function saveGeneral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'date_format' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::set($key, $value, 'string', 'general');
        }

        return redirect()->route('settings.general')
            ->with('success', 'General settings saved.');
    }

    /**
     * Display ticket settings.
     */
    public function ticket(): View
    {
        $settings = AppSetting::getByGroup('ticket');

        return view('settings.ticket', compact('settings'));
    }

    /**
     * Save ticket settings.
     */
    public function saveTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_priority' => ['nullable', 'in:low,medium,high,critical'],
            'auto_assignment' => ['nullable', 'string'],
            'ticket_prefix' => ['nullable', 'string', 'max:10'],
        ]);

        AppSetting::set('default_priority', $validated['default_priority'] ?? 'medium', 'string', 'ticket');
        AppSetting::set('auto_assignment', isset($validated['auto_assignment']) ? '1' : '0', 'boolean', 'ticket');
        AppSetting::set('ticket_prefix', $validated['ticket_prefix'] ?? 'TKT', 'string', 'ticket');

        return redirect()->route('settings.ticket')
            ->with('success', 'Ticket settings saved.');
    }

    /**
     * Display notification settings (Business+).
     */
    public function notifications(): View
    {
        $settings = AppSetting::getByGroup('notifications');

        return view('settings.notifications', compact('settings'));
    }

    /**
     * Save notification settings.
     */
    public function saveNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_on_ticket_create' => ['nullable', 'string'],
            'notify_on_ticket_assign' => ['nullable', 'string'],
            'notify_on_ticket_close' => ['nullable', 'string'],
            'notify_on_comment' => ['nullable', 'string'],
        ]);

        foreach (['notify_on_ticket_create', 'notify_on_ticket_assign', 'notify_on_ticket_close', 'notify_on_comment'] as $key) {
            AppSetting::set($key, isset($validated[$key]) ? '1' : '0', 'boolean', 'notifications');
        }

        return redirect()->route('settings.notifications')
            ->with('success', 'Notification settings saved.');
    }

    /**
     * Display branding settings.
     */
    public function branding(): View
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        return view('settings.branding', compact('tenant'));
    }

    /**
     * Save branding settings.
     */
    public function saveBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dark_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dark_accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'remove_logo' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        if ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }

            $tenant->logo_path = $request->file('logo')->store('tenant-logos', 'public');
        }

        if (isset($validated['remove_logo']) && $tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->logo_path = null;
        }

        $tenant->primary_color = ($validated['primary_color'] ?? null) ?: null;
        $tenant->accent_color = ($validated['accent_color'] ?? null) ?: null;
        $tenant->dark_primary_color = ($validated['dark_primary_color'] ?? null) ?: null;
        $tenant->dark_accent_color = ($validated['dark_accent_color'] ?? null) ?: null;
        $tenant->save();

        return redirect()->route('settings.branding')
            ->with('success', 'Branding settings saved.');
    }
}
