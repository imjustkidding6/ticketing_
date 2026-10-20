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
        $internal = TicketTechnicalFields::rules(internal: true, technical: true);

        foreach (['impact_scope', 'workaround', 'environment'] as $key) {
            $this->assertContains('required', $internal[$key], "{$key} is required internally");
            $this->assertNotContains('nullable', $internal[$key]);
        }

        foreach (['steps_to_reproduce', 'error_text'] as $key) {
            $this->assertContains('nullable', $internal[$key], "{$key} is prompted, never required");
        }

        // The public form can never be stricter than the internal one, by construction.
        foreach (TicketTechnicalFields::rules(internal: false, technical: true) as $key => $rules) {
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

    public function test_only_the_universal_pair_is_required_in_a_non_technical_department(): void
    {
        $rules = TicketTechnicalFields::rules(internal: true, technical: false);

        // An HR payroll query or "provision a laptop for a new starter" has no environment,
        // and requiring one teaches the agent to pick a value to clear the form.
        $this->assertContains('required', $rules['impact_scope']);
        $this->assertContains('required', $rules['workaround']);
        $this->assertContains('nullable', $rules['environment']);

        // Still VALIDATED though: a stray value is refused rather than waved through to the
        // column and the prompt.
        $this->assertContains('in:production,staging,test,development', $rules['environment']);
    }

    public function test_applicability_is_declared_once_and_splits_the_set_in_two(): void
    {
        $all = TicketTechnicalFields::applicable(technical: false);
        $technical = TicketTechnicalFields::applicable(technical: true);

        $this->assertSame(['impact_scope', 'workaround'], array_keys($all));
        $this->assertSame(TicketTechnicalFields::keys(), array_keys($technical));

        // Breadth and workaround are asked by default in every department because they are
        // what PRIORITY_BOUNDARY judges critical on. A department can now opt out (P9),
        // which is tested below; the rubric itself does not become department-specific.
        foreach ($all as $field) {
            $this->assertTrue($field['rubric_input']);
        }
    }

    public function test_a_non_technical_ticket_is_never_told_it_is_missing_an_environment(): void
    {
        $rows = TicketTechnicalFields::missing([], technical: false);

        $this->assertSame(['impact_scope', 'workaround'], array_column($rows, 'key'));
    }

    public function test_the_fact_block_omits_a_field_the_department_never_asked_for(): void
    {
        // A value can exist on the row (the department was unticked later, or the ticket was
        // moved) and still must not be stated as a fact the reporter was asked for.
        $block = TicketTechnicalFields::factBlock([
            'impact_scope' => 'everyone',
            'workaround' => 'none',
            'environment' => 'production',
        ], technical: false);

        $this->assertStringContainsString('impact_scope: everyone', $block);
        $this->assertStringContainsString('workaround: none', $block);
        $this->assertStringNotContainsString('environment', $block);
    }

    public function test_the_impact_group_is_switched_off_as_one_declaration_not_a_second_list(): void
    {
        $both = TicketTechnicalFields::applicable(technical: true, asksImpact: false);
        $neither = TicketTechnicalFields::applicable(technical: false, asksImpact: false);

        // The two gates are independent: a technical department that does not want the
        // impact questions keeps exactly the technical half.
        $this->assertSame(['environment', 'steps_to_reproduce', 'error_text'], array_keys($both));

        // And a department that asks neither applies NO declared field at all — which is
        // what lets every surface drop its heading instead of rendering an empty section.
        $this->assertSame([], array_keys($neither));
    }

    public function test_the_impact_pair_is_still_validated_where_it_does_not_apply(): void
    {
        $rules = TicketTechnicalFields::rules(internal: true, technical: false, asksImpact: false);

        // ONE mechanism for both groups: `required` drops, the closed-set rule stands. A
        // stray `impact_scope` is refused exactly as a stray `environment` on a Sales
        // ticket is, rather than being waved through to the column and the prompt.
        $this->assertContains('nullable', $rules['impact_scope']);
        $this->assertContains('nullable', $rules['workaround']);
        $this->assertContains('in:one_person,team,department,everyone', $rules['impact_scope']);
        $this->assertContains('in:none,partial,yes', $rules['workaround']);

        // Nothing is required anywhere when nothing applies.
        foreach ($rules as $fieldRules) {
            $this->assertNotContains('required', $fieldRules);
        }
    }

    public function test_a_department_that_does_not_ask_the_impact_pair_is_never_told_it_is_missing_one(): void
    {
        // All THREE technical fields, not just the required one: P8's contract is that
        // `missing()` lists every applicable field with no value, optional ones included
        // (its own test asserts 5 rows for a wholly empty technical ticket). The list is a
        // prompt for what would help, not a second rendering of the validation rules.
        $this->assertSame(
            ['environment', 'steps_to_reproduce', 'error_text'],
            array_column(TicketTechnicalFields::missing([], technical: true, asksImpact: false), 'key'),
        );

        // Both groups off: no rows at all, so no "information needed" heading is rendered.
        $this->assertSame([], TicketTechnicalFields::missing([], technical: false, asksImpact: false));
    }

    public function test_the_fact_block_emits_nothing_at_all_for_a_switched_off_impact_group(): void
    {
        // The P9 cost, pinned: the rubric's two decisive inputs are SILENT rather than
        // stated as unknown, so PRIORITY_UNCERTAINTY resolves both downward.
        $this->assertSame('', TicketTechnicalFields::factBlock([
            'impact_scope' => 'everyone',
            'workaround' => 'none',
        ], technical: false, asksImpact: false));

        // Not an empty section and not an `unknown` line — the block is absent entirely
        // even though the technical half is answered.
        $block = TicketTechnicalFields::factBlock([
            'impact_scope' => 'everyone',
            'workaround' => 'none',
            'environment' => 'production',
        ], technical: true, asksImpact: false);

        $this->assertStringContainsString('environment: production', $block);
        $this->assertStringNotContainsString('impact_scope', $block);
        $this->assertStringNotContainsString('workaround', $block);
        $this->assertStringNotContainsString('unknown', $block);
    }
}
