<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('AI Assistant') }}</h2>
    <p>{{ __('Say hello to your AI teammate — the sparkle button floating in the bottom-right corner of every page. Think of it as the colleague who has read every ticket, never sleeps, and is happy to crunch numbers, draft replies, or just answer a quick question. You talk to it in plain language; it figures out the rest.') }}</p>

    {{-- Hero "meet the assistant" callout --}}
    <div class="not-prose my-5 flex items-start gap-3 rounded-xl bg-linear-to-br from-indigo-50 to-violet-50 p-4 ring-1 ring-indigo-100 dark:from-indigo-500/10 dark:to-violet-500/10 dark:ring-indigo-500/20">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
        </span>
        <div class="text-sm text-indigo-900 dark:text-indigo-200">
            <p class="font-semibold">{{ __('Just start typing — no commands to memorize.') }}</p>
            <p class="mt-0.5 text-indigo-700 dark:text-indigo-300">{{ __('Press the sparkle button, ask a question the way you would ask a coworker, and the assistant will pull the data, build the chart, or write the draft for you. When in doubt, just ask it "what can you do?"') }}</p>
        </div>
    </div>

    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('The AI Assistant is an Enterprise feature, and an admin can switch it on or off (and toggle the in-app copilot vs. the public portal bot) under Settings → AI.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Opening it & the basics') }}</h3>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('Click the sparkle bubble in the bottom-right to open the chat. Click it again (or the ✕) to tuck it away.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Shift + Enter') }}</strong> {{ __('adds a new line; plain') }} <strong class="dark:text-gray-100">{{ __('Enter') }}</strong> {{ __('sends your message.') }}</li>
        <li>{{ __('Drag the left edge of the panel to make it wider — handy when you are reading a long answer or a chart.') }}</li>
        <li>{{ __('Your conversations are private to you and saved, so you can pick up where you left off. Use the history (clock) icon to revisit past chats or the pencil icon to start a fresh one.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('What to ask — a cheat sheet') }}</h3>
    <p>{{ __('Below are real prompts you can copy, tweak, and send. They are grouped by what you are trying to get done. The assistant understands follow-ups too, so feel free to keep the conversation going ("now only the high-priority ones").') }}</p>

    {{-- Recipe 1: Find & filter tickets --}}
    <h4 class="mt-5 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <span class="text-indigo-600 dark:text-indigo-400">①</span> {{ __('Find & filter your tickets') }}
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Let the assistant dig through the queue so you do not have to click filters.') }}</p>
    <div class="not-prose my-3 grid gap-2 sm:grid-cols-2">
        @foreach([
            'What tickets are assigned to me?',
            'Show me open high-priority tickets from this week.',
            'Which tickets for Acme Corp are still unresolved?',
            'Any tickets that have been on hold for more than 3 days?',
        ] as $prompt)
            @include('tutorials.partials._prompt', ['prompt' => $prompt])
        @endforeach
    </div>

    {{-- Recipe 2: Stats & charts --}}
    <h4 class="mt-5 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <span class="text-indigo-600 dark:text-indigo-400">②</span> {{ __('See the numbers — with charts') }}
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ask for a "chart", "graph", "pie", or "trend" and the assistant draws it right in the chat. It picks a bar, pie, or line chart to fit the data.') }}</p>
    <div class="not-prose my-3 grid gap-2 sm:grid-cols-2">
        @foreach([
            'Give me a pie chart of tickets by status.',
            'Bar graph of open tickets by priority.',
            'Chart how many tickets we closed each day this week.',
            'How many tickets did I close this month?',
        ] as $prompt)
            @include('tutorials.partials._prompt', ['prompt' => $prompt])
        @endforeach
    </div>

    {{-- Recipe 3: Resolve faster --}}
    <h4 class="mt-5 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <span class="text-indigo-600 dark:text-indigo-400">③</span> {{ __('Resolve problems faster') }}
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('The assistant learns from every ticket your team closes. Describe a problem and it will surface how similar issues were solved before — then help you write the answer.') }}</p>
    <div class="not-prose my-3 grid gap-2 sm:grid-cols-2">
        @foreach([
            "A customer's VPN keeps disconnecting — how have we fixed this before?",
            'Find past tickets about printer driver errors and what resolved them.',
            'Draft a friendly reply explaining how to reset their license key.',
            'Summarize this ticket thread in three bullet points.',
        ] as $prompt)
            @include('tutorials.partials._prompt', ['prompt' => $prompt])
        @endforeach
    </div>
    <div class="mt-3 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900 dark:border-indigo-500 dark:bg-indigo-500/10 dark:text-indigo-300">
        {{ __('Tip: The more thorough your closing remarks are when you close a ticket, the smarter the assistant gets — those notes become the "memory" it draws on for the next similar problem. On a ticket page you can also use the "Draft with AI" and "Summarize" buttons directly.') }}
    </div>

    {{-- Recipe 4: Files & images --}}
    <h4 class="mt-5 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <span class="text-indigo-600 dark:text-indigo-400">④</span> {{ __('Drop in a screenshot or document') }}
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Use the paperclip to attach an image, PDF, or text file, then ask about it. The assistant can read error screenshots and skim documents.') }}</p>
    <div class="not-prose my-3 grid gap-2 sm:grid-cols-2">
        @foreach([
            '📎 [screenshot] What error is this showing, and how do I fix it?',
            '📎 [PDF] Summarize the key requirements in this client document.',
            '📎 [log file] What is causing the failures in this log?',
            '📎 [photo] Read the serial number off this device label.',
        ] as $prompt)
            @include('tutorials.partials._prompt', ['prompt' => $prompt])
        @endforeach
    </div>

    {{-- Recipe 5: Learn the app & look things up --}}
    <h4 class="mt-5 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        <span class="text-indigo-600 dark:text-indigo-400">⑤</span> {{ __('Learn the app & look things up') }}
    </h4>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('It knows how this system works, and can reach the web for current, general questions when you need them.') }}</p>
    <div class="not-prose my-3 grid gap-2 sm:grid-cols-2">
        @foreach([
            'How do I set up an SLA policy?',
            'What is the difference between On Hold and Cancelled?',
            'How does this system compare to other help desks?',
            'What are the latest best practices for reducing ticket backlog?',
        ] as $prompt)
            @include('tutorials.partials._prompt', ['prompt' => $prompt])
        @endforeach
    </div>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Get better answers — a few habits') }}</h3>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong class="dark:text-gray-100">{{ __('Be specific.') }}</strong> {{ __('"High-priority open tickets for Acme this week" beats "show me tickets".') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Build on the last answer.') }}</strong> {{ __('Say "now just the unassigned ones" or "make that a pie chart" instead of starting over.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Tell it the format you want.') }}</strong> {{ __('"In a table", "as three bullets", "a short reply I can send the client".') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Ask it to show its work.') }}</strong> {{ __('"Which tickets did you count?" — it can list them.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Good to know (the guardrails)') }}</h3>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('It only ever sees your own organization\'s data. Tickets, clients, and reports stay walled off from other tenants — always.') }}</li>
        <li>{{ __('It reads, counts, drafts, and charts — but it will not silently change or close your tickets. Drafts wait for you to review and send, so a human is always in the loop.') }}</li>
        <li>{{ __('If it is not sure, it says so rather than inventing a policy, price, or fact. If the AI is ever unavailable, the rest of the app keeps working normally.') }}</li>
    </ul>

    <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">{{ __('That is it — open the sparkle button and try one of the prompts above. The fastest way to learn what your AI teammate can do is to ask it.') }}</p>
</div>