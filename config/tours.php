<?php

/*
|--------------------------------------------------------------------------
| Guided tours
|--------------------------------------------------------------------------
|
| A tour is data. It lives here rather than in the database because steps
| reference CSS selectors and permissions, and both deserve code review — a
| renamed anchor should show up in a diff, not as a silently dead tour.
|
| Tour:
|   key         stable identifier, used to resume across page loads
|   title       shown in the launcher
|   summary     one line, shown before the user commits
|   minutes     honest estimate of the TOUR, not the task once learned
|   permission  the permission for the action being TAUGHT, not for the page.
|               Read access to the ticket list is not permission to be taught
//               how to create one, so these name the write permission.
|   feature     optional PlanFeature value; the tour is hidden without it
|   steps[]
|
| Step:
|   page        route name this step lives on (supports a trailing * wildcard)
|   selector    a data-tour anchor, and nothing else — see TourAnchorTest
|   title       short
|   body        one idea. Name the trap: the thing that is expensive to undo.
|   advance     'next'  the step only explains what is on screen
|               'click' the step CAUSES something (navigation, modal, tab);
|                       the runner waits for a real click on the target
|   side        preferred panel placement: top|bottom|left|right
|
*/

return [

    'create-ticket' => [
        'key' => 'create-ticket',
        'title' => 'Raise a ticket and route it correctly',
        'summary' => 'Where the SLA clock comes from, and why the assignee can be refused.',
        'minutes' => 3,
        'permission' => 'create tickets',
        'steps' => [
            [
                'page' => 'tickets.index',
                'selector' => '[data-tour="ticket-create"]',
                'title' => 'Start here',
                'body' => 'Opens the ticket form. Everything that decides the SLA deadline is set on that one screen.',
                'advance' => 'click',
                'side' => 'bottom',
            ],
            [
                'page' => 'tickets.create',
                'selector' => '[data-tour="ticket-client"]',
                'title' => 'The client sets the tier',
                'body' => 'SLA targets are chosen per client tier, not per client. Picking the wrong company here gives the ticket the wrong deadline, and nothing on the form will tell you.',
                'advance' => 'next',
                'side' => 'bottom',
            ],
            [
                'page' => 'tickets.create',
                'selector' => '[data-tour="ticket-priority"]',
                'title' => 'Priority starts the clock',
                'body' => 'The deadline is the creation time plus the target for this priority and tier. Raise the priority later and the deadline is recomputed from creation — it does not restart.',
                'advance' => 'next',
                'side' => 'bottom',
            ],
            [
                'page' => 'tickets.create',
                'selector' => '[data-tour="ticket-assignee"]',
                'title' => 'Assignment can be refused',
                'body' => 'On Enterprise plans an agent below the ticket\'s tier, or outside their working hours, is rejected on save. Leave it unassigned if you are not sure — it goes to the queue either way.',
                'advance' => 'next',
                'side' => 'top',
            ],
            [
                'page' => 'tickets.create',
                'selector' => '[data-tour="ticket-submit"]',
                'title' => 'One click is enough',
                'body' => 'A double-click is absorbed for ten seconds, so you will not end up with two tickets. An identical report filed later is treated as a genuinely separate ticket.',
                'advance' => 'next',
                'side' => 'top',
            ],
        ],
    ],

    'merge-duplicates' => [
        'key' => 'merge-duplicates',
        'title' => 'Merge duplicate reports',
        'summary' => 'What merging moves, and what unmerging can put back.',
        'minutes' => 2,
        'permission' => 'merge tickets',
        'feature' => 'ticket_merging',
        'steps' => [
            [
                'page' => 'tickets.show',
                'selector' => '[data-tour="ticket-merge"]',
                'title' => 'Merge into the ticket you are keeping',
                'body' => 'Open this on the ticket you want to close — the one you pick here is the one that survives.',
                'advance' => 'next',
                'side' => 'top',
            ],
            [
                'page' => 'tickets.show',
                'selector' => '[data-tour="ticket-merge-target"]',
                'title' => 'Pick the ticket at the top of the chain',
                'body' => 'A ticket that has already been merged cannot be a target — merging into one would build a chain the surviving ticket cannot show. The list here excludes them.',
                'advance' => 'next',
                'side' => 'bottom',
            ],
            [
                'page' => 'tickets.show',
                'selector' => '[data-tour="ticket-history"]',
                'title' => 'The merge is reversible, but not free',
                'body' => 'Merging moves this ticket\'s tasks and comments onto the target and closes it. Unmerging moves exactly those rows back and restores the status it had — anything added while merged stays on the target.',
                'advance' => 'next',
                'side' => 'top',
            ],
        ],
    ],

];
