<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RiskThresholdService
{
    /** Band boundaries used when the table is empty (legacy platform defaults). */
    public const DEFAULTS = [
        'low_threshold' => 40,
        'moderate_threshold' => 70,
        'high_threshold' => 71,
    ];

    public const LEVELS = ['Low', 'Moderate', 'High'];

    /** @var array<string, int>|null Request-scoped memo of the persisted row. */
    protected ?array $memo = null;

    public function current(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $row = DB::table('risk_thresholds')->orderBy('id')->first();

        return $this->memo = self::normalize($row ? (array) $row : []);
    }

    public static function normalize(array $raw): array
    {
        $normalized = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $raw[$key] ?? null;
            $normalized[$key] = is_numeric($value) ? (int) $value : $default;
        }

        return $normalized;
    }

    /** Drop the request-scoped memo (called after a configuration write). */
    public function forget(): void
    {
        $this->memo = null;
    }

    public function levelFor($score, ?array $thresholds = null): string
    {
        $bands = self::normalize($thresholds ?? $this->current());
        $score = (int) round((float) $score);

        if ($score <= $bands['low_threshold']) {
            return 'Low';
        }

        if ($score <= $bands['moderate_threshold']) {
            return 'Moderate';
        }

        if ($score >= $bands['high_threshold']) {
            return 'High';
        }

        return 'Moderate';
    }

    public static function bandLabels(array $thresholds): array
    {
        $bands = self::normalize($thresholds);

        return [
            'Low' => '0 - ' . $bands['low_threshold'],
            'Moderate' => ($bands['low_threshold'] + 1) . ' - ' . $bands['moderate_threshold'],
            'High' => $bands['high_threshold'] . ' - 100',
        ];
    }

    public static function bandsForPrompt(array $thresholds): array
    {
        $bands = self::normalize($thresholds);

        return [
            ['level' => 'Low', 'min' => 0, 'max' => $bands['low_threshold']],
            ['level' => 'Moderate', 'min' => $bands['low_threshold'] + 1, 'max' => $bands['moderate_threshold']],
            ['level' => 'High', 'min' => $bands['high_threshold'], 'max' => 100],
        ];
    }

    public static function effectiveBandLabels(array $thresholds): array
    {
        $bands = self::normalize($thresholds);

        return [
            'Low' => '0 - ' . $bands['low_threshold'],
            'Moderate' => ($bands['low_threshold'] + 1) . ' - ' . max($bands['moderate_threshold'], $bands['high_threshold'] - 1),
            'High' => $bands['high_threshold'] . ' - 100',
        ];
    }

    public static function consistencyError(array $thresholds): ?string
    {
        $bands = self::normalize($thresholds);
        $low = $bands['low_threshold'];
        $moderate = $bands['moderate_threshold'];
        $high = $bands['high_threshold'];

        if ($low < 0 || $moderate < 0 || $high < 0 || $high > 100) {
            return 'Thresholds must stay between 0 and 100.';
        }

        if ($low >= $moderate) {
            return 'The Low threshold must be less than the Moderate threshold.';
        }

        if ($moderate >= $high) {
            return 'The Moderate threshold must be less than the High threshold.';
        }

        if ($high !== $moderate + 1) {
            return 'Bands must be contiguous so no score is left unclassified: '
                . 'the High band must begin exactly one point above the Moderate ceiling '
                . '(high_threshold must equal moderate_threshold + 1).';
        }

        return null;
    }
}