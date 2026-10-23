<?php

namespace Tests\Unit;

use App\Services\RecommendationCondenser;
use App\Services\RecommendationTextService;
use Tests\TestCase;

class RecommendationCondenserTest extends TestCase
{
    protected RecommendationCondenser $condenser;
    protected RecommendationTextService $text;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condenser = new RecommendationCondenser();
        $this->text = new RecommendationTextService();
    }

    protected function verboseRow(): array
    {
        return [
            [
                'action' => 'tutoring',
                'priority' => 'high',
                'details' => 'Mandatory intensive tutoring for CSC106 (Database) and CSC102 (Computer Systems) due to critical failure grades (25 and 38). Focus on foundational concept remediation.',
            ],
            [
                'action' => 'academic_advising',
                'priority' => 'high',
                'details' => 'Immediate intervention to discuss the 65.38% average and potential shift in study strategies for the 5 failing subjects. Reviewing the load for the next semester is critical.',
            ],
            [
                'action' => 'parent_meeting',
                'priority' => 'high',
                'details' => 'Formal meeting with student and parents/guardians to discuss the high-risk status and the severe discrepancy between high performance in programming (87, 78) and low performance in theory-based subjects.',
            ],
            [
                'action' => 'study_skills',
                'priority' => 'medium',
                'details' => 'Workshop on study habits specifically for theory-heavy technical subjects, as the student displays a clear aptitude for practical programming (CSP101/102) but struggles with conceptual subjects like CSC106 and CSC102.',
            ],
            [
                'action' => 'academic_coaching',
                'priority' => 'medium',
                'details' => 'Coaching session to address the near-passing grades (70-74) in Calculus 1, 2, and Discrete Structures to prevent further drops in core mathematical foundations.',
            ],
            [
                'action' => 'counseling',
                'priority' => 'medium',
                'details' => 'Assessment of potential external factors affecting performance, given that the student maintains excellent attendance (97.06%) but is failing 62.5% of their course load.',
            ],
        ];
    }


    public function test_it_condenses_a_real_six_action_row_into_three_short_steps(): void
    {
        $raw = $this->verboseRow();
        $condensed = $this->condenser->condense($raw);

        $this->assertCount(3, $condensed);
        $this->assertSame(
            "[high] Tutoring: Mandatory intensive tutoring for CSC106 (Database) and CSC102 (Computer Systems).\n"
            . "[high] Parent meeting: Formal meeting with student and parents/guardians.\n"
            . '[medium] Study skills: Workshop on study habits specifically for theory-heavy technical subjects.',
            $this->text->toText($condensed)
        );

        $before = mb_strlen($this->text->toText($raw));
        $after = mb_strlen($this->text->toText($condensed));

        $this->assertGreaterThan(1200, $before);
        $this->assertLessThan($before / 3, $after);
    }

    public function test_no_condensed_line_restates_the_grades_or_attendance(): void
    {
        $condensed = $this->condenser->condense($this->verboseRow());
        $text = $this->text->toText($condensed);

        foreach (['due to', 'because', 'given that', 'which', 'as the student', '65.38', '62.5', '97.06'] as $verboten) {
            $this->assertStringNotContainsStringIgnoringCase($verboten, $text);
        }
    }

    public function test_every_condensed_line_is_one_short_sentence(): void
    {
        foreach ($this->condenser->condense($this->verboseRow()) as $action) {
            $this->assertLessThanOrEqual(RecommendationCondenser::MAX_DETAILS, mb_strlen($action['details']));
            $this->assertSame(1, preg_match('/^[A-Z].*[.!?]$/', $action['details']), $action['details']);
            $this->assertSame(1, substr_count($action['details'], '.'), $action['details']);
        }
    }


    public function test_it_collapses_the_four_labels_for_one_kind_of_help(): void
    {
        $condensed = $this->condenser->condense([
            ['action' => 'academic_advising', 'priority' => 'high', 'details' => 'Review the study plan with the student.'],
            ['action' => 'academic_coaching', 'priority' => 'medium', 'details' => 'Coach the student on exam technique.'],
            ['action' => 'peer_tutoring', 'priority' => 'medium', 'details' => 'Pair the student with a peer mentor.'],
            ['action' => 'tutoring', 'priority' => 'medium', 'details' => 'Tutoring for CSC102.'],
        ]);

        $this->assertCount(1, $condensed);
        $this->assertSame('tutoring', $condensed[0]['action']);
        $this->assertSame('Review the study plan with the student.', $condensed[0]['details']);
        $this->assertSame('high', $condensed[0]['priority']);
    }

    public function test_it_demotes_a_third_high_priority_action(): void
    {
        $condensed = $this->condenser->condense([
            ['action' => 'tutoring', 'priority' => 'high', 'details' => 'Twice-weekly tutoring for CSC102 until the grade reaches 75.'],
            ['action' => 'parent_meeting', 'priority' => 'high', 'details' => 'Meet the guardians this week on the failing subjects.'],
            ['action' => 'counseling', 'priority' => 'high', 'details' => 'Book a counseling session this week.'],
        ]);

        $this->assertSame(['high', 'high', 'medium'], array_column($condensed, 'priority'));
    }

    public function test_it_caps_seven_actions_to_the_three_most_urgent(): void
    {
        $condensed = $this->condenser->condense([
            ['action' => 'career_guidance', 'priority' => 'low', 'details' => 'Suggest a career talk for the student next month.'],
            ['action' => 'attendance_monitoring', 'priority' => 'low', 'details' => 'Check the attendance log every Friday.'],
            ['action' => 'study_skills', 'priority' => 'medium', 'details' => 'Refer the student to the study-skills workshop.'],
            ['action' => 'parent_meeting', 'priority' => 'high', 'details' => 'Meet the guardians this week on the failing subjects.'],
            ['action' => 'tutoring', 'priority' => 'high', 'details' => 'Twice-weekly tutoring for CSC102 until the grade reaches 75.'],
            ['action' => 'counseling', 'priority' => 'high', 'details' => 'Book a counseling session this week.'],
            ['action' => 'peer_tutoring', 'priority' => 'high', 'details' => 'Pair the student with a peer mentor for CSC106.'],
        ]);

        // Unsorted input: the top band is kept and the page reads most urgent first.
        $this->assertSame(['parent_meeting', 'tutoring', 'study_skills'], array_column($condensed, 'action'));
        $this->assertSame(['high', 'high', 'medium'], array_column($condensed, 'priority'));
    }

    public function test_a_sentence_with_no_reason_clause_is_truncated_on_a_word_boundary(): void
    {
        $condensed = $this->condenser->condense([[
            'action' => 'attendance_monitoring',
            'priority' => 'high',
            'details' => 'Arrange a structured weekly check-in with the student covering every enrolled subject and every assessment deadline for the remainder of the term with written notes filed afterwards.',
        ]]);

        $this->assertSame(
            'Arrange a structured weekly check-in with the student covering every enrolled subject and every assessment deadline.',
            $condensed[0]['details']
        );
        $this->assertLessThanOrEqual(RecommendationCondenser::MAX_DETAILS, mb_strlen($condensed[0]['details']));
        // Never cut on a dangling preposition ("... deadline for.").
        $this->assertDoesNotMatchRegularExpression('/(and|or|for|with|of|between|the|to)\.$/', $condensed[0]['details']);
    }


    public function test_a_lean_response_passes_through_byte_identical(): void
    {
        // What the prompt asks for. The Academic Head must see exactly this.
        $lean = [
            ['action' => 'tutoring', 'details' => 'Twice-weekly tutoring for CSC106 and CSC102 until grades reach 75.', 'priority' => 'high'],
            ['action' => 'parent_meeting', 'details' => 'Meet the guardians this week on the 5 failing subjects.', 'priority' => 'high'],
            ['action' => 'study_skills', 'details' => 'Refer to the study-skills workshop for theory-heavy subjects.', 'priority' => 'medium'],
        ];

        $this->assertSame($lean, $this->condenser->condense($lean));
        $this->assertSame($this->text->toText($lean), $this->text->toText($this->condenser->condense($lean)));
    }

    public function test_the_condensed_actions_round_trip_through_the_edited_text_format(): void
    {
        $condensed = $this->condenser->condense($this->verboseRow());

        // The Academic Head edits the text form; parsing it back must be lossless.
        $this->assertSame($condensed, $this->text->fromText($this->text->toText($condensed)));
    }

    public function test_it_keeps_hand_written_and_seeded_actions(): void
    {
        // Two seeded bare strings: the fallback 'custom' label carries no meaning,
        // so it can never prove that two actions are the same kind of help.
        $seeded = $this->condenser->condense([
            'Attend tutoring sessions for struggling subjects',
            'Schedule a meeting with your guidance counselor',
        ]);

        $this->assertCount(2, $seeded);
        $this->assertSame(['custom', 'custom'], array_column($seeded, 'action'));

        $handWritten = $this->condenser->condense([
            ['action' => 'custom', 'priority' => 'medium', 'details' => 'Bring the printed grade slip to the registrar.'],
        ]);

        $this->assertSame('custom', $handWritten[0]['action']);
        $this->assertSame('Bring the printed grade slip to the registrar.', $handWritten[0]['details']);
    }

    public function test_it_never_returns_nothing_for_a_non_empty_input(): void
    {
        $condensed = $this->condenser->condense([
            ['action' => 'tutoring', 'priority' => 'high', 'details' => 'Retake.'],
            ['action' => 'counseling', 'priority' => 'low', 'details' => 'Talk.'],
        ]);

        $this->assertCount(1, $condensed);
        $this->assertSame('Retake.', $condensed[0]['details']);
        $this->assertSame('high', $condensed[0]['priority']);
    }

    public function test_garbage_input_yields_no_actions_instead_of_throwing(): void
    {
        foreach ([null, '', 0, false, [], [null], [[]]] as $input) {
            $this->assertSame([], $this->condenser->condense($input), 'input: ' . var_export($input, true));
        }
    }

    public function test_the_output_keeps_the_stored_column_shape(): void
    {
        foreach ($this->condenser->condense(json_encode($this->verboseRow())) as $action) {
            $this->assertSame(['action', 'details', 'priority'], array_keys($action));
            $this->assertIsString($action['action']);
            $this->assertIsString($action['details']);
            $this->assertContains($action['priority'], RecommendationTextService::PRIORITIES);
        }
    }
}
