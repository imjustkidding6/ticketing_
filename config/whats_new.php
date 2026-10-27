<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What's New
    |--------------------------------------------------------------------------
    |
    | The floating release note. It is CONTENT, not markup — the same principle
    | the help centre follows, so a release can be announced by editing this file
    | and nothing else.
    |
    | `release` is the dismissal key. A viewer who dismisses the pill will not see
    | it again until this string changes, so bumping it is what re-shows the pill
    | for everyone. Use a date; it reads as a version in the one place a support
    | engineer will have to compare it against, the browser's local storage.
    |
    | Dismissal is per browser, not per user: it lives in localStorage, because a
    | release note is a convenience and not something worth a column, a migration
    | and a write on every page view. The cost is that a viewer who dismisses it
    | on their laptop sees it again on their phone, once.
    |
    */

    'enabled' => env('FEATURE_WHATS_NEW', true),

    'release' => '2026-10-27',

    'title' => "What's new",

    'summary' => 'Ticket categories now decide which detail questions a ticket asks.',

    /*
     * Keep this to three or four lines. The pill is an invitation to read the
     * help centre, not a substitute for it — anything that needs a paragraph
     * belongs in config/help.php, where it is permission-scoped and searchable.
     */
    'items' => [
        'Each category chooses whether its tickets ask the technical questions (environment, steps to reproduce, error text).',
        'Each category chooses whether they ask who is affected and whether there is a workaround — the pair that separates high from critical.',
        'These settings used to live on the department. Existing categories keep asking the impact pair and leave the technical questions off until you change them.',
    ],

    /*
     * Where the pill sends the reader. A help-centre TOPIC KEY, resolved to a URL
     * by the view — not a hardcoded path, so a route rename is a test failure
     * rather than a dead link in production. The topic is permission-filtered on
     * arrival like every other, so a reader who may not configure categories gets
     * the help centre rather than a 403.
     */
    'topic' => 'categories',

];
