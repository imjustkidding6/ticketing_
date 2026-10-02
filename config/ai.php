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
    ],

    'analyze' => [
        /*
         * Requested output ceiling for the single strict-JSON chat call. On a
         * reasoning model OpenAiService::chat() floors max_completion_tokens at
         * OPENAI_MAX_OUTPUT_TOKENS anyway and hidden reasoning tokens eat into it,
         * so truncation is a real failure mode — it is handled as `failed`, never
         * as a partial render.
         */
        'max_tokens' => 1200,

        /* How many resolved tickets the embedding search may retrieve. */
        'context_limit' => 5,

        /* Hard cap on the ticket text sent to OpenAI. */
        'input_chars' => 8000,

        /* Hard cap on the returned summary. */
        'summary_chars' => 1200,
    ],

];
