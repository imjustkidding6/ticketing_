<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minutes saved per accepted suggestion
    |--------------------------------------------------------------------------
    |
    | An ESTIMATE, not a measurement, and deliberately so. The pilot (§07 of the
    | management proposal) asks for a time-saved figure; the only clock available
    | is created_at → decided_at, which measures how long the engineer deliberated
    | and would credit an hour to anyone who left the tab open. An honest constant
    | the pilot can revise beats a dishonest measurement.
    |
    | The value in force is copied onto the verdict row at decision time, so
    | revising a figure here does not rewrite history.
    |
    */

    'minutes_saved' => [
        'summary' => 3,
        'category' => 1,
        'priority' => 1,
        'checks' => 5,
        'engineer' => 2,
        'context' => 4,
        /*
         * P6b. The two highest-value suggestions on a publicly submitted ticket: the
         * engineer would otherwise retitle and restructure a customer's prose by hand.
         * Without entries here `(int) config(…, 0)` silently banks zero.
         */
        'subject' => 1,
        'description' => 4,
    ],

    'analyze' => [
        /*
         * Requested output ceiling for the single strict-JSON chat call. On a
         * reasoning model OpenAiService::chat() floors max_completion_tokens at
         * OPENAI_MAX_OUTPUT_TOKENS anyway and hidden reasoning tokens eat into it,
         * so truncation is a real failure mode — it is handled as `failed`, never
         * as a partial render.
         *
         * Raised from 1200 for P6b: one ceiling now carries the summary, the checks,
         * three rationales AND a full polished description. Confirmed to sit under
         * OPENAI_MAX_OUTPUT_TOKENS (default 6000), which chat() floors reasoning models
         * at and whose hidden tokens also count against it.
         */
        'max_tokens' => 2200,

        /* How many resolved tickets the embedding search may retrieve. */
        'context_limit' => 5,

        /* Hard cap on the ticket text sent to OpenAI. */
        'input_chars' => 8000,

        /* Hard cap on the returned summary. */
        'summary_chars' => 1200,

        /*
         * Hard cap on the polished subject: the WIDTH OF `tickets.subject`. A longer
         * suggestion would 422 the apply after the verdict row was already written.
         */
        'polished_subject_chars' => 255,

        /*
         * Hard cap on the polished description: StoreSuggestionVerdictRequest's
         * `applied_value` ceiling, so the two limits agree by construction and the
         * verdict endpoint can never 422 on a value the panel itself offered.
         */
        'polished_description_chars' => 8000,

        /*
         * Automatic analyses per workspace per TENANT-LOCAL day. Counted from
         * ai_ticket_analyses (the billing record itself), never a cache counter that
         * can disagree with it. The cap protects unattended spend only: an engineer
         * pressing Analyze is never gated by it.
         */
        'daily_cap' => 100,
    ],

];
