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
        'catalog' => ['label' => 'What you support', 'icon' => 'tag'],
        'service' => ['label' => 'Service levels', 'icon' => 'clock'],
        'knowledge' => ['label' => 'Knowledge', 'icon' => 'book-open'],
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

            'tour' => [
                'title' => 'Find your way around',
                'summary' => 'What the sidebar shows you, and where help lives.',
                'minutes' => 2,
                'permission' => null,
                'steps' => [
                    ['route' => 'dashboard', 'selector' => '[data-tour="nav-tickets"]', 'title' => 'The sidebar is your whole workspace', 'body' => 'What appears here depends on your role and the workspace plan. A menu you cannot see is a permission you do not hold, not a missing feature — so if a colleague has an item you lack, ask for the permission rather than reporting a bug.', 'advance' => 'click', 'side' => 'right'],
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-filters"]', 'title' => 'Every list filters the same way', 'body' => 'Search plus the dropdowns narrow the list, and the filters stay in the address bar — so a filtered view is something you can bookmark or send to a colleague.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'tickets.index', 'selector' => '[data-tour="help-launcher"]', 'title' => 'Help is on every page', 'body' => 'This opens the guided tutorials from wherever you are. Starting one changes nothing, and stopping halfway leaves nothing behind.', 'advance' => 'next', 'side' => 'bottom'],
                ],
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
                ['p' => 'There are seven: <strong>open</strong>, <strong>assigned</strong>, <strong>in progress</strong>, <strong>on hold</strong>, <strong>pending</strong>, <strong>closed</strong> and <strong>cancelled</strong>. There is deliberately no separate "resolved" — resolving and closing are the same event.'],
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

                ['h' => 'Waiting on the customer', 'permission' => 'update tickets'],
                ['p' => 'Use <strong>pending</strong> when the ball is in the customer\'s court. It pauses the SLA clock exactly as on hold does — the difference is what it tells your reports: how much of the wait was theirs, not yours.', 'permission' => 'update tickets'],
                ['note' => 'A workspace can set pending tickets to close automatically after a number of days in Settings → Tickets. It is off until someone sets it.', 'permission' => 'update tickets'],

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

            'tour' => [
                'title' => 'Add a client and set their tier',
                'summary' => 'The tier is a commercial setting, not a label.',
                'minutes' => 2,
                'permission' => 'create clients',
                'steps' => [
                    ['route' => 'clients.index', 'selector' => '[data-tour="client-create"]', 'title' => 'Start here', 'body' => 'Opens the new client form.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'clients.create', 'selector' => '[data-tour="client-name"]', 'title' => 'Use the name on the contract', 'body' => 'Agents pick the client from this name when raising a ticket. A trading name nobody recognises gets tickets filed against the wrong company.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'clients.create', 'selector' => '[data-tour="client-email"]', 'title' => 'The email is how the portal finds them', 'body' => 'A customer tracking a ticket on the public portal is matched on their ticket number and this address. Get it wrong and they cannot see their own ticket.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'clients.create', 'selector' => '[data-tour="client-tier"]', 'title' => 'The tier decides their deadlines', 'body' => 'Tier plus priority selects the SLA policy. Setting this higher than what was sold commits you to response times you have not agreed to.', 'advance' => 'next', 'side' => 'bottom'],
                ],
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
            'keywords' => 'sla policy response time resolution time first response breach target tier hold deadline compliance overdue calendar hours business hours working hours',
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
                    ['q' => 'Does the SLA pause outside working hours?', 'a' => 'No — targets are measured in <strong>calendar hours</strong>, which is a deliberate choice rather than a limitation. A 24-hour target set on Friday evening expires on Saturday evening, whether or not anyone is rostered. Set targets that reflect the cover you actually run: if you want "two working days", the calendar-hours equivalent is what to type.'],
                    ['q' => 'What is a calendar hour?', 'a' => 'An hour of elapsed clock time, counted continuously from the moment the ticket is created — nights, weekends and public holidays all count. The only thing that stops the clock is putting the ticket <strong>on hold</strong>, which pushes the deadline out by the length of the hold.'],
                ]],
            ],

            'tour' => [
                'title' => 'Set your SLA targets',
                'summary' => 'Seed the defaults, then tune one tier at a time.',
                'minutes' => 3,
                'permission' => ['create sla policies', 'update sla policies'],
                'steps' => [
                    ['route' => 'sla.index', 'selector' => '[data-tour="sla-tiers"]', 'title' => 'One set of targets per client tier', 'body' => 'Every client sits in a tier, and each tier carries its own response and resolution hours for each priority. On an empty workspace, seed the defaults first — a priority with no matching policy cannot be set at all.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'sla.index', 'selector' => '[data-tour="sla-tier"]', 'title' => 'Targets are set per tier', 'body' => 'Each client tier has its own response and resolution hours for each priority. Open one to adjust it.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'sla.edit-tier', 'selector' => '[data-tour="sla-targets"]', 'title' => 'Two clocks, not one', 'body' => 'Response is how long until someone replies; resolution is how long until it is closed. They are measured separately and breach separately. Both are counted in calendar hours — elapsed clock time, including nights, weekends and holidays.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'sla.edit-tier', 'selector' => '[data-tour="sla-save"]', 'title' => 'This changes new tickets only', 'body' => 'Tickets already open keep the deadline they were given. Changing a target here does not retroactively breach or rescue anything.', 'advance' => 'next', 'side' => 'top'],
                ],
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

            'tour' => [
                'title' => 'Read a report without misreading it',
                'summary' => 'What the totals exclude, and whose day they use.',
                'minutes' => 3,
                'permission' => 'view reports',
                'steps' => [
                    ['route' => 'reports.overview', 'selector' => '[data-tour="report-filters"]', 'title' => 'Every report shares these filters', 'body' => 'Dates here are your workspace timezone, not the server\'s — so "today" means your today, and a report you export at midnight still covers the day you meant.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'reports.overview', 'selector' => '[data-tour="report-charts"]', 'title' => 'Totals are not a row count', 'body' => 'Merged and spam tickets are excluded everywhere. Resolution time excludes hold, response time does not. A reassigned ticket is credited to whoever holds it now — so these figures will not match a raw count of the table.', 'advance' => 'next', 'side' => 'top'],
                ],
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

            'tour' => [
                'title' => 'Ask the assistant',
                'summary' => 'What it can reach, and what it will not do.',
                'minutes' => 2,
                'permission' => 'use ai assistant',
                'steps' => [
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ai-launcher"]', 'title' => 'It reads what you can read', 'body' => 'The assistant answers from this workspace only, and inherits your permissions rather than bypassing them — it cannot show you a ticket you could not open yourself.', 'advance' => 'next', 'side' => 'left'],
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-row"]', 'title' => 'It drafts; you send', 'body' => 'On a ticket it can draft a reply or summarise a long thread. Nothing it writes reaches a customer until you post it yourself.', 'advance' => 'next', 'side' => 'bottom'],
                ],
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

            'tour' => [
                'title' => 'Set up a new workspace',
                'summary' => 'The two settings that quietly affect everything else.',
                'minutes' => 3,
                'permission' => 'update settings',
                'steps' => [
                    ['route' => 'settings.general', 'selector' => '[data-tour="setting-company"]', 'title' => 'This name is customer-facing', 'body' => 'It appears on the public portal, on generated service reports and in notification emails — so use the name customers know you by, not an internal one.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'settings.general', 'selector' => '[data-tour="setting-timezone"]', 'title' => 'Set this before you trust a report', 'body' => 'Every report groups by day in this timezone and every timestamp is displayed in it. Changing it later moves which day existing tickets are counted in.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'dashboard' => [
            'group' => 'basics',
            'title' => 'Reading the Dashboard',
            'icon' => 'chart-bar',
            'permission' => null,
            'minutes' => 2,
            'keywords' => 'dashboard overview widget stats overdue queue my tickets activity',
            'summary' => 'What each figure on the front page counts.',
            'blocks' => [
                ['p' => 'The dashboard answers two questions: what needs you now, and how the workspace is doing.'],
                ['ul' => [
                    '<strong>Open</strong> and <strong>in progress</strong> count tickets assigned to you.',
                    '<strong>Overdue</strong> counts tickets past their resolution deadline. Tickets on hold are not counted — their clock is paused.',
                    '<strong>Unassigned</strong> is the queue nobody has picked up yet.',
                ]],
                ['note' => 'Merged and spam tickets are excluded everywhere, so a total here will not match a raw count of rows.'],
                ['tip' => 'Counts are grouped by day in your workspace timezone, so "today" means your today.'],
            ],

            'tour' => [
                'title' => 'Read the dashboard correctly',
                'summary' => 'What the tiles count, and what they leave out.',
                'minutes' => 2,
                'permission' => null,
                'steps' => [
                    ['route' => 'dashboard', 'selector' => '[data-tour="dashboard-stats"]', 'title' => 'These are your tickets, not everyone\'s', 'body' => 'Open and in-progress count what is assigned to you. Overdue counts tickets past their resolution deadline — tickets on hold are excluded, because their clock is paused.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'dashboard', 'selector' => '[data-tour="nav-tickets"]', 'title' => 'The queue is the full picture', 'body' => 'Unassigned work does not appear in your tiles at all. The ticket list, filtered to unassigned, is where you find what nobody has picked up.', 'advance' => 'next', 'side' => 'right'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'ticket-collaboration' => [
            'group' => 'work',
            'title' => 'Working a Ticket',
            'icon' => 'chat-bubble-left-right',
            'permission' => 'view ticket comments',
            'minutes' => 4,
            'keywords' => 'comment reply internal note task checklist attachment canned response collaborate',
            'summary' => 'Replies, internal notes, tasks and attachments.',
            'blocks' => [
                ['h' => 'Replies and internal notes'],
                ['p' => 'A reply is part of the conversation. An <strong>internal note</strong> is not — it stays between colleagues and is never shown to the client.', 'permission' => 'create ticket comments'],
                ['warn' => 'Internal notes are a UI distinction, not a redaction. Write them as though the customer might one day read them in an export.', 'permission' => 'create internal notes'],

                ['h' => 'Tasks', 'permission' => 'manage ticket tasks'],
                ['p' => 'Break a ticket into a checklist. This is not decoration: a ticket cannot be closed while any task is still outstanding, so the checklist is the closing condition.', 'permission' => 'manage ticket tasks'],
                ['tip' => 'Cancel a task you no longer need rather than deleting it — a cancelled task still counts as resolved for closing, and the history keeps the decision.', 'permission' => 'manage ticket tasks'],

                ['h' => 'Attachments', 'permission' => 'upload attachments'],
                ['ul' => [
                    'Up to five files per ticket, 10 MB each.',
                    'Documents, images and archives only — scripts and pages are refused.',
                ], 'permission' => 'upload attachments'],

                ['faq' => [
                    ['q' => 'Why will this ticket not close?', 'a' => 'A task is still open. Complete or cancel it first.', 'permission' => 'manage ticket tasks'],
                ]],
            ],

            'tour' => [
                'title' => 'Work a ticket to closure',
                'summary' => 'Notes, tasks and replies — and what stops you closing.',
                'minutes' => 4,
                'permission' => 'create ticket comments',
                'steps' => [
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-row"]', 'title' => 'Open a ticket', 'body' => 'Pick any ticket to follow along — this tutorial only shows you around, it changes nothing.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-status"]', 'title' => 'Status is a state machine', 'body' => 'Not every move is allowed. A closed ticket can only be reopened, never sent straight back to in progress — the system refuses invalid moves rather than recording them.', 'advance' => 'next', 'side' => 'left'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-tasks"]', 'title' => 'Tasks are the closing condition', 'body' => 'This is not a notepad: while any task is outstanding the ticket cannot be closed. Cancel a task you no longer need rather than deleting it — the history keeps the decision.', 'advance' => 'next', 'side' => 'top'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-history"]', 'title' => 'Everything is recorded', 'body' => 'Status changes, assignments, priority moves and reopens all land here with who did them. When something looks wrong, this is the first place to look.', 'advance' => 'next', 'side' => 'top'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'escalation' => [
            'group' => 'work',
            'title' => 'Escalating a Ticket',
            'icon' => 'arrow-trending-up',
            'permission' => 'escalate tickets',
            'feature' => 'agent_escalation',
            'minutes' => 2,
            'keywords' => 'escalate tier senior second line handover',
            'summary' => 'Moving a ticket up a tier, and who can receive it.',
            'blocks' => [
                ['p' => 'Agents sit in three tiers. Escalating hands a ticket to someone with more authority over it.'],
                ['ul' => [
                    'You can only escalate <strong>up</strong>. Same tier or lower is refused.',
                    'The receiving agent must be at or above the tier you are escalating to.',
                    'The workspace owner is exempt from the tier rules.',
                ]],
                ['note' => 'Escalating with an agent selected reassigns the ticket, and that shows in the assignment history like any other handover.'],
            ],

        ],

        /* ---------------------------------------------------------------- */

        'spam' => [
            'group' => 'work',
            'title' => 'Spam',
            'icon' => 'shield-exclamation',
            'permission' => 'update tickets',
            'feature' => 'spam_management',
            'minutes' => 2,
            'keywords' => 'spam junk mark unmark bulk delete portal abuse',
            'summary' => 'Getting junk out of the queue without losing it.',
            'blocks' => [
                ['p' => 'The public portal is open to anyone, so some of what arrives is not a support request.'],
                ['steps' => [
                    'Mark a ticket as spam to take it out of every queue, count and report.',
                    'Spam is kept, not deleted — review it in the spam list and unmark anything caught wrongly.',
                    'Delete in bulk from that list once you are sure.',
                ], 'minutes' => 2],
                ['warn' => 'Marking spam is reversible; deleting from the spam list is not.'],
            ],

            'tour' => [
                'title' => 'Deal with junk',
                'summary' => 'Marking spam is reversible; deleting is not.',
                'minutes' => 2,
                'permission' => 'update tickets',
                'steps' => [
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-filters"]', 'title' => 'Junk arrives through the portal', 'body' => 'The public submit form is open to anyone, so some of what lands here is not a support request.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-row"]', 'title' => 'Open the offending ticket', 'body' => 'Spam is marked on the ticket itself.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-status"]', 'title' => 'Marked spam, not deleted', 'body' => 'Marking spam takes a ticket out of every queue, count and report but keeps it, so anything caught wrongly can be put back. Deleting from the spam list is the step that cannot be undone.', 'advance' => 'next', 'side' => 'left'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'members' => [
            'group' => 'people',
            'title' => 'Team Members',
            'icon' => 'user-group',
            'permission' => 'view members',
            'minutes' => 4,
            'keywords' => 'member agent user invite role department tier support access remove',
            'summary' => 'Adding people, and the two settings that decide what they see.',
            'blocks' => [
                ['p' => 'Two separate settings decide what a member can do. Getting either wrong is the most common cause of "why can I not see this?".'],
                ['ul' => [
                    'Their <strong>role</strong> decides which actions they may take.',
                    'Their <strong>departments</strong> decide which tickets they may see.',
                ]],
                ['warn' => 'A member with no departments sees only tickets assigned to them or raised by them — not everything. If someone reports an empty ticket list, check their departments first.'],
                ['h' => 'Adding someone', 'permission' => 'invite members'],
                ['steps' => [
                    'Enter their name and email — the email is their login and must be unique.',
                    'Pick a role. Start narrow; widening later is easy and safe.',
                    'Assign the departments whose tickets they should see.',
                    'On plans with agent tiers, set their support tier — a ticket above their tier cannot be assigned to them.',
                ], 'minutes' => 3, 'permission' => 'invite members'],
                ['h' => 'Removing someone', 'permission' => 'remove members'],
                ['p' => 'Removing a member revokes their access to this workspace. Their name stays on the tickets they handled, so the history stays readable.', 'permission' => 'remove members'],
            ],
            'tour' => [
                'title' => 'Add a team member',
                'summary' => 'Role decides what they can do; departments decide what they can see.',
                'minutes' => 3,
                'permission' => 'invite members',
                'steps' => [
                    ['route' => 'members.index', 'selector' => '[data-tour="member-create"]', 'title' => 'Start here', 'body' => 'Opens the new member form.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'members.create', 'selector' => '[data-tour="member-name"]', 'title' => 'This name goes on their tickets', 'body' => 'It is what customers and colleagues see against every ticket they handle, and it stays on those tickets after they leave.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'members.create', 'selector' => '[data-tour="member-email"]', 'title' => 'The email is the login', 'body' => 'It has to be unique across the whole system, and it is what they sign in with. Getting it wrong means an invitation that never arrives.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'roles' => [
            'group' => 'people',
            'title' => 'Roles & Permissions',
            'icon' => 'key',
            'permission' => 'view roles',
            'minutes' => 4,
            'keywords' => 'role permission custom access grant revoke owner admin agent viewer',
            'summary' => 'The seven built-in roles, and when to make your own.',
            'blocks' => [
                ['p' => 'Every workspace starts with seven roles, in decreasing access: administrator, manager, supervisor, senior agent, agent, viewer and billing.'],
                ['ul' => [
                    'The <strong>workspace owner</strong> bypasses every permission check and cannot be locked out.',
                    'An <strong>agent</strong> deliberately cannot browse the client directory — agents work tickets.',
                    'A <strong>viewer</strong> holds view-only permissions and nothing else.',
                ]],
                ['h' => 'Custom roles', 'permission' => 'create roles'],
                ['p' => 'Build a role from the individual permissions when none of the seven fits. Permissions are grouped by area to make the choice legible.', 'permission' => 'create roles'],
                ['note' => 'One permission behaves differently from the rest: <strong>view all tickets</strong> decides how much someone sees rather than whether they get in. Without it a member is limited to their departments instead of being refused.', 'permission' => 'create roles'],
            ],

            'tour' => [
                'title' => 'Build a custom role',
                'summary' => 'Start narrow — widening later is the safe direction.',
                'minutes' => 3,
                'permission' => 'create roles',
                'steps' => [
                    ['route' => 'roles.index', 'selector' => '[data-tour="role-create"]', 'title' => 'Start here', 'body' => 'Opens the role builder.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'roles.create', 'selector' => '[data-tour="role-name"]', 'title' => 'Name it for the job', 'body' => 'Members are assigned this by name, so it should describe what someone does rather than which permissions it happens to hold.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'departments' => [
            'group' => 'people',
            'title' => 'Departments',
            'icon' => 'building-office',
            'permission' => 'view departments',
            'feature' => 'department_management',
            'minutes' => 3,
            'keywords' => 'department team routing visibility scope queue split',
            'summary' => 'Departments are the ticket visibility boundary.',
            'blocks' => [
                ['p' => 'A department is more than a label: it is what decides which tickets a member can see. Without the "view all tickets" permission, a member sees their departments\' tickets plus their own work.'],
                ['h' => 'Creating one', 'permission' => 'create departments'],
                ['steps' => [
                    'Name it after the team that will work its tickets, not after the customer.',
                    'Assign members to it, or their queue stays empty.',
                ], 'minutes' => 2, 'permission' => 'create departments'],
                ['tip' => 'Fewer, broader departments cause fewer support questions than many narrow ones.'],
            ],

            'tour' => [
                'title' => 'Create a department',
                'summary' => 'Departments decide who can see which tickets.',
                'minutes' => 2,
                'permission' => 'create departments',
                'steps' => [
                    ['route' => 'departments.index', 'selector' => '[data-tour="department-create"]', 'title' => 'Start here', 'body' => 'Opens the new department form.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'departments.create', 'selector' => '[data-tour="department-name"]', 'title' => 'Name it after the team', 'body' => 'This is a visibility boundary, not a label: members assigned here see its tickets, and members who are not do not. Name it after who works the tickets.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'departments.create', 'selector' => '[data-tour="department-code"]', 'title' => 'The code shows up in reports', 'body' => 'Short and stable. Changing it later leaves older exports referring to something nobody recognises.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'categories' => [
            'group' => 'catalog',
            'title' => 'Ticket Categories',
            'icon' => 'tag',
            'permission' => 'view categories',
            'minutes' => 2,
            'keywords' => 'category type classification department reporting group',
            'summary' => 'What a ticket is about, for routing and reporting.',
            'blocks' => [
                ['p' => 'Categories classify what a ticket is about. They drive the category breakdown in reports, so a tidy list is worth keeping.'],
                ['note' => 'A category belongs to a department. Picking a department on the ticket form narrows the categories offered, so a category on the wrong department is effectively invisible.'],
                ['h' => 'Adding one', 'permission' => 'create categories'],
                ['steps' => [
                    'Choose the department it belongs to first.',
                    'Name it for what the customer experienced, not for the fix.',
                ], 'minutes' => 2, 'permission' => 'create categories'],
            ],

            'tour' => [
                'title' => 'Add a ticket category',
                'summary' => 'A category on the wrong department is invisible.',
                'minutes' => 2,
                'permission' => 'create categories',
                'steps' => [
                    ['route' => 'categories.index', 'selector' => '[data-tour="category-create"]', 'title' => 'Start here', 'body' => 'Opens the new category form.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'categories.create', 'selector' => '[data-tour="category-department"]', 'title' => 'Pick the department first', 'body' => 'The ticket form only offers categories belonging to the department chosen on it. A category filed under the wrong department will never appear.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'categories.create', 'selector' => '[data-tour="category-name"]', 'title' => 'Name it for the symptom', 'body' => 'Agents pick this while the customer is describing the problem, before anyone knows the cause. "Cannot log in" gets chosen; "LDAP misconfiguration" does not.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'products' => [
            'group' => 'catalog',
            'title' => 'Products & Services',
            'icon' => 'cube',
            'permission' => 'view products',
            'minutes' => 2,
            'keywords' => 'product service catalog asset item supported',
            'summary' => 'The things you support, attached to tickets.',
            'blocks' => [
                ['p' => 'Products are what you support. A ticket can name several, which is what makes the per-product report meaningful.'],
                ['h' => 'Adding one', 'permission' => 'create products'],
                ['p' => 'Keep names as the customer says them. A product nobody recognises will not get picked, and the report will be empty.', 'permission' => 'create products'],
            ],

            'tour' => [
                'title' => 'Add a product',
                'summary' => 'Name it the way customers say it.',
                'minutes' => 2,
                'permission' => 'create products',
                'steps' => [
                    ['route' => 'products.index', 'selector' => '[data-tour="product-create"]', 'title' => 'Start here', 'body' => 'Opens the new product form.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'products.create', 'selector' => '[data-tour="product-name"]', 'title' => 'Use the customer\'s words', 'body' => 'Agents pick this from a list while a customer is on the phone. A part number nobody recognises will not get chosen, and the per-product report stays empty.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'products.create', 'selector' => '[data-tour="product-price"]', 'title' => 'Price is a reference, not a charge', 'body' => 'Nothing here bills anyone. What a customer is charged comes from marking a ticket billable, which is a separate decision.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'knowledge-base' => [
            'group' => 'knowledge',
            'title' => 'Knowledge Base',
            'icon' => 'book-open',
            'permission' => 'view kb articles',
            'feature' => 'knowledge_base',
            // Switched off in the product — see config/features.php.
            'module' => 'knowledge_base',
            'minutes' => 4,
            'keywords' => 'knowledge base article kb publish draft portal public documentation',
            'summary' => 'Articles for your customers, and what publishing means.',
            'blocks' => [
                ['p' => 'The knowledge base is customer-facing. Published articles appear on your public portal, where anyone with the link can read them.'],
                ['warn' => 'Publishing is publishing. An article marked published is readable by the public without logging in — check it before you tick the box, not after.'],
                ['h' => 'Writing one', 'permission' => 'create kb articles'],
                ['steps' => [
                    'Give it the title a customer would search for, not your internal name for the problem.',
                    'Put it in a category so it is findable by browsing as well as searching.',
                    'Leave it unpublished until it has been read by someone else.',
                ], 'minutes' => 3, 'permission' => 'create kb articles'],
                ['tip' => 'The AI assistant searches published articles when answering, so a good article reduces both tickets and wrong answers.'],
            ],
            'tour' => [
                'title' => 'Publish a knowledge base article',
                'summary' => 'Publishing puts it on the public portal — check before ticking.',
                'minutes' => 3,
                'permission' => 'create kb articles',
                'steps' => [
                    ['route' => 'knowledge-base.articles.index', 'selector' => '[data-tour="kb-create"]', 'title' => 'Start here', 'body' => 'Opens the article editor.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'knowledge-base.articles.create', 'selector' => '[data-tour="kb-title"]', 'title' => 'Title it as a customer would search', 'body' => 'This is what people match against on the portal and what the AI assistant retrieves. Your internal name for the fault will not be searched for.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'knowledge-base.articles.create', 'selector' => '[data-tour="kb-publish"]', 'title' => 'This makes it public', 'body' => 'A published article is readable by anyone with your portal link, without logging in. Leave it off until someone else has read it.', 'advance' => 'next', 'side' => 'top'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'canned-responses' => [
            'group' => 'knowledge',
            'title' => 'Canned Responses',
            'icon' => 'chat-bubble-bottom-center-text',
            'permission' => 'view canned responses',
            'feature' => 'canned_responses',
            'minutes' => 2,
            'keywords' => 'canned response template saved reply macro boilerplate',
            'summary' => 'Saved replies for the answers you give weekly.',
            'blocks' => [
                ['p' => 'A canned response is a saved reply you can drop into a ticket and then edit. It is inserted, not sent — nothing reaches the customer until you post it.'],
                ['h' => 'Writing one', 'permission' => 'create canned responses'],
                ['tip' => 'Write them to be edited. A response that reads as finished gets sent unchanged, and customers notice a form letter.', 'permission' => 'create canned responses'],
            ],

            'tour' => [
                'title' => 'Save a canned response',
                'summary' => 'Write it to be edited, not sent as-is.',
                'minutes' => 2,
                'permission' => 'create canned responses',
                'steps' => [
                    ['route' => 'canned-responses.index', 'selector' => '[data-tour="canned-create"]', 'title' => 'Start here', 'body' => 'Opens the response editor.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'canned-responses.create', 'selector' => '[data-tour="canned-name"]', 'title' => 'Name it for the situation', 'body' => 'Agents pick from this name in a dropdown mid-reply. Name it for when you would reach for it, not for what it says.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'canned-responses.create', 'selector' => '[data-tour="canned-content"]', 'title' => 'Leave room to edit', 'body' => 'This is inserted into a reply, not sent. A response that reads as finished gets posted unchanged, and customers can tell.', 'advance' => 'next', 'side' => 'top'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'schedules' => [
            'group' => 'people',
            'title' => 'Agent Schedules',
            'icon' => 'calendar-days',
            'permission' => 'view schedules',
            'feature' => 'agent_schedule',
            'minutes' => 3,
            'keywords' => 'schedule shift roster working hours availability assignment refused',
            'summary' => 'Working hours, and how they block assignment.',
            'blocks' => [
                ['p' => 'A schedule records when an agent works. It is not just information: assigning a ticket to someone outside their hours is refused.'],
                ['note' => 'An agent with no schedule configured is treated as always available. No schedule means no rule, not no availability.'],
                ['h' => 'Setting hours', 'permission' => 'manage schedules'],
                ['warn' => 'If tickets suddenly cannot be assigned to someone, check their schedule before assuming a permission problem — the two produce similar-looking refusals.', 'permission' => 'manage schedules'],
            ],

            'tour' => [
                'title' => 'Set an agent\'s working hours',
                'summary' => 'Off-schedule assignment is refused, not just discouraged.',
                'minutes' => 2,
                'permission' => 'manage schedules',
                'steps' => [
                    ['route' => 'schedules.index', 'selector' => '[data-tour="schedule-user"]', 'title' => 'Pick the agent', 'body' => 'An agent with no schedule is treated as always available. Once you set one, assigning them a ticket outside those hours is refused — so set the hours you actually want enforced.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'schedules.index', 'selector' => '[data-tour="schedule-team"]', 'title' => 'Check the week as a whole', 'body' => 'The team view is where gaps show up. An hour nobody covers is an hour tickets cannot be assigned to anyone.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'service-reports' => [
            'group' => 'service',
            'title' => 'Service Reports',
            'icon' => 'document-text',
            'permission' => 'view service reports',
            'feature' => 'service_reports',
            'minutes' => 2,
            'keywords' => 'service report document pdf closure customer sign off',
            'summary' => 'The document generated when a ticket closes.',
            'blocks' => [
                ['p' => 'A service report is a customer-facing record of what was done on a ticket. One is generated automatically when a ticket closes, if the workspace has that switched on.'],
                ['note' => 'It draws on the closing remarks. A closure with a blank remark produces a report with nothing useful in it, so write the remark for the customer.'],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'billing' => [
            'group' => 'insight',
            'title' => 'Billing',
            'icon' => 'banknotes',
            'permission' => 'view billing',
            'feature' => 'billing',
            'minutes' => 2,
            'keywords' => 'billing billable invoice charge amount cost recover',
            'summary' => 'Marking work billable, and what the report totals.',
            'blocks' => [
                ['p' => 'A ticket can be marked billable with an amount and a description. The billing report totals those across a period.'],
                ['h' => 'Marking a ticket billable', 'permission' => 'manage billing'],
                ['tip' => 'Fill the description as though it will be read by whoever queries the invoice, because it will be.', 'permission' => 'manage billing'],
            ],

            'tour' => [
                'title' => 'Make a ticket billable',
                'summary' => 'What the billing report actually totals.',
                'minutes' => 2,
                'permission' => 'manage billing',
                'steps' => [
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-row"]', 'title' => 'Open the ticket you are charging for', 'body' => 'Billing is recorded per ticket, not per client.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-billing"]', 'title' => 'The description is the invoice line', 'body' => 'Whoever queries the charge will read this, possibly months later and without the ticket in front of them. Write it for them, not as a note to yourself.', 'advance' => 'next', 'side' => 'left'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'activity-logs' => [
            'group' => 'insight',
            'title' => 'Activity Log',
            'icon' => 'clipboard-document-list',
            'permission' => 'view activity logs',
            'feature' => 'audit_logs',
            'minutes' => 2,
            'keywords' => 'audit activity log history who changed trail accountability',
            'summary' => 'Who changed what, and when.',
            'blocks' => [
                ['p' => 'The activity log records creations, updates and deletions across the workspace, with the field-level before and after.'],
                ['ul' => [
                    'Noisy internal fields are deliberately excluded, so the log stays readable.',
                    'Passwords and tokens are never recorded.',
                ]],
                ['tip' => 'Each ticket also carries its own history on the ticket page, which is usually the faster place to look.'],
            ],

            'tour' => [
                'title' => 'Find out who changed something',
                'summary' => 'Where to look, and where to look first.',
                'minutes' => 2,
                'permission' => 'view activity logs',
                'steps' => [
                    ['route' => 'activity-logs.index', 'selector' => '[data-tour="activity-filters"]', 'title' => 'Narrow before you read', 'body' => 'This log covers the whole workspace, so it is long by design. Filter to the person or the day you are asking about.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'tickets.index', 'selector' => '[data-tour="ticket-row"]', 'title' => 'For one ticket, start on the ticket', 'body' => 'Each ticket carries its own history, which is almost always the faster answer. Come here when the question spans more than one record.', 'advance' => 'click', 'side' => 'bottom'],
                    ['route' => 'tickets.show', 'selector' => '[data-tour="ticket-history"]', 'title' => 'This is the same trail, scoped', 'body' => 'Status changes, assignments and reopens with who did them and when — without you having to filter a workspace-wide log down to one ticket.', 'advance' => 'next', 'side' => 'top'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'public-portal' => [
            'group' => 'admin',
            'title' => 'The Public Portal',
            'icon' => 'globe-alt',
            'permission' => 'view settings',
            'minutes' => 3,
            'keywords' => 'portal public submit track ticket customer self service landing qr link',
            'summary' => 'The pages your customers use, without an account.',
            'blocks' => [
                ['p' => 'Your workspace has public pages where customers can submit a ticket and track it afterwards, with no login. They live under your workspace address.'],
                ['ul' => [
                    'A submitted ticket returns a tracking link the customer can keep.',
                    'On Enterprise plans customers can also reply on a tracked ticket, and read your knowledge base.',
                ]],
                ['h' => 'Prefilled links'],
                ['p' => 'Each client has a prefilled submit link, which you can hand out as a QR code — useful on a sticker beside the equipment you support.'],
                ['warn' => 'Those links are not signed. Anyone can craft one, so treat every value arriving through the portal as customer-supplied.'],
            ],

            'tour' => [
                'title' => 'Give a client a submit link',
                'summary' => 'A prefilled link and QR code, and why it is not a password.',
                'minutes' => 3,
                'permission' => 'view clients',
                'steps' => [
                    ['route' => 'clients.index', 'selector' => '[data-tour="client-filters"]', 'title' => 'Find the client', 'body' => 'Each client has their own prefilled submit link, so start from the client rather than from the portal.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'clients.index', 'selector' => '[data-tour="client-create"]', 'title' => 'Open a client to find their link', 'body' => 'The quick-submit link lives on the client record. Open any client from the list to see it.', 'advance' => 'next', 'side' => 'bottom'],
                ],
            ],
        ],

        /* ---------------------------------------------------------------- */

        'api-access' => [
            'group' => 'admin',
            'title' => 'API Access',
            'icon' => 'code-bracket',
            'permission' => 'manage api tokens',
            // API access is enabled per workspace; its page 404s otherwise, so
            // the topic and its tour must follow the same switch.
            'gate' => 'apiAccessEnabled',
            'minutes' => 3,
            'keywords' => 'api token rest integration bearer authentication webhook programmatic',
            'summary' => 'Tokens for integrating another system.',
            'blocks' => [
                ['p' => 'The REST API lets another system raise and read tickets. It authenticates with a bearer token that carries this workspace\'s identity — there is no workspace name in the URL.'],
                ['warn' => 'A token is shown once, at the moment you create it. It cannot be recovered afterwards: if it is lost, revoke it and issue another.'],
                ['steps' => [
                    'Name the token after the system that will use it, so you know what you are revoking later.',
                    'Copy it immediately and store it wherever that system keeps its secrets.',
                    'Revoke tokens for integrations you have retired — an unused token is still a working key.',
                ], 'minutes' => 2],
            ],
            'tour' => [
                'title' => 'Create an API token',
                'summary' => 'It is shown once and cannot be recovered.',
                'minutes' => 2,
                'permission' => 'manage api tokens',
                'steps' => [
                    ['route' => 'settings.api-tokens', 'selector' => '[data-tour="token-name"]', 'title' => 'Name it after the integration', 'body' => 'This is the only thing distinguishing one token from another when you come to revoke one.', 'advance' => 'next', 'side' => 'bottom'],
                    ['route' => 'settings.api-tokens', 'selector' => '[data-tour="token-submit"]', 'title' => 'Copy it now', 'body' => 'The token appears once, on the next screen. It is stored hashed, so nobody — including support — can show it to you again.', 'advance' => 'next', 'side' => 'top'],
                ],
            ],
        ],

    ],

];
