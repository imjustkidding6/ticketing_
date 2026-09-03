<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Builds the help centre content and the guided tours for one specific reader.
 *
 * Everything is filtered against their permissions: a topic, a block inside a
 * topic, an FAQ entry or a whole tour disappears when they cannot reach the
 * feature it describes. Content lives in config/help.php.
 *
 * This is the only place help permissions are read. The page, the contents
 * list, the search index, the PDF and the tour endpoint all call the same
 * methods — the moment one of them re-checks a permission itself, they start
 * to drift.
 */
class HelpService
{
    /**
     * Visible topics, arranged into the groups the contents list renders.
     *
     * @return array<string, array<string, mixed>>
     */
    public function groupedTopicsFor(?User $user): array
    {
        $groups = [];

        foreach ($this->topicsFor($user) as $key => $topic) {
            $groupKey = $topic['group'] ?? 'basics';

            if (! isset($groups[$groupKey])) {
                $meta = config("help.groups.{$groupKey}", ['label' => ucfirst($groupKey), 'icon' => 'book-open']);
                $groups[$groupKey] = $meta + ['topics' => []];
            }

            $groups[$groupKey]['topics'][$key] = $topic;
        }

        return $groups;
    }

    /**
     * Flat list of visible topics, keyed by topic key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function topicsFor(?User $user): array
    {
        $visible = [];

        foreach (config('help.topics', []) as $key => $topic) {
            if (! $this->allows($user, $topic['permission'] ?? null)
                || ! $this->planHas($topic['feature'] ?? null)
                || ! $this->tenantAllows($user, $topic['gate'] ?? null)
                || ! $this->enabled($topic['module'] ?? null)) {
                continue;
            }

            $topic['key'] = $key;
            $topic['blocks'] = $this->filterBlocks($user, $topic['blocks'] ?? []);
            $topic['tour'] = $this->resolveTour($user, $key, $topic);

            // A topic whose every block was filtered away has nothing left to
            // say — drop it rather than render an empty card.
            if ($topic['blocks'] === [] && $topic['tour'] === null) {
                continue;
            }

            $visible[$key] = $topic;
        }

        return $visible;
    }

    /**
     * Every topic, unfiltered, for platform staff.
     *
     * Platform administrators hold no tenant role, so filtering by permission
     * would hand them an empty guide. They are cross-tenant by design — the
     * same exemption the admin controllers already carry.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allTopics(): array
    {
        $all = [];

        foreach (config('help.topics', []) as $key => $topic) {
            $topic['key'] = $key;
            $topic['blocks'] = $this->dropOrphanHeadings($topic['blocks'] ?? []);
            $topic['tour'] = null;

            $all[$key] = $topic;
        }

        return $all;
    }

    /**
     * Groups for the unfiltered set.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allGroupedTopics(): array
    {
        $groups = [];

        foreach ($this->allTopics() as $key => $topic) {
            $groupKey = $topic['group'] ?? 'basics';

            if (! isset($groups[$groupKey])) {
                $meta = config("help.groups.{$groupKey}", ['label' => ucfirst($groupKey), 'icon' => 'book-open']);
                $groups[$groupKey] = $meta + ['topics' => []];
            }

            $groups[$groupKey]['topics'][$key] = $topic;
        }

        return $groups;
    }

    /**
     * One topic, filtered, or null when the reader may not see it.
     *
     * @return array<string, mixed>|null
     */
    public function topicFor(?User $user, string $key): ?array
    {
        return $this->topicsFor($user)[$key] ?? null;
    }

