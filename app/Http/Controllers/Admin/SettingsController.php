<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'adminTimezone' => SystemSetting::get('admin_timezone', 'UTC'),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_timezone' => ['required', 'string', 'timezone'],
        ]);

        SystemSetting::set('admin_timezone', $validated['admin_timezone']);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Admin settings saved.');
    }
}
