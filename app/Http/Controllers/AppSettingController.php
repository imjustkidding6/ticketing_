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
        $this->checkPermission('manage settings');

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
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_website' => ['nullable', 'url', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'date_format' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($validated as $key => $value) {
            AppSetting::set($key, $value ?? '', 'string', 'general');
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
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'admin_notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        foreach (['notify_on_ticket_create', 'notify_on_ticket_assign', 'notify_on_ticket_close', 'notify_on_comment'] as $key) {
            AppSetting::set($key, isset($validated[$key]) ? '1' : '0', 'boolean', 'notifications');
        }

        // SMTP settings
        foreach (['mail_host', 'mail_port', 'mail_from_address', 'mail_from_name', 'mail_username', 'mail_encryption', 'admin_notification_email'] as $key) {
            if (array_key_exists($key, $validated)) {
                AppSetting::set($key, $validated[$key] ?? '', 'string', 'notifications');
            }
        }

        // Store password encrypted if provided
        if (! empty($validated['mail_password'])) {
            AppSetting::set('mail_password', $validated['mail_password'], 'encrypted', 'notifications');
        }

        return redirect()->route('settings.notifications')
            ->with('success', 'Notification settings saved.');
    }

    /**
     * Send a test email using the configured SMTP settings.
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $settings = AppSetting::getByGroup('notifications');
        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        $host = $settings['mail_host'] ?? config('mail.mailers.smtp.host');
        $port = $settings['mail_port'] ?? config('mail.mailers.smtp.port');
        $username = $settings['mail_username'] ?? config('mail.mailers.smtp.username');
        $password = $settings['mail_password'] ?? config('mail.mailers.smtp.password');
        $encryption = $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');
        $fromAddress = $settings['mail_from_address'] ?? config('mail.from.address');
        $fromName = $settings['mail_from_name'] ?? config('mail.from.name');

        if ($encryption === 'none') {
            $encryption = null;
        }

        try {
            config([
                'mail.mailers.tenant_smtp' => [
                    'transport' => 'smtp',
                    'host' => $host,
                    'port' => (int) $port,
                    'username' => $username,
                    'password' => $password,
                    'encryption' => $encryption,
                ],
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName,
            ]);

            $generalSettings = AppSetting::getByGroup('general');
            $companyName = $generalSettings['company_name'] ?? $tenant->name;

            \Illuminate\Support\Facades\Mail::mailer('tenant_smtp')->send([], [], function ($message) use ($validated, $fromAddress, $fromName, $companyName) {
                $message->to($validated['test_email'])
                    ->from($fromAddress, $fromName)
                    ->subject('Test Email from '.$companyName)
                    ->html(
                        '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;">'
                        .'<h2 style="color:#10b981;">Email Configuration Successful!</h2>'
                        .'<p>This is a test email from <strong>'.$companyName.'</strong>.</p>'
                        .'<p>Your SMTP settings are working correctly.</p>'
                        .'<p style="color:#6b7280;font-size:12px;margin-top:20px;">Sent at '.now()->format('M d, Y g:i A').'</p>'
                        .'</div>'
                    );
            });

            return redirect()->route('settings.notifications')
                ->with('success', 'Test email sent successfully to '.$validated['test_email']);
        } catch (\Exception $e) {
            return redirect()->route('settings.notifications')
                ->with('error', 'Failed to send test email: '.$e->getMessage());
        }
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
     * Display service-report settings.
     */
    public function serviceReport(): View
    {
        $this->checkPermission('manage settings');

        $tenant = Tenant::findOrFail(session('current_tenant_id'));
        $settings = AppSetting::getByGroup('service_report');

        return view('settings.service-report', compact('tenant', 'settings'));
    }

    /**
     * Save service-report settings.
     */
    public function saveServiceReport(Request $request): RedirectResponse
    {
        $this->checkPermission('manage settings');

        $validated = $request->validate([
            'auto_generate_on_close' => ['nullable', 'boolean'],
            'report_title' => ['nullable', 'string', 'max:255'],
            'report_footer' => ['nullable', 'string', 'max:500'],
            'show_sla_metrics' => ['nullable', 'boolean'],
            'show_tasks' => ['nullable', 'boolean'],
            'service_report_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'remove_service_report_logo' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        if ($request->hasFile('service_report_logo')) {
            if ($tenant->service_report_logo_path) {
                Storage::disk('public')->delete($tenant->service_report_logo_path);
            }
            $tenant->service_report_logo_path = $request->file('service_report_logo')->store('tenant-logos', 'public');
            $tenant->save();
        }

        if (! empty($validated['remove_service_report_logo']) && $tenant->service_report_logo_path) {
            Storage::disk('public')->delete($tenant->service_report_logo_path);
            $tenant->service_report_logo_path = null;
            $tenant->save();
        }

        AppSetting::set('auto_generate_on_close', $request->boolean('auto_generate_on_close') ? '1' : '0', 'boolean', 'service_report');
        AppSetting::set('show_sla_metrics', $request->boolean('show_sla_metrics') ? '1' : '0', 'boolean', 'service_report');
        AppSetting::set('show_tasks', $request->boolean('show_tasks') ? '1' : '0', 'boolean', 'service_report');
        AppSetting::set('report_title', $validated['report_title'] ?? '', 'string', 'service_report');
        AppSetting::set('report_footer', $validated['report_footer'] ?? '', 'string', 'service_report');

        return redirect()->route('settings.service-report')
            ->with('success', 'Service report settings saved.');
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
