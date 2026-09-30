<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiAgentSkill;
use App\Models\DepartmentAiAgent;
use Database\Seeders\Catalogue\AgentCatalogue;
use Database\Seeders\Catalogue\SkillCatalogue;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue is 1,300 lines of transcribed data, so the realistic failure is a typo
 * rather than a logic error. These read the two constants and touch no database, so a
 * mistyped skill slug is a red build rather than an exception in a customer's install.
 */
class AiAgentCatalogueIntegrityTest extends TestCase
{
    public function test_every_skill_slug_is_unique(): void
    {
        $slugs = array_column(SkillCatalogue::SKILLS, 'slug');

        // The spec repeats thirteen skills across its department sections (Root Cause
        // Analysis, Intent Detection, Sentiment Analysis, …); §2/§3 want one row each.
        $this->assertSame(array_unique($slugs), $slugs, 'A skill slug appears twice in the catalogue.');
    }

    public function test_every_skill_slug_is_the_slug_of_its_name(): void
    {
        foreach (SkillCatalogue::SKILLS as $skill) {
            $this->assertSame(
                Str::slug($skill['name']),
                $skill['slug'],
                "Skill [{$skill['name']}] does not carry Str::slug() of its own name.",
            );
        }
    }

    public function test_every_skill_category_is_one_of_the_declared_categories(): void
    {
        foreach (SkillCatalogue::SKILLS as $skill) {
            $this->assertContains(
                $skill['skill_category'],
                SkillCatalogue::CATEGORIES,
                "Skill [{$skill['name']}] is in an undeclared category.",
            );
        }
    }

    public function test_every_declared_category_is_used(): void
    {
        // An unused category is a dead option in P3's filter.
        foreach (SkillCatalogue::CATEGORIES as $category) {
            $this->assertContains($category, array_column(SkillCatalogue::SKILLS, 'skill_category'));
        }
    }

    public function test_by_slug_finds_a_skill_and_returns_null_for_an_unknown_one(): void
    {
        $this->assertSame('MySQL', SkillCatalogue::bySlug('mysql')['name']);
        $this->assertNull(SkillCatalogue::bySlug('no-such-skill'));
    }

    public function test_there_are_forty_eight_agents_with_unique_slugs(): void
    {
        $slugs = array_column(AgentCatalogue::AGENTS, 'slug');

        $this->assertCount(48, AgentCatalogue::AGENTS);
        $this->assertSame(array_unique($slugs), $slugs, 'An agent slug appears twice in the catalogue.');
    }

