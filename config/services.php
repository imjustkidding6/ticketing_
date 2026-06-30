<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5'),
        // Reasoning models (gpt-5 family, o-series) use `max_completion_tokens` and a
        // `reasoning_effort` knob ('minimal'|'low'|'medium'|'high'). Lower = faster/cheaper.
        // Ignored by non-reasoning models (gpt-4.1, gpt-5-chat-latest).
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        // Token budget for a single completion. Reasoning models spend hidden reasoning
        // tokens against this, so it must be generous or replies can come back empty.
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 6000),
        // Model used for the live web-search tool (OpenAI's built-in web search).
        'search_model' => env('OPENAI_SEARCH_MODEL', 'gpt-4o-mini-search-preview'),
        // Embedding model used to learn from resolved tickets (semantic search).
        'embed_model' => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    // GitHub bridge for the "AI Programmer": the app files an issue (label ai-fix)
    // that the Claude Code GitHub Action turns into a fix PR; a webhook reports back.
    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'repo' => env('GITHUB_REPO'),               // e.g. "CliqueHA-Information-Services/ticketing"
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
    ],

];
