<?php

namespace Tests\Unit;

use App\Services\AI\AnalyzeTicketService;
use App\Support\Ai\AgentRecommendation;
use PHPUnit\Framework\TestCase;

/**
 * "One prompt, not two" and "one rubric, not two", as tests rather than as claims.
 *
 * The brief forbids a second agent prompt and a second selection rule, and the amendment
 * forbids a second rubric. Both are shared public builders on AnalyzeTicketService, so a
 * drift shows up here as a changed string rather than as two surfaces quietly reasoning
 * differently about the same department's expertise.
 *
 * A pure unit test: both builders are static, take arrays and touch no database.
 */
class AgentInstructionLinesTest extends TestCase
{
    public function test_the_agent_instruction_block_names_the_priority_judgement_and_each_agent(): void
    {
        $lines = AnalyzeTicketService::agentInstructionLines([
            new AgentRecommendation(
                agentId: 7,
                name: 'Software Triage Agent',
                roleType: 'triage',
                systemPrompt: 'Weigh printer faults by site impact.',
                score: 0.0,
                matchedSkills: ['Printers'],
            ),
        ]);

        $this->assertCount(2, $lines);
        $this->assertSame(
            'The following specialist instructions come from the AI agents this department has '
                .'configured. Apply them within your analysis, INCLUDING when you judge the priority — '
                .'their expertise is what tells you how much impact this class of problem really has:',
            $lines[0],
        );
        $this->assertSame(
            '- Software Triage Agent [triage] (relevant skills: Printers): Weigh printer faults by site impact.',
            $lines[1],
        );
    }

    public function test_no_recommendations_produces_no_lines(): void
    {
        // The empty-list contract both callers rely on: no department, or a department
        // belonging to another workspace, must append NOTHING rather than a dangling
        // header promising specialist instructions that are not there.
        $this->assertSame([], AnalyzeTicketService::agentInstructionLines([]));
    }

    public function test_the_rubric_describes_only_the_levels_it_is_given_and_needs_at_least_two(): void
    {
        $this->assertSame('', AnalyzeTicketService::priorityRubric([]));
        $this->assertSame('', AnalyzeTicketService::priorityRubric(['medium']));

        $rubric = AnalyzeTicketService::priorityRubric(
            ['low', 'medium', 'high', 'critical'],
            ['critical' => ['response' => 2, 'resolution' => 8]],
        );

        // Highest-first: the reader of a rubric needs the ceiling and its gates first.
        $this->assertStringStartsWith("PRIORITY RUBRIC\n", $rubric);
        $this->assertLessThan(strpos($rubric, '- low'), strpos($rubric, '- critical'));
        // Hours only where a policy supplied them; never invented for the rest.
        $this->assertStringContainsString('- critical (this workspace commits to respond within 2h and resolve within 8h)', $rubric);
        $this->assertStringContainsString("\n- high: ONLY IF a team", $rubric);
        $this->assertStringContainsString(AnalyzeTicketService::PRIORITY_BOUNDARY, $rubric);
        $this->assertStringContainsString(AnalyzeTicketService::PRIORITY_UNCERTAINTY, $rubric);
    }
}
