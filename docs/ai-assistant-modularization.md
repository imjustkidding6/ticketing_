# Modularizing the AI Assistant for Reuse in Other Systems

A design note for extracting the AI Assistant into a reusable module/package that
can be dropped into other apps. This is a plan, not yet implemented.

## The coupling falls into three rings

**Ring 1 — already portable (move as-is)**
- `OpenAiService` (`app/Services/OpenAiService.php`) — pure HTTP wrapper, zero app knowledge.
- `FileParser` (`app/Support/FileParser.php`) — generic upload → text/vision.
- The embeddings + cosine-similarity math inside `AiAssistantService` (~lines 549–767).
- The chart fenced-block convention and the tool-calling loop (`converse()`, ~lines 83–125).

**Ring 2 — generic shape, app-specific content**
- `ChatConversation` / `ChatMessage` persistence — shape is reusable; the `BelongsToTenant`
  trait and `channel` semantics are this app's.
- `SystemGuide` (`app/Support/SystemGuide.php`) — mechanism is generic, the 16 topics are CliqueHA-specific.
- The Blade widgets — generic UX, but hardcoded `route()` names, feature flags, branding vars.

**Ring 3 — welded to this app (the real work)**
- The 10 tools. Each directly queries `Ticket`/`Client`/`KbArticle`/`Department`/`BugReport`;
  dispatch is a hardcoded `match()` in `executeTool()` (`AiAssistantService` ~lines 258–273).
- Tenant everywhere — every method takes `Tenant $tenant`.
- Feature gating — `PlanService`/`PlanFeature` (lives in controllers, good).
- `PageContextResolver` — queries this app's Ticket/Client.
- Per-tenant config — `AppSetting::get('ai_system_prompt')` etc.

Ring 3 is ~90% of the value AND ~90% of the coupling. Strategy: **make Ring 3 pluggable,
ship Rings 1+2 as an engine.**

## The one architectural move that unlocks everything

A tool is currently split across three places: a schema in `appTools()`, a `match` arm in
`executeTool()`, and a private method. Invert that — make a tool a single self-describing
object the host app registers:

```php
interface AssistantTool
{
    public function name(): string;
    public function schema(): array;                          // OpenAI function spec
    public function handle(array $args, AssistantContext $ctx): array;
}
```

Replace the `Tenant $tenant` thread with a neutral context the host populates:

```php
final class AssistantContext
{
    public function __construct(
        public readonly int|string|null $tenantId, // null for single-tenant apps
        public readonly ?object $user,
        public readonly string $channel,           // 'agent' | 'portal' | …
        public readonly array $settings,           // system_prompt, timezone, flags…
    ) {}
}
```

`AiAssistantService` then shrinks to a domain-free engine:

```php
$assistant = new Assistant($llm, $registry, $conversationRepo);
$assistant->register(new SearchKnowledgeBaseTool($kb));   // host app's tools
$assistant->register(new QueryTicketsTool($tickets));
$reply = $assistant->converse($context, $conversation, $userMessage);
```

The engine owns: tool loop, message persistence, history cleansing, embeddings retrieval,
chart passthrough, web search. It never names `Ticket` or `Tenant`. Each host app ships its
own `AssistantTool` classes. This is the difference between "copy-paste and edit a 1,100-line
file" and "install the engine, write 5 small tool classes."

## Seams to abstract (small interfaces)

| Today (hardcoded) | Interface to introduce | Why |
|---|---|---|
| `Tenant $tenant` params | `AssistantContext` value object | works for single-tenant / non-tenant apps too |
| `executeTool()` `match` | `ToolRegistry` + `AssistantTool` | host registers domain tools |
| `ChatConversation::create(...)` | `ConversationRepository` | apps with different models/storage plug in |
| `AppSetting::get('ai_*')` | `SettingsResolver` | KV source is the host's concern |
| `PageContextResolver` (Ticket/Client) | `ContextProvider` (optional, per-app) | page-awareness is domain-specific |
| `OpenAiService` | `LlmClient` (chat/embed/webSearch) | lets you swap in Anthropic etc. later |

`SystemGuide` and `FileParser` move as-is; `SystemGuide` content becomes host-supplied data
(a config array or a `GuideProvider`).

## Proposed package layout

A Composer package (own repo or path repo), provider- and domain-agnostic:

```
cliqueha/laravel-ai-assistant
├── src/
│   ├── Assistant.php              # the engine (ex-AiAssistantService, domain-free)
│   ├── AssistantContext.php
│   ├── Contracts/                 # AssistantTool, ConversationRepository,
│   │                              #   SettingsResolver, LlmClient, ContextProvider
│   ├── Llm/OpenAiClient.php       # ex-OpenAiService
│   ├── Tools/AbstractTool.php     # base + web-search & embeddings-retrieval tools (domain-free)
│   ├── Support/{FileParser,Charts,Cosine}.php
│   └── Http/Concerns/             # reusable controller traits (no routes)
├── database/migrations/           # chat_conversations, chat_messages (publishable)
├── resources/views/               # widget partials with @yields for routes/branding
└── config/ai-assistant.php
```

This ticketing app becomes the first consumer: it keeps `Ticket`/`Client`/KB knowledge by
shipping `app/AiAssistant/Tools/*` (QueryTicketsTool, CreateTicketTool, ReportBugTool…)
implementing the package interface, plus an `EloquentConversationRepository` and an
`AppSettingResolver`. Nothing user-facing changes.

## Sequencing (low-risk, incremental)

1. **Extract Ring 1** into the package first (`LlmClient`, `FileParser`, cosine/charts).
   No behavior change — move + swap the binding. Tests stay green.
2. **Introduce `AssistantTool` + `ToolRegistry`** inside this app, converting the 10 tools
   one at a time, keeping the `match` as a fallback until all are migrated.
3. **Introduce `AssistantContext`** to replace `Tenant $tenant` plumbing.
4. **Lift the engine** (now domain-free) into the package; leave tool classes here.
5. Generalize the Blade widgets last (yield route names + branding tokens).

Each step ships behind the existing feature flags, so the live assistant never breaks.

## Open scoping questions (decide before building)

1. **Are the other systems also Laravel?** Yes → Composer package as above. Non-PHP → the
   reusable artifact is the engine spec, not a Laravel package (bigger conversation).
2. **Are they multi-tenant or single-tenant?** Decides whether `AssistantContext.tenantId`
   is required/nullable and whether persistence assumes `BelongsToTenant`.
3. **One LLM provider or several?** If Anthropic/others are possible, do the `LlmClient`
   interface now; if OpenAI forever, skip that abstraction and keep it simpler.