    public function test_every_skill_referenced_by_an_agent_exists_in_the_skill_catalogue(): void
    {
        $known = array_column(SkillCatalogue::SKILLS, 'slug');

        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertNotEmpty($agent['skills'], "Agent [{$agent['name']}] holds no skills.");
            $this->assertSame(
                array_unique($agent['skills']),
                $agent['skills'],
                "Agent [{$agent['name']}] lists a skill twice.",
            );

            foreach ($agent['skills'] as $slug) {
                $this->assertContains(
                    $slug,
                    $known,
                    "Agent [{$agent['name']}] references unknown skill [{$slug}].",
                );
            }
        }
    }

    public function test_every_agent_role_type_is_one_the_model_knows(): void
    {
        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertContains($agent['role_type'], AiAgent::ROLE_TYPES);
        }
    }

    public function test_every_department_role_is_one_the_pivot_knows_and_matches_the_role_map(): void
    {
        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertContains($agent['department_role'], DepartmentAiAgent::ROLES);
            $this->assertSame(
                AgentCatalogue::ROLE_TO_DEPARTMENT_ROLE[$agent['role_type']],
                $agent['department_role'],
                "Agent [{$agent['name']}] disagrees with the role map.",
            );
        }
    }

    public function test_every_assignment_priority_matches_the_role_priority_map(): void
    {
        // §8's worked example: Software Triage 1, Debugger 20, QA 90.
        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertSame(
                AgentCatalogue::ROLE_PRIORITIES[$agent['role_type']],
                $agent['priority'],
                "Agent [{$agent['name']}] is not at its role's priority.",
            );
        }
    }

    /**
     * The seven pre-existing departments must keep the sort_order the live rows already
     * hold, and the two added in 2026-09 must append after them.
     *
     * DepartmentSeeder::run() loops every tenant with updateOrCreate, so a changed
     * sort_order here is not a reordering of a constant — it is a write to the
     * departments of every live workspace on the next db:seed. The feature-test guard
     * captures its expectation FROM this same constant and so cannot see that: set
     * 'HR' => 1 and both sides of its assertSame agree while the live rows are
     * renumbered. This asserts the literal map, which is the only form that bites.
     */
    public function test_the_default_department_sort_order_map_is_the_literal_expected_one(): void
    {
        $actual = [];

        foreach (DepartmentSeeder::DEFAULTS as $department) {
            $actual[$department['code']] = $department['sort_order'];
        }

        ksort($actual);

        $expected = [
            'HR' => 0,
            'PROC' => 1,
            'SOFTWARE' => 2,
            'HARDWARE' => 3,
            'SALES' => 4,
            'CS' => 5,
            'OTHERS' => 6,
            'OPS' => 7,
            'PRESALES' => 8,
        ];
        ksort($expected);

        // ksort both: the assertion is about which code holds which value, not the order
        // DEFAULTS happens to list them in.
        $this->assertSame($expected, $actual, 'Changing a default department sort_order rewrites live rows in every workspace on the next db:seed.');
    }

    public function test_every_department_code_exists_in_the_department_defaults(): void
    {
        $codes = array_column(DepartmentSeeder::DEFAULTS, 'code');

        foreach (AgentCatalogue::departmentCodes() as $code) {
            $this->assertContains($code, $codes, "The catalogue expects a department [{$code}] that is not a default.");
        }

        // All nine departments of §1 receive agents; a default with none would be an
        // empty AI Agents tab with nothing to explain it.
        $this->assertCount(count($codes), AgentCatalogue::departmentCodes());
    }

    public function test_exactly_one_agent_per_department_is_auto_invoke_and_it_is_the_triage_agent(): void
    {
        foreach (AgentCatalogue::departmentCodes() as $code) {
            $auto = array_filter(
                AgentCatalogue::forDepartmentCode($code),
                fn (array $agent): bool => $agent['auto_invoke'],
            );

            $this->assertCount(1, $auto, "Department [{$code}] does not have exactly one auto-invoke agent.");

            $agent = reset($auto);
            $this->assertSame('triage', $agent['role_type']);
            // is_default follows auto_invoke: the agent a department falls back to.
            $this->assertTrue($agent['is_default']);
        }

        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertSame($agent['role_type'] === 'triage', $agent['auto_invoke']);
            $this->assertSame($agent['role_type'] === 'triage', $agent['is_default']);
        }
    }

    public function test_no_agent_carries_a_system_prompt(): void
    {
        // §28's default stays the single source, so a wording fix reaches every agent
        // nobody has edited. A prompt copied into 48 rows would freeze at seed time.
        foreach (AgentCatalogue::AGENTS as $agent) {
            $this->assertArrayNotHasKey('system_prompt', $agent);
        }
    }

    public function test_the_seeded_proficiency_is_one_the_pivot_knows(): void
    {
        $this->assertContains('advanced', AiAgentSkill::PROFICIENCIES);
    }

    public function test_technical_software_holds_the_eight_agents_of_the_expected_result(): void
    {
        // §39, read from the data rather than from a query.
        $this->assertSame([
            'software-triage-agent',
            'software-engineer-agent',
            'debugger-agent',
            'database-engineer-agent',
            'devops-agent',
            'qa-engineer-agent',
            'security-agent',
            'documentation-agent',
        ], array_column(AgentCatalogue::forDepartmentCode('SOFTWARE'), 'slug'));
    }
}
