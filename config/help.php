<?php

/*
|--------------------------------------------------------------------------
| Help centre content
|--------------------------------------------------------------------------
|
| All help content lives here as data, and HelpService filters it for the
| person reading. Nothing is hardcoded in a template: the page, the contents
| list, the search index, the PDF and the tour registry all read the same
| filtered result, so they cannot drift apart.
|
| Filtering is recursive. A `permission` may sit on a topic, on one block
| inside a topic, on a single FAQ entry, or on a tour. Someone who can view
| tickets but not create them reads the viewing section and never sees the
| creating section — gating whole topics is the obvious half and the useless
| half.
|
| permission: null (everyone) | 'view tickets' | ['any', 'of', 'these']
|
| Block DSL — each variant may carry its own `permission`:
|   ['p'      => 'paragraph']
|   ['h'      => 'sub-heading']
|   ['steps'  => ['do this', 'then this'], 'minutes' => 3]
|   ['ul'     => ['bullet']]
|   ['tip'|'note'|'warn' => 'callout']
|   ['faq'    => [['q' => '…', 'a' => '…', 'permission' => '…']]]
|
| Resist adding block types: each is a branch in the page renderer AND the
| PDF renderer AND searchTextFor().
|
*/

return [

    'groups' => [
        'basics' => ['label' => 'Getting around', 'icon' => 'rocket-launch'],
        'work' => ['label' => 'Daily work', 'icon' => 'ticket'],
        'people' => ['label' => 'People & clients', 'icon' => 'users'],
        'service' => ['label' => 'Service levels', 'icon' => 'clock'],
        'insight' => ['label' => 'Reporting', 'icon' => 'chart-bar'],
        'admin' => ['label' => 'Administration', 'icon' => 'cog'],
    ],

    'topics' => [

        /* ---------------------------------------------------------------- */

        'getting-started' => [
            'group' => 'basics',
            'title' => 'Getting Started',
            'icon' => 'rocket-launch',
            'permission' => null,
            'minutes' => 3,
            'keywords' => 'login sidebar workspace navigate profile switch tenant onboarding',
            'summary' => 'Find your way around, and what the sidebar shows you.',
            'blocks' => [
                ['p' => 'This is a shared helpdesk: every ticket, client and report you see belongs to the workspace named at the top of the sidebar. Switching workspace switches all of it.'],
                ['steps' => [
                    'Use the <strong>left sidebar</strong> to move between areas. What appears there depends on your role and on the workspace plan — a menu you cannot see is a permission you do not hold, not a missing feature.',
                    'Your name and profile menu are at the <strong>top right</strong>.',
                    'The <strong>question-mark icon</strong> in the header opens the guided tutorials from any page.',
                ], 'minutes' => 2],
                ['note' => 'This guide is personalised. You are only shown topics your role can actually reach, so nothing here points at a button you do not have.'],
                ['h' => 'Guided tutorials'],
                ['p' => 'A guided tutorial walks you through the real screen: the page dims, the next thing to click is spotlighted, and a pointer shows you where to go. Start one any time — there is nothing to reset, and stopping halfway changes nothing.'],
                ['tip' => 'A tutorial follows you across pages. When a step asks you to click something, click it for real — the tutorial picks up on the next screen.'],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'managing-tickets' => [
            'group' => 'work',
            'title' => 'Managing Tickets',
            'icon' => 'ticket',
            'permission' => 'view tickets',
            'minutes' => 8,
            'keywords' => 'ticket queue status priority assign merge duplicate reopen close hold escalate attachment',
            'summary' => 'The ticket lifecycle, and the moves the system will refuse.',
            'blocks' => [
                ['p' => 'Tickets arrive from the public portal, the REST API, or an agent creating one here.'],

                ['h' => 'Statuses'],
                ['p' => 'There are six: <strong>open</strong>, <strong>assigned</strong>, <strong>in progress</strong>, <strong>on hold</strong>, <strong>closed</strong> and <strong>cancelled</strong>. There is deliberately no separate "resolved" — resolving and closing are the same event.'],
                ['warn' => 'Not every move between statuses is allowed. A closed ticket can only be reopened; it cannot jump straight back to in progress. The system refuses invalid moves rather than recording them.'],

                ['h' => 'Priority', 'permission' => 'update tickets'],
                ['p' => 'Low, medium, high or critical. Priority is not a label — together with the client\'s tier it selects the SLA policy, and therefore the deadline.', 'permission' => 'update tickets'],
                ['note' => 'Changing priority recalculates the deadline from the ticket\'s creation time. It does not restart the clock.', 'permission' => 'update tickets'],

                ['h' => 'Creating a ticket', 'permission' => 'create tickets'],
                ['steps' => [
                    'Pick the <strong>client</strong> — their tier is half of what decides the SLA target.',
                    'Set the <strong>priority</strong>. On workspaces with SLA management, a priority with no matching policy is refused rather than saved without a deadline.',
                    'Optionally <strong>assign</strong> an agent. Assignment can be refused if the agent is below the ticket\'s tier or outside their working hours.',
                    'Save. A double-click is absorbed, so you will not create the same ticket twice.',
                ], 'minutes' => 3, 'permission' => 'create tickets'],

                ['h' => 'Assigning', 'permission' => 'assign tickets'],
                ['p' => 'Claiming an unassigned ticket is first-come-first-served: if someone claims it while you are looking at it, your claim is refused rather than silently overwriting theirs. A manager reassigning a ticket overrides that deliberately.', 'permission' => 'assign tickets'],

                ['h' => 'Putting a ticket on hold', 'permission' => 'update tickets'],
                ['p' => 'Use <strong>on hold</strong> when you are waiting on someone else. Time on hold is not counted against you: when the hold ends, the SLA deadline moves out by however long it lasted.', 'permission' => 'update tickets'],

                ['h' => 'Merging duplicates', 'permission' => 'merge tickets'],
                ['steps' => [
                    'Open the ticket you want to <em>close</em>, and merge it into the one you are keeping.',
                    'Tasks and comments move to the ticket you keep, and the merged one is closed.',
                    'Unmerging moves exactly those rows back and restores the status the ticket had before.',
                ], 'minutes' => 2, 'permission' => 'merge tickets'],
                ['warn' => 'A ticket that has already been merged cannot be a merge target — that would build a chain the surviving ticket cannot show you. Merge into the ticket at the top instead.', 'permission' => 'merge tickets'],

                ['h' => 'Reopening', 'permission' => 'reopen tickets'],
                ['p' => 'Only a closed or cancelled ticket can be reopened. Reopening clears the closure timestamp and counts a reopen, so the reports stay honest.', 'permission' => 'reopen tickets'],

                ['faq' => [
                    ['q' => 'Why can I see fewer tickets than a colleague?', 'a' => 'Ticket visibility follows your departments. Without the "view all tickets" permission you see your departments\' tickets plus anything assigned to you or raised by you.'],
                    ['q' => 'Why was my assignment rejected?', 'a' => 'Either the agent\'s support tier is below the ticket\'s priority, or they are outside their configured working hours. Both are only enforced on plans that include those features.', 'permission' => 'assign tickets'],
                    ['q' => 'I closed a ticket by mistake.', 'a' => 'Reopen it — the original closure stays in the history, and the reopen is recorded with your reason.', 'permission' => 'reopen tickets'],
                ]],
            ],
            'tour' => [
                'title' => 'Raise a ticket and route it correctly',
                'summary' => 'Where the SLA clock comes from, and why an assignee can be refused.',
                'minutes' => 3,
                'permission' => 'create tickets',
                'steps' => [
                    [
                        'route' => 'tickets.index',
                        'selector' => '[data-tour="ticket-create"]',
                        'title' => 'Start here',
                        'body' => 'Opens the ticket form. Everything that decides the SLA deadline is set on that one screen.',
                        'advance' => 'click',
                        'side' => 'bottom',
                    ],
                    [
                        'route' => 'tickets.create',
                        'selector' => '[data-tour="ticket-client"]',
                        'title' => 'The client sets the tier',
                        'body' => 'SLA targets are chosen per client tier, not per client. Picking the wrong company gives the ticket the wrong deadline, and nothing on the form will tell you.',
                        'advance' => 'next',
                        'side' => 'bottom',
                    ],
                    [
                        'route' => 'tickets.create',
                        'selector' => '[data-tour="ticket-priority"]',
                        'title' => 'Priority starts the clock',
                        'body' => 'The deadline is the creation time plus the target for this priority and tier. Raise the priority later and the deadline is recomputed from creation — it does not restart.',
                        'advance' => 'next',
                        'side' => 'bottom',
                    ],
                    [
                        'route' => 'tickets.create',
                        'selector' => '[data-tour="ticket-assignee"]',
                        'title' => 'Assignment can be refused',
                        'body' => 'An agent below the ticket\'s tier, or outside their working hours, is rejected on save. Leave it unassigned if you are not sure — it reaches the queue either way.',
                        'advance' => 'next',
                        'side' => 'top',
                    ],
                    [
                        'route' => 'tickets.create',
                        'selector' => '[data-tour="ticket-submit"]',
                        'title' => 'One click is enough',
                        'body' => 'A double-click is absorbed for ten seconds, so you will not end up with two tickets. An identical report filed later is treated as a genuinely separate ticket.',
                        'advance' => 'next',
                        'side' => 'top',
                    ],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'client-management' => [
            'group' => 'people',
            'title' => 'Client Management',
            'icon' => 'users',
            'permission' => 'view clients',
            'minutes' => 4,
            'keywords' => 'client company tier portal contact autofill qr track submit',
            'summary' => 'Client records, tiers, and the public portal they use.',
            'blocks' => [
                ['p' => 'A client is the company raising tickets. Their <strong>tier</strong> — basic, premium or enterprise — is one of the two things that decide an SLA target, so it is a commercial setting, not a label.'],
                ['h' => 'Adding a client', 'permission' => 'create clients'],
                ['steps' => [
                    'Give the company name, contact and email.',
                    'Set the tier to match what was sold to them.',
                ], 'minutes' => 2, 'permission' => 'create clients'],
                ['h' => 'The public portal'],
                ['p' => 'On Business plans and above, clients get a public page under your workspace address where they can submit a ticket and track it by its number and email. Each client also has a prefilled submit link, which you can hand out as a QR code.'],
                ['warn' => 'Those prefilled links are not signed — anyone can craft one. Treat what arrives through them as customer-supplied, the same as any other portal submission.'],
                ['faq' => [
                    ['q' => 'Why does changing a tier not move existing deadlines?', 'a' => 'The SLA is chosen when the ticket is created or when its priority changes. Existing tickets keep the target they were given.'],
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'sla-management' => [
            'group' => 'service',
            'title' => 'SLA Management',
            'icon' => 'clock',
            'permission' => 'view sla policies',
            'feature' => 'sla_management',
            'minutes' => 6,
            'keywords' => 'sla policy response time resolution time first response breach target tier hold deadline compliance overdue',
            'summary' => 'How a deadline is chosen, and what stops the clock.',
            'blocks' => [
                ['p' => 'An SLA policy sets two targets: the <strong>response time</strong>, meaning how long until an agent first replies, and the <strong>resolution time</strong>, meaning how long until the ticket is closed. A ticket is matched to a policy by <strong>client tier and priority</strong>.'],
                ['h' => 'How a policy is matched'],
                ['ul' => [
                    'A policy naming both a tier and a priority wins first.',
                    'Then one naming only a priority, then only a tier.',
                    'A catch-all policy — no tier, no priority — matches anything left.',
                ]],
                ['note' => 'Without a matching policy, a workspace with SLA management will refuse to set a priority rather than create a ticket with no deadline. If that happens, seed the defaults or add a catch-all.'],
                ['h' => 'What the clock does'],
                ['ul' => [
                    'It starts when the ticket is created, not when someone picks it up.',
                    'Changing priority recomputes the deadline from the creation time.',
                    'Time <strong>on hold</strong> pushes the deadline out by the same amount, so waiting on a customer does not count against you.',
                ]],
                ['h' => 'Editing targets', 'permission' => ['create sla policies', 'update sla policies']],
                ['steps' => [
                    'Policies are managed per client tier, not per individual policy.',
                    'Pick the tier, then set the response and resolution hours for each priority.',
                    'Seed the industry defaults first if you are starting from nothing.',
                ], 'minutes' => 3, 'permission' => ['create sla policies', 'update sla policies']],
                ['faq' => [
                    ['q' => 'A ticket shows as overdue but the SLA report says it met its target.', 'a' => 'That should no longer happen: the deadline itself is extended when a hold ends, so the dashboard and the report now apply the same rule. If you still see it, the ticket is worth reporting.'],
                    ['q' => 'Does the SLA pause outside working hours?', 'a' => 'No. Targets are plain elapsed hours — a 24-hour target set on Friday evening expires on Saturday evening. Set targets that reflect that.'],
                ]],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'reports-analytics' => [
            'group' => 'insight',
            'title' => 'Reports & Analytics',
            'icon' => 'chart-bar',
            'permission' => 'view reports',
            'minutes' => 4,
            'keywords' => 'report dashboard export csv agent performance compliance trend timezone',
            'summary' => 'What the numbers count, and the timezone they count it in.',
            'blocks' => [
                ['p' => 'Reports cover ticket volume, resolution times, agent performance and SLA compliance.'],
                ['h' => 'What is counted'],
                ['ul' => [
                    'Merged and spam tickets are excluded, so totals will not match a raw row count.',
                    'Resolution time excludes time on hold. Response time does not — it is measured plainly from creation to first reply.',
                    'A reassigned ticket is credited to whoever holds it now, not to everyone who touched it.',
                ]],
                ['note' => 'Dates are grouped in your workspace timezone, so "today" means your today rather than the server\'s.'],
                ['h' => 'Exporting', 'permission' => 'export reports'],
                ['p' => 'Reports export to CSV for anything you would rather do in a spreadsheet.', 'permission' => 'export reports'],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'ai-assistant' => [
            'group' => 'work',
            'title' => 'AI Assistant',
            'icon' => 'sparkles',
            'permission' => 'use ai assistant',
            'feature' => 'ai_chatbot',
            'minutes' => 3,
            'keywords' => 'ai assistant chatbot copilot draft summarise chart ask',
            'summary' => 'What the assistant can reach, and what it will not do.',
            'blocks' => [
                ['p' => 'The assistant answers questions about this workspace — your tickets, your clients, your numbers. It reads only what you can read: it inherits your permissions rather than bypassing them.'],
                ['ul' => [
                    'Ask for figures and it will draw a chart.',
                    'It can draft a reply or summarise a long ticket for you to edit.',
                    'It can look up past resolutions to similar tickets.',
                ]],
                ['note' => 'It is off unless your workspace has switched it on, and it is only available on Enterprise plans.'],
                ['warn' => 'It drafts; you send. Nothing it writes reaches a customer until you post it.'],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'settings-configuration' => [
            'group' => 'admin',
            'title' => 'Settings & Configuration',
            'icon' => 'cog',
            'permission' => 'view settings',
            'minutes' => 5,
            'keywords' => 'settings branding email smtp notification api token timezone workspace',
            'summary' => 'Workspace settings, and the two that surprise people.',
            'blocks' => [
                ['p' => 'Settings are per workspace. Nothing here affects another workspace.'],
                ['h' => 'Timezone'],
                ['p' => 'Set this first. Every report groups by day in this timezone, and every timestamp is displayed in it.'],
                ['h' => 'Email', 'permission' => 'manage email settings'],
                ['p' => 'You can send notifications through your own SMTP server so mail arrives from your domain. Leave it blank and the application defaults are used.', 'permission' => 'manage email settings'],
                ['h' => 'Branding', 'permission' => 'manage branding'],
                ['p' => 'Your logo and colours appear on the public portal and on generated documents.', 'permission' => 'manage branding'],
                ['h' => 'API tokens', 'permission' => 'manage api tokens'],
                ['warn' => 'A token is shown once, when you create it. It cannot be recovered afterwards — if it is lost, revoke it and issue another.', 'permission' => 'manage api tokens'],
            ],
        ],

    ],

];