    /**
     * One tour, resolved and permission-checked, or null. Used by the endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function tourFor(?User $user, string $topicKey): ?array
    {
        $topic = config("help.topics.{$topicKey}");

        if ($topic === null
            || ! $this->allows($user, $topic['permission'] ?? null)
            || ! $this->planHas($topic['feature'] ?? null)
            || ! $this->tenantAllows($user, $topic['gate'] ?? null)
            || ! $this->enabled($topic['module'] ?? null)) {
            return null;
        }

        return $this->resolveTour($user, $topicKey, $topic);
    }

    /**
     * Every tour this reader may take.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toursFor(?User $user): array
    {
        return array_filter(array_map(
            fn (array $topic) => $topic['tour'],
            $this->topicsFor($user)
        ));
    }

    /**
     * Topics this reader may NOT see, reduced to what the search box needs to
     * explain itself.
     *
     * Without this, searching for a feature your role cannot open returns a
     * bare "nothing matches", which reads as a broken search rather than as
     * the scoping working. Titles and permission names are not sensitive —
     * knowing an SLA module exists is not access to it.
     *
     * @return array<int, array{title: string, keywords: string, permissions: array<int, string>}>
     */
    public function hiddenTopicsFor(?User $user): array
    {
        $visible = $this->topicsFor($user);
        $hidden = [];

        foreach (config('help.topics', []) as $key => $topic) {
            if (isset($visible[$key])) {
                continue;
            }

            $hidden[] = [
                'title' => html_entity_decode(strip_tags($topic['title'])),
                'keywords' => Str::squish(html_entity_decode(implode(' ', [
                    $topic['keywords'] ?? '',
                    strip_tags($topic['title']),
                    strip_tags($topic['summary'] ?? ''),
                ]))),
                'permissions' => array_values(Arr::wrap($topic['permission'] ?? [])),
            ];
        }

        return $hidden;
    }

    /**
     * Everything a topic should be findable by.
     *
     * The card shows a tour only as a "Start tutorial" button, so without
     * folding the tour's own wording in here, searching for what a tutorial
     * teaches would find nothing.
     *
     * @param  array<string, mixed>  $topic
     */
    public function searchTextFor(array $topic): string
    {
        $parts = [
            $topic['keywords'] ?? '',
            strip_tags($topic['title']),
            strip_tags($topic['summary'] ?? ''),
        ];

        foreach ($topic['blocks'] ?? [] as $block) {
            foreach (['h', 'p', 'tip', 'note', 'warn'] as $key) {
                if (isset($block[$key])) {
                    $parts[] = strip_tags($block[$key]);
                }
            }

            foreach (array_merge($block['steps'] ?? [], $block['ul'] ?? []) as $line) {
                $parts[] = strip_tags($line);
            }

            foreach ($block['faq'] ?? [] as $entry) {
                $parts[] = strip_tags($entry['q']).' '.strip_tags($entry['a']);
            }
        }

        if (! empty($topic['tour'])) {
            // The tour's own wording only. Padding every tour-bearing topic
            // with "tutorial tour guided" would make those words match nearly
            // everything and stop them narrowing anything.
            $parts[] = strip_tags($topic['tour']['title']);

            foreach ($topic['tour']['steps'] as $step) {
                $parts[] = strip_tags($step['title']).' '.strip_tags($step['body']);
            }
        }

        return Str::squish(html_entity_decode(implode(' ', $parts)));
    }

    /**
     * Total estimated minutes across what this reader can actually see.
     */
    public function totalMinutesFor(?User $user): int
    {
        return array_sum(array_column($this->topicsFor($user), 'minutes'));
    }

    /**
     * Attach the runtime bits a tour needs, and drop it when the reader lacks
     * its own permission — which is separate from the topic's. Reading about
     * tickets is not permission to be taught how to raise one.
     *
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>|null
     */
    private function resolveTour(?User $user, string $topicKey, array $topic): ?array
    {
        $tour = $topic['tour'] ?? null;

        if ($tour === null || ! $this->allows($user, $tour['permission'] ?? null)) {
            return null;
        }

        $tour['key'] = $topicKey;
        $tour['topic'] = $topicKey;
        $tour['topic_title'] = $topic['title'];
        $tour['icon'] = $topic['icon'] ?? 'academic-cap';
        $tour['category'] = config("help.groups.{$topic['group']}.label", null);

        $tour['steps'] = array_values(array_map(function (array $step) {
            $step['page'] = $step['route'] ?? null;
            $step['url'] = $this->urlForRoute($step['route'] ?? null);
            $step['advance'] = $step['advance'] ?? 'next';
            $step['side'] = $step['side'] ?? 'bottom';

            return $step;
        }, $tour['steps'] ?? []));

        // Drop steps whose route is not registered at all — a disabled module
        // would otherwise dead-end the tour. A registered route we merely
        // cannot build a URL for (it needs a record id) is kept: the runner
        // tells the reader where to go instead of linking somewhere wrong.
        $tour['steps'] = array_values(array_filter(
            $tour['steps'],
            fn (array $s) => $s['page'] !== null && Route::has($s['page'])
        ));

        if ($tour['steps'] === []) {
            return null;
        }

        $tour['start_url'] = $tour['steps'][0]['url'] ?? null;

        return $tour;
    }

