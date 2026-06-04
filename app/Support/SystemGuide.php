<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Built-in, product-wide help about how to USE the CliqueHA Nexus app
 * (navigation, where features live, how to perform actions). This is distinct
 * from a tenant's own Knowledge Base (which is for that tenant's customers).
 * The AI assistant searches this so it can answer "how do I … / where is …".
 */
class SystemGuide
{
    /**
     * @return array<int, array{title: string, location: string, content: string}>
     */
    public static function topics(): array
    {
        return [
            [
                'title' => 'Create a ticket',
                'location' => 'Sidebar → Tickets → "Create Ticket" button',
                'content' => 'Open Tickets in the sidebar and click "Create Ticket". Choose the client (or create one), enter a subject and description, pick a department, category, and priority, and optionally assign an agent. Tickets also arrive automatically from the public portal.',
            ],
            [
                'title' => 'Find or search tickets',
                'location' => 'Sidebar → Tickets (search box / filters)',
                'content' => 'The Tickets page lists all tickets with filters for status, priority, department, and a search box. Click any ticket to open its detail page.',
            ],
            [
                'title' => 'Reply to a ticket or add an internal note',
                'location' => 'Ticket detail → comment box at the bottom',
                'content' => 'On a ticket, scroll to the comment box. Choose "Public" to send a reply the client can see, or "Internal Note" for a private note. With the AI copilot enabled you can also click "Draft with AI" or "Summarize".',
            ],
            [
                'title' => 'Assign or self-assign a ticket',
                'location' => 'Ticket detail → Assignment & Priority panel',
                'content' => 'On the ticket detail page, use the "Assign To" dropdown to assign an agent, or "Self-assign" to take it yourself. Managers and admins can assign to anyone.',
            ],
            [
                'title' => 'Change status, priority, or put a ticket on hold',
                'location' => 'Ticket detail → status / priority controls',
                'content' => 'Use the status control to move a ticket through Open, Assigned, In Progress, On Hold, Closed, or Cancelled. On Hold pauses the SLA clock. Priority is Low/Medium/High/Critical.',
            ],
            [
                'title' => 'Close a ticket (tasks required)',
                'location' => 'Ticket detail → set status to Closed',
                'content' => 'A ticket must have at least one task, and all tasks completed or cancelled, before it can be closed. Add a task describing the work, finish it, then set status to Closed and add closing remarks. To close a non-issue, use "Mark as false alarm".',
            ],
            [
                'title' => 'Manage clients',
                'location' => 'Sidebar → Clients',
                'content' => 'Add and edit clients under Clients. Set a client tier (Basic, Premium, Enterprise) which, with priority, selects the SLA policy. Clients are also created automatically from public portal submissions.',
            ],
            [
                'title' => 'Departments, categories, and products',
                'location' => 'Sidebar → Management (Departments / Categories / Products & Services)',
                'content' => 'Departments group tickets (department management is Enterprise). Categories classify requests within a department. Products & Services can be linked to tickets for product reporting.',
            ],
            [
                'title' => 'User management and roles',
                'location' => 'Sidebar → Management → User Management',
                'content' => 'Invite agents, managers, and admins under User Management. Default roles are Agent, Manager, and Admin; Enterprise can create custom roles under Roles with specific permissions.',
            ],
            [
                'title' => 'SLA policies',
                'location' => 'Sidebar → SLA → SLA Policies',
                'content' => 'Define response and resolution targets per client tier on the SLA Policies page. Seed the defaults to start, then tune each tier. SLA is a Business+ feature.',
            ],
            [
                'title' => 'Reports and exports',
                'location' => 'Sidebar → Reports (Overview, Tickets, Agents, SLA compliance, …)',
                'content' => 'Reports cover ticket volume, departments, categories, clients, agents, products, reopen analysis, and SLA compliance. Use the date filter; export to CSV where available (Detailed Reporting feature). Service-report PDFs are generated from a ticket.',
            ],
            [
                'title' => 'Settings',
                'location' => 'Sidebar → Settings (tabs: General, Tickets, Notifications, Branding, Service Report, AI Assistant)',
                'content' => 'General sets company name, timezone, and date format. Notifications configures email/SMTP. Branding sets logo and colors. AI Assistant turns the AI features on or off. Settings are admin/owner only.',
            ],
            [
                'title' => 'Knowledge base management',
                'location' => 'Sidebar → Knowledge Base (Enterprise)',
                'content' => 'Publish help articles for your clients, organized into categories, under Knowledge Base. They appear on your public portal. This is an Enterprise feature.',
            ],
            [
                'title' => 'Turn the AI Assistant on or off',
                'location' => 'Sidebar → Settings → AI Assistant',
                'content' => 'On the AI Assistant settings tab, use the master "Enable AI Assistant" toggle, plus toggles for the public portal widget and the agent copilot. It is opt-in per tenant and is an Enterprise feature.',
            ],
            [
                'title' => 'Public support portal',
                'location' => 'Your workspace URL: /{your-slug}/',
                'content' => 'Business+ plans get a public portal where clients submit and track tickets without an account, browse the knowledge base (Enterprise), and chat with the AI assistant. Share the slug URL with your clients.',
            ],
        ];
    }

    /**
     * Keyword-search the guide and return the most relevant topics.
     *
     * @return array{topics: array<int, array{title: string, location: string, content: string}>}
     */
    public static function search(string $query): array
    {
        $query = trim(Str::lower($query));
        $topics = self::topics();

        if ($query === '') {
            return ['topics' => array_slice($topics, 0, 4)];
        }

        $words = array_filter(preg_split('/\s+/', $query) ?: [], fn ($w) => strlen($w) >= 3);

        $scored = [];
        foreach ($topics as $topic) {
            $haystack = Str::lower($topic['title'].' '.$topic['content']);
            $score = 0;
            foreach ($words as $word) {
                $score += substr_count($haystack, $word);
            }
            if (Str::contains($haystack, $query)) {
                $score += 5;
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'topic' => $topic];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['topics' => array_map(fn ($s) => $s['topic'], array_slice($scored, 0, 4))];
    }
}
