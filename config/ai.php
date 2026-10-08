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
        /*
         * A2-1. Writing up a resolution twice — once for the customer, once for the
         * record — is the documentation time §04 of the proposal costs. The internal
         * note is the cheaper of the two because its shape is fixed.
         */
        'client_resolution' => 6,
        'internal_note' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | The create-form draft polish (P6c)
    |--------------------------------------------------------------------------
    |
    | The same one strict-JSON call that has always polished the draft, now also
    | carrying a suggested priority. 800 is the ceiling this path has always used;
    | it is here so it is tunable without a code change.
    |
    */

    'draft' => [
        'max_tokens' => 800,

        /*
         * How long the suggestion payload waits in the cache for the engineer to
         * submit the form. Long enough to write a ticket, short enough that an
         * abandoned draft evaporates on its own — there is nothing to prune.
         */
        'token_ttl_minutes' => 120,

        /* Hard cap on the draft text sent to OpenAI. */
        'input_chars' => 8000,
    ],

    /*
    |--------------------------------------------------------------------------
    | GENERATE RESOLUTION (A2-1)
    |--------------------------------------------------------------------------
    |
    | One chat call carrying both prose drafts. No embedding call: a resolution is
    | written from what THIS engineer did, and retrieved text from another ticket
    | is exactly the invented-fact risk this output can least afford.
    |
    */

    'resolution' => [
        /*
         * One output ceiling for both drafts. On a reasoning model chat() floors
         * max_completion_tokens at OPENAI_MAX_OUTPUT_TOKENS and hidden reasoning
         * tokens count against it, so truncation is a real failure mode — handled
         * as `failed` with NO partial render, never half a draft.
         */
        'max_tokens' => 1500,

        /* Request cap on the engineer's notes; a 422 with a field error, not a truncation. */
        'notes_chars' => 4000,

        /*
         * Below this the endpoint refuses before any HTTP and writes NO analysis row:
         * a three-word note produces invented facts, which is the one failure mode a
         * resolution record must not have. A refusal to even try is not a run.
         */
        'notes_min_chars' => 40,

        /* Hard cap on the whole assembled prompt input (notes + ticket text + work done). */
        'input_chars' => 8000,

        /*
         * 2,000 IS `closing_remarks`' own `max:2000` (TicketController::changeStatus).
         * The limits agree by construction, so a copy the panel offered can never 422
         * at the form that receives it.
         */
        'client_chars' => 2000,

        /* Under the comment box's max:5000 and under applied_value's max:8000. */
        'internal_chars' => 2500,
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