    /**
     * Path for a route name, or null when it is unregistered or needs
     * parameters we cannot supply.
     */
    private function urlForRoute(?string $name): ?string
    {
        if ($name === null || ! Route::has($name)) {
            return null;
        }

        try {
            // The tenant slug comes from SetTenantUrlDefaults. Anything else
            // missing throws, which is the signal that we cannot link to it.
            return parse_url(route($name), PHP_URL_PATH) ?: '/';
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Drop blocks the reader may not see, and prune FAQ entries the same way.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function filterBlocks(?User $user, array $blocks): array
    {
        $kept = [];

        foreach ($blocks as $block) {
            if (! $this->allows($user, $block['permission'] ?? null)) {
                continue;
            }

            if (isset($block['faq'])) {
                $block['faq'] = array_values(array_filter(
                    $block['faq'],
                    fn (array $entry) => $this->allows($user, $entry['permission'] ?? null)
                ));

                if ($block['faq'] === []) {
                    continue;
                }
            }

            $kept[] = $block;
        }

        return $this->dropOrphanHeadings($kept);
    }

    /**
     * A sub-heading whose content was all filtered away leaves a bare title
     * over nothing. This is the line between a working implementation and one
     * that renders a heading with an empty space under it.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function dropOrphanHeadings(array $blocks): array
    {
        $kept = [];

        foreach ($blocks as $i => $block) {
            if (isset($block['h'])) {
                $next = $blocks[$i + 1] ?? null;

                if ($next === null || isset($next['h'])) {
                    continue;
                }
            }

            $kept[] = $block;
        }

        return array_values($kept);
    }

    /**
     * Whether the workspace plan includes a feature a topic depends on.
     *
     * Permission and plan are different questions: an administrator may hold
     * "use ai assistant" on a workspace whose plan has no AI. Documenting it
     * to them advertises something they cannot switch on.
     */
    private function planHas(?string $feature): bool
    {
        if ($feature === null) {
            return true;
        }

        $case = PlanFeature::tryFrom($feature);

        return $case !== null && app(PlanService::class)->currentTenantHasFeature($case);
    }

    /**
     * Whether the module this topic documents is switched on in the product.
     *
     * Separate from permissions and plans: a module can exist in the codebase,
     * be included in the plan, and still not be part of the product a customer
     * is using. See config/features.php.
     */
    private function enabled(?string $module): bool
    {
        return $module === null || (bool) config("features.{$module}", true);
    }

    /**
     * A workspace-level switch that is neither a permission nor a plan feature.
     *
     * Named as a string rather than held as a closure so config stays cacheable.
     * API access, for instance, is enabled per workspace and its page 404s
     * otherwise — offering a tour into a 404 is worse than offering nothing.
     */
    private function tenantAllows(?User $user, ?string $gate): bool
    {
        if ($gate === null) {
            return true;
        }

        $tenant = $user?->currentTenant();

        if ($tenant === null || ! method_exists($tenant, $gate)) {
            return false;
        }

        return (bool) $tenant->{$gate}();
    }

    /**
     * Permission check. Null means everyone; an array means any of them.
     *
     * Workspace owners bypass, matching the rest of the application — an owner
     * holds no explicit role, so a plain can() would hide the whole guide
     * from the one person who can do everything.
     *
     * @param  string|array<int, string>|null  $permission
     */
    private function allows(?User $user, string|array|null $permission): bool
    {
        if ($permission === null || $permission === []) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $tenant = $user->currentTenant();

        if ($tenant && $user->roleInTenant($tenant) === 'owner') {
            return true;
        }

        foreach (Arr::wrap($permission) as $name) {
            if ($user->can($name)) {
                return true;
            }
        }

        return false;
    }
}
