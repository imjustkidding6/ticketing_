<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatbotSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChatbotSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = SystemSetting::getByGroup('chatbot');

        return view('admin.chatbot-settings', compact('settings'));
    }

    public function update(ChatbotSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SystemSetting::set('chatbot_provider', $validated['chatbot_provider'] ?? 'gemini', 'string', 'chatbot');
        SystemSetting::set('chatbot_model', $validated['chatbot_model'] ?? 'gemini-1.5-flash', 'string', 'chatbot');
        SystemSetting::set('chatbot_system_prompt', $validated['chatbot_system_prompt'] ?? '', 'string', 'chatbot');
        SystemSetting::set('chatbot_confidence_threshold', $validated['chatbot_confidence_threshold'] ?? '0.6', 'string', 'chatbot');

        if (! empty($validated['chatbot_api_key'])) {
            SystemSetting::set('chatbot_api_key', $validated['chatbot_api_key'], 'encrypted', 'chatbot');
        }

        return redirect()->route('admin.chatbot-settings.edit')
            ->with('success', 'Chatbot settings saved.');
    }
}
