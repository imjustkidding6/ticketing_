<?php

namespace Tests\Unit;

use App\Support\TicketTechnicalFields;
use PHPUnit\Framework\TestCase;

/**
 * P8: the declared technical-detail set.
 *
 * Pure, no database, no container — which is the point of the declaration living in
 * app/Support. These tests are what make "one declared set" structural rather than a claim
 * in a comment: a developer who adds an option to a dropdown without the matching `in:`
 * rule, or who adds an `unknown` member, reds here.
 */
class TicketTechnicalFieldsTest extends TestCase
{
    public function test_the_declared_set_is_the_only_source_of_required_fields(): void
    {
        $internal = TicketTechnicalFields::rules(internal: true);

        foreach (['impact_scope', 'workaround', 'environment'] as $key) {
            $this->assertContains('required', $internal[$key], "{$key} is required internally");
            $this->assertNotContains('nullable', $internal[$key]);
        }

        foreach (['steps_to_reproduce', 'error_text'] as $key) {
            $this->assertContains('nullable', $internal[$key], "{$key} is prompted, never required");
        }

        // The public form can never be stricter than the internal one, by construction.
        foreach (TicketTechnicalFields::rules(internal: false) as $key => $rules) {
            $this->assertContains('nullable', $rules, "{$key} is nullable on the public/update surface");
            $this->assertNotContains('required', $rules);
        }
    }

    public function test_every_declared_select_validates_against_its_own_declared_options(): void
    {
        $selects = 0;

        foreach (TicketTechnicalFields::FIELDS as $key => $field) {
            if ($field['shape'] !== 'select') {
                continue;
            }

            $selects++;
            $in = null;

            foreach ($field['rules'] as $rule) {
                if (str_starts_with($rule, 'in:')) {
                    $in = explode(',', substr($rule, 3));
                }
            }

            $this->assertNotNull($in, "{$key} must be validated against a closed set");
            $this->assertSame(array_keys($field['options']), $in, "{$key}'s in: rule must list exactly its declared options");

            // No `unknown` member anywhere: blank IS unknown, it validates as nullable, and
            // it emits no prompt line at all. A stored `unknown` is a stated fact about the
            // state of knowledge, which a model reasons about separately and which breaks
            // PRIORITY_UNCERTAINTY's downward resolution.
            $this->assertArrayNotHasKey('unknown', $field['options']);
            $this->assertSame(array_keys($field['options']), array_keys($field['facts']));
        }

        $this->assertSame(3, $selects);
    }

    public function test_missing_names_why_a_field_matters_only_where_it_is_a_rubric_input(): void
    {
        $rows = TicketTechnicalFields::missing([]);

        $this->assertCount(5, $rows);

        $byKey = array_column($rows, null, 'key');

        $this->assertTrue($byKey['impact_scope']['rubric_input']);
        $this->assertTrue($byKey['workaround']['rubric_input']);
        $this->assertTrue($byKey['environment']['rubric_input']);
        $this->assertFalse($byKey['steps_to_reproduce']['rubric_input']);
        $this->assertFalse($byKey['error_text']['rubric_input']);

        // A rubric input is named as a CONSEQUENCE FOR THE PRIORITY, which is what makes an
        // engineer fill it in.
        $this->assertStringContainsString('Critical needs the scope', $byKey['impact_scope']['why']);
        $this->assertStringContainsString('workaround is high, not critical', $byKey['workaround']['why']);
        $this->assertStringContainsString('NOT production', $byKey['environment']['why']);
    }

    public function test_a_fully_captured_ticket_has_nothing_missing(): void
    {
        $this->assertSame([], TicketTechnicalFields::missing([
            'impact_scope' => 'everyone',
            'workaround' => 'none',
            'environment' => 'production',
            'steps_to_reproduce' => 'Open the app, click Reports.',
            'error_text' => 'HTTP 500',
        ]));
    }

    public function test_the_fact_block_emits_closed_set_fields_only_and_never_the_free_text(): void
    {
        $block = TicketTechnicalFields::factBlock([
            'impact_scope' => 'department',
            'workaround' => 'none',
            'environment' => 'production',
            'steps_to_reproduce' => 'SECRET-STEPS-MARKER',
            'error_text' => 'SECRET-ERROR-MARKER',
        ]);

        $this->assertStringContainsString('REPORTED TECHNICAL FACTS', $block);
        $this->assertStringContainsString('impact_scope: department (a whole department or site is affected)', $block);
        $this->assertStringContainsString('workaround: none (no workaround exists)', $block);
        $this->assertStringContainsString('environment: production (the live production environment)', $block);

        // The free text goes through the SCREENED ticket text instead, so this block can
        // never carry an injected instruction and a reviewer can verify that by reading
        // factBlock() alone.
        $this->assertStringNotContainsString('SECRET-STEPS-MARKER', $block);
        $this->assertStringNotContainsString('SECRET-ERROR-MARKER', $block);
    }

    public function test_an_absent_fact_emits_no_line_and_no_unknown_placeholder(): void
    {
        $block = TicketTechnicalFields::factBlock(['workaround' => 'partial']);

        $this->assertStringContainsString('workaround: partial', $block);
        $this->assertStringNotContainsString('environment', $block);
        $this->assertStringNotContainsString('impact_scope', $block);
        // THE PRIORITY_UNCERTAINTY test. Its mechanism is silence; a line saying "unknown"
        // is not silence.
        $this->assertStringNotContainsString('unknown', $block);
        $this->assertStringNotContainsString('not stated', mb_strtolower(explode("\n", $block)[3] ?? ''));
    }

    public function test_nothing_captured_yields_an_empty_fact_block(): void
    {
        $this->assertSame('', TicketTechnicalFields::factBlock([]));
        $this->assertSame('', TicketTechnicalFields::factBlock([
            // An invalid value never reaches the column (`in:`), and would not be emitted.
            'impact_scope' => 'everybody',
            'environment' => '',
        ]));
    }
}
