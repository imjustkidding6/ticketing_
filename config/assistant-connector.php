<?php

use App\Assistant\AddComment;
use App\Assistant\CreateTicket;
use App\Assistant\FindTickets;
use App\Assistant\GetTicket;
use App\Assistant\ListClients;
use App\Assistant\SetTenantContext;
use App\Assistant\TicketSummary;
use App\Assistant\UpdateStatus;

return [

    'prefix' => 'api/assistant',

    'middleware' => ['assistant.token'],

    'user_model' => \App\Models\User::class,

    // The actions this helpdesk exposes to the Jude Hub.
    'tools' => [
        FindTickets::class,
        GetTicket::class,
        TicketSummary::class,
        ListClients::class,
        CreateTicket::class,
        UpdateStatus::class,
        AddComment::class,
    ],

    // Multi-tenant: the token API has no session, so set the active tenant from
    // the user (their current/first workspace) — TenantScope then scopes + stamps.
    'context_resolver' => SetTenantContext::class,

];
