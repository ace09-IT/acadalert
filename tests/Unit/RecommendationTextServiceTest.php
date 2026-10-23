<?php

namespace Tests\Unit;

use App\Services\RecommendationTextService;
use Tests\TestCase;

class RecommendationTextServiceTest extends TestCase
{
    protected RecommendationTextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RecommendationTextService();
    }


    public function test_it_renders_object_actions_in_the_documented_line_format(): void
    {
        $text = $this->service->toText([
            ['action' => 'tutoring', 'details' => 'Schedule one-on-one tutoring.', 'priority' => 'high'],
            ['action' => 'study_group', 'details' => 'Join a study group.', 'priority' => 'low'],
        ]);

        $this->assertSame(
            "[high] Tutoring: Schedule one-on-one tutoring.\n[low] Study group: Join a study group.",
            $text
        );
    }

    public function test_it_renders_plain_string_actions_from_the_seeder(): void
    {
        $text = $this->service->toText([
            'Attend tutoring sessions for struggling subjects',
            'Schedule a meeting with your guidance counselor',
        ]);

        $this->assertSame(
            "[medium] Custom: Attend tutoring sessions for struggling subjects\n"
            . '[medium] Custom: Schedule a meeting with your guidance counselor',
            $text
        );
    }

    public function test_it_decodes_a_json_column(): void
    {
        $json = json_encode([['action' => 'meeting', 'details' => 'Meet the counselor', 'priority' => 'medium']]);

        $this->assertSame('[medium] Meeting: Meet the counselor', $this->service->toText($json));
    }

    public function test_it_treats_a_json_scalar_as_one_action(): void
    {
        $this->assertSame('[medium] Custom: Do better.', $this->service->toText(json_encode('Do better.')));
    }

    public function test_it_normalises_priority_spellings(): void
    {
        $text = $this->service->toText([
            ['action' => 'a', 'details' => 'critical one', 'priority' => 'Critical'],
            ['action' => 'b', 'details' => 'urgent one', 'priority' => 'URGENT'],
            ['action' => 'c', 'details' => 'minor one', 'priority' => 'minor'],
            ['action' => 'd', 'details' => 'nonsense one', 'priority' => 'whatever'],
        ]);

        $this->assertSame(
            "[high] A: critical one\n[high] B: urgent one\n[low] C: minor one\n[medium] D: nonsense one",
            $text
        );
    }

    public function test_garbage_input_yields_no_actions_instead_of_throwing(): void
    {
        foreach ([null, '', 0, false, [], [null], [[]]] as $input) {
            $this->assertSame('', $this->service->toText($input), 'input: ' . var_export($input, true));
            $this->assertTrue($this->service->isEmpty($input));
        }
    }

    public function test_a_plain_sentence_column_is_surfaced_as_one_action(): void
    {
        // A column holding free text (not JSON) must still reach the counselor rather
        // than being silently dropped.
        $this->assertSame(
            '[medium] Custom: not json at all',
            $this->service->toText('not json at all')
        );
        $this->assertFalse($this->service->isEmpty('not json at all'));
    }

    public function test_it_accepts_an_action_only_row(): void
    {
        $this->assertSame(
            '[medium] Custom: Review the study plan',
            $this->service->toText([['action' => 'Review the study plan']])
        );
    }

    public function test_it_parses_the_documented_line_format_back_into_actions(): void
    {
        $actions = $this->service->fromText("[high] Tutoring: One-to-one help\n[low] Attendance: Attend every class");

        $this->assertCount(2, $actions);
        $this->assertSame('tutoring', $actions[0]['action']);
        $this->assertSame('One-to-one help', $actions[0]['details']);
        $this->assertSame('high', $actions[0]['priority']);
        $this->assertSame('low', $actions[1]['priority']);
    }

    public function test_it_parses_bullets_and_bare_lines(): void
    {
        $actions = $this->service->fromText("- Attend all classes\nJust talk to the teacher");

        $this->assertCount(2, $actions);
        $this->assertSame('Attend all classes', $actions[0]['details']);
        $this->assertSame('medium', $actions[0]['priority']);
        $this->assertSame('Just talk to the teacher', $actions[1]['details']);
    }

    public function test_round_trip_preserves_the_sentences(): void
    {
        $actions = [
            ['action' => 'tutoring', 'details' => 'Schedule one-on-one tutoring.', 'priority' => 'high'],
            ['action' => 'parent_conference', 'details' => 'Call the parent.', 'priority' => 'medium'],
        ];

        $roundTripped = $this->service->fromText($this->service->toText($actions));

        $this->assertSame('Schedule one-on-one tutoring.', $roundTripped[0]['details']);
        $this->assertSame('high', $roundTripped[0]['priority']);
        $this->assertSame('Call the parent.', $roundTripped[1]['details']);
    }

    public function test_from_text_of_nothing_is_empty(): void
    {
        $this->assertSame([], $this->service->fromText(null));
        $this->assertSame([], $this->service->fromText('   '));
    }


    public function test_an_edit_always_wins_over_the_generated_text(): void
    {
        $generated = [['action' => 'tutoring', 'details' => 'Generated advice', 'priority' => 'high']];

        $resolved = $this->service->resolveForwardedText($generated, '[high] Tutoring: Edited advice');

        $this->assertTrue($resolved['included']);
        $this->assertTrue($resolved['edited']);
        $this->assertSame('[high] Tutoring: Edited advice', $resolved['text']);
    }

    public function test_the_generated_text_is_forwarded_verbatim_without_an_edit(): void
    {
        $generated = [['action' => 'tutoring', 'details' => 'Generated advice', 'priority' => 'high']];

        $resolved = $this->service->resolveForwardedText($generated, null);

        $this->assertTrue($resolved['included']);
        $this->assertFalse($resolved['edited']);
        $this->assertSame('[high] Tutoring: Generated advice', $resolved['text']);
    }

    public function test_an_edit_equal_to_the_generated_text_is_not_marked_as_edited(): void
    {
        $generated = [['action' => 'tutoring', 'details' => 'Generated advice', 'priority' => 'high']];

        $resolved = $this->service->resolveForwardedText($generated, '[high] Tutoring: Generated advice');

        $this->assertTrue($resolved['included']);
        $this->assertFalse($resolved['edited']);
    }

    public function test_an_edit_alone_is_forwarded_when_no_recommendation_was_generated(): void
    {
        $resolved = $this->service->resolveForwardedText(null, 'Written by hand');

        $this->assertTrue($resolved['included']);
        $this->assertTrue($resolved['edited']);
        $this->assertSame('Written by hand', $resolved['text']);
    }

    public function test_nothing_is_forwarded_when_there_is_nothing_to_forward(): void
    {
        $resolved = $this->service->resolveForwardedText(null, null);

        $this->assertFalse($resolved['included']);
        $this->assertFalse($resolved['edited']);
        $this->assertNull($resolved['text']);
    }

    public function test_whitespace_only_edits_are_ignored(): void
    {
        $generated = [['action' => 'tutoring', 'details' => 'Generated advice', 'priority' => 'high']];

        $resolved = $this->service->resolveForwardedText($generated, "   \n  ");

        $this->assertSame('[high] Tutoring: Generated advice', $resolved['text']);
        $this->assertFalse($resolved['edited']);
    }


    public function test_it_normalises_risk_factors_from_every_known_shape(): void
    {
        $this->assertSame(
            ['Low grades in programming', 'Frequent absences'],
            $this->service->factors(json_encode(['Low grades in programming', 'Frequent absences']))
        );

        $this->assertSame(
            ['Failing grades'],
            $this->service->factors([['factor' => 'Failing grades']])
        );

        $this->assertSame(
            ['Attendance below 80%'],
            $this->service->factors('Attendance below 80%')
        );

        $this->assertSame([], $this->service->factors(null));
        $this->assertSame([], $this->service->factors([null, '']));
    }

    public function test_factors_are_deduplicated_and_trimmed(): void
    {
        $this->assertSame(
            ['Low grades', 'Poor attendance'],
            $this->service->factors(['  Low grades  ', 'Low grades', 'Poor attendance'])
        );
    }
}
