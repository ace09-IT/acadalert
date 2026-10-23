<?php

namespace App\Services;

class RecommendationCondenser
{
    public const MAX_ACTIONS = 3;

    public const MAX_HIGH = 2;

    public const MAX_DETAILS = 120;

    public const MIN_DETAILS = 15;

    public const MIN_STRIPPED_DETAILS = 45;

    protected const FAMILIES = [
        'tutoring' => [
            'tutoring',
            'peer_tutoring',
            'academic_coaching',
            'academic_advising',
            'remediation',
            'tutorial',
            'study_group',
        ],
        'counseling' => [
            'counseling',
            'counselling',
            'stress_management',
        ],
        'study_skills' => [
            'study_skills',
            'study_habits',
            'time_management',
            'workshop',
        ],
        'attendance_monitoring' => [
            'attendance_monitoring',
            'attendance',
        ],
        'parent_meeting' => [
            'parent_meeting',
            'parent_conference',
        ],
        'career_guidance' => [
            'career_guidance',
        ],
    ];

    protected const JUSTIFICATION_PATTERN = '/[,\s]+(?:due to|because|since|given that|'
        . 'considering that|owing to|so that|which|'
        . 'as (?:he|she|they|the|this|his|her|their)|'
        . 'to (?:discuss|address|prevent|review|ensure|support|improve|monitor|assess|'
        . 'evaluate|determine|help|provide|remediate|recover))\b.*$/i';

    /** Words that must never be the last word of a truncated instruction. */
    protected const DANGLING_WORDS = [
        'and', 'or', 'but', 'to', 'for', 'with', 'of', 'in', 'on', 'at', 'by',
        'the', 'a', 'an', 'as', 'due', 'because', 'that', 'this', 'from',
        'until', 'their', 'his', 'her', 'is', 'are', 'was', 'were', 'between',
        'about', 'regarding', 'against', 'into', 'over', 'under', 'across',
        'within', 'without', 'during', 'toward', 'towards', 'than', 'per',
    ];

    protected RecommendationTextService $text;

    public function __construct(?RecommendationTextService $text = null)
    {
        $this->text = $text ?? new RecommendationTextService();
    }

    public function condense($value): array
    {
        $actions = $this->text->actions($value);

        if ($actions === []) {
            return [];
        }

        $rank = array_flip(RecommendationTextService::PRIORITIES);
        $kept = [];
        $seenFamilies = [];
        $highCount = 0;

        foreach ($actions as $action) {
            $family = $this->family($action['action']);

            if (isset($seenFamilies[$family])) {
                continue;
            }

            $details = $this->condenseDetails($action['details']);

            // Nothing actionable left — better an absent action than a stored
            // sentence that only repeats a grade.
            if ($details === '') {
                continue;
            }

            $priority = $action['priority'];

            if ($priority === 'high') {
                if ($highCount >= self::MAX_HIGH) {
                    $priority = 'medium';
                } else {
                    $highCount++;
                }
            }

            if ($family !== 'custom') {
                $seenFamilies[$family] = true;
            }

            $kept[] = [
                'action' => $family,
                'details' => $details,
                'priority' => $priority,
            ];
        }

        if ($kept === []) {
            return [[
                'action' => $this->family($actions[0]['action']),
                'details' => $this->condenseDetails($actions[0]['details'], true),
                'priority' => $actions[0]['priority'],
            ]];
        }

        // Stable sort (PHP 8) by priority, so the slice below keeps the most
        // urgent actions and the page always reads most-to-least important.
        usort($kept, fn (array $a, array $b) => ($rank[$a['priority']] ?? 1) <=> ($rank[$b['priority']] ?? 1));

        return array_slice($kept, 0, self::MAX_ACTIONS);
    }

    protected function family(string $label): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));

        foreach (self::FAMILIES as $family => $labels) {
            if (in_array($slug, $labels, true)) {
                return $family;
            }
        }

        // Hand-written and seeded labels ('custom', 'meeting', ...) keep their own
        // identity — the Academic Head's wording is never rewritten.
        return $slug === '' ? 'custom' : $slug;
    }

    protected function condenseDetails(string $details, bool $keepShort = false): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $details));

        $text = trim((string) (preg_split('/(?<=[.!?])\s+/', $text, 2)[0] ?? $text));

        // Only an over-budget sentence is rewritten, so a lean instruction — the
        // prompt asks for 90 characters — passes through untouched.
        if (mb_strlen($text) > self::MAX_DETAILS) {
            $stripped = $this->stripJustification($text);

            if (mb_strlen($stripped) >= self::MIN_STRIPPED_DETAILS) {
                $text = $this->closeSentence($stripped);
            }

            if (mb_strlen($text) > self::MAX_DETAILS) {
                $text = $this->truncateAtWord($text, self::MAX_DETAILS);
            }
        }

        if (!$keepShort && mb_strlen($text) < self::MIN_DETAILS) {
            return '';
        }

        return $text;
    }

    protected function stripJustification(string $text): string
    {
        $stripped = preg_replace(self::JUSTIFICATION_PATTERN, '', $text);

        return rtrim(trim((string) $stripped), " ,;:-");
    }

    protected function closeSentence(string $text): string
    {
        $text = rtrim($text, " ,;:-");

        if ($text === '') {
            return '';
        }

        return preg_match('/[.!?]$/', $text) === 1 ? $text : $text . '.';
    }

    protected function truncateAtWord(string $text, int $limit): string
    {
        $cut = mb_substr($text, 0, $limit);

        if (mb_strlen($text) > $limit) {
            $lastSpace = mb_strrpos($cut, ' ');

            if ($lastSpace !== false && $lastSpace > 0) {
                $cut = mb_substr($cut, 0, $lastSpace);
            }
        }

        $cut = rtrim($cut, " ,;:-");

        $whole = $this->dropDanglingWords($cut);

        // Never truncate the instruction away entirely.
        $cut = $whole === '' ? $cut : $whole;

        if ($cut === '') {
            return '';
        }

        return rtrim($cut, " ,;:-.") . '.';
    }

    protected function dropDanglingWords(string $text): string
    {
        $words = explode(' ', $text);

        while ($words !== []) {
            $last = strtolower(trim((string) end($words), " ,;:-."));

            if (!in_array($last, self::DANGLING_WORDS, true)) {
                break;
            }

            array_pop($words);
        }

        return trim(implode(' ', $words), " ,;:-");
    }
}
