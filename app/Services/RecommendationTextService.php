<?php

namespace App\Services;

class RecommendationTextService
{
    public const PRIORITIES = ['high', 'medium', 'low'];

    public function actions($value): array
    {
        $decoded = $this->decode($value);

        if (is_string($decoded)) {
            $decoded = [$decoded];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $actions = [];

        foreach ($decoded as $key => $entry) {
            $action = $this->normaliseAction($entry, $key);

            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    public function toText($value): string
    {
        $lines = [];

        foreach ($this->actions($value) as $action) {
            $lines[] = trim(
                '[' . $action['priority'] . '] '
                . ucfirst(str_replace('_', ' ', $action['action']))
                . ': ' . $action['details']
            );
        }

        return implode("\n", $lines);
    }

    public function fromText(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $actions = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $rawLine) {
            $line = trim((string) $rawLine);

            if ($line === '') {
                continue;
            }

            $priority = 'medium';

            if (preg_match('/^[\[\(]\s*([a-zA-Z]+)\s*[\]\)]\s*(.*)$/', $line, $matches)) {
                $priority = $this->normalisePriority($matches[1]);
                $line = trim($matches[2]);
            }

            $line = preg_replace('/^[-*\x{2022}]\s*|^\d+[\.\)]\s*/u', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $action = 'custom';
            $details = $line;

            if (str_contains($line, ':')) {
                [$label, $rest] = explode(':', $line, 2);
                $label = trim($label);
                $rest = trim($rest);

                if ($label !== '' && $rest !== '' && str_word_count($label) <= 4) {
                    $action = strtolower(str_replace([' ', '-'], '_', $label));
                    $details = $rest;
                }
            }

            $actions[] = [
                'action' => $action,
                'details' => $details,
                'priority' => $priority,
            ];
        }

        return $actions;
    }

    public function factors($value): array
    {
        $decoded = $this->decode($value);

        if (is_string($decoded)) {
            $decoded = preg_split('/\r\n|\r|\n|;/', $decoded) ?: [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $factors = [];

        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $entry = $entry['factor'] ?? $entry['text'] ?? $entry['details'] ?? null;
            }

            if (!is_string($entry) && !is_numeric($entry)) {
                continue;
            }

            $factor = trim((string) $entry);

            if ($factor !== '' && $factor !== '0') {
                $factors[] = $factor;
            }
        }

        return array_values(array_unique($factors));
    }

    public function isEmpty($value): bool
    {
        return $this->actions($value) === [];
    }

    public function textForRow(?object $row): string
    {
        return $row === null ? '' : $this->toText($row->suggested_actions ?? null);
    }

    public function countForRow(?object $row): int
    {
        return $row === null ? 0 : count($this->actions($row->suggested_actions ?? null));
    }

    public function resolveForwardedText($generatedActions, ?string $editedText): array
    {
        $generated = trim($this->toText($generatedActions));
        $edited = $editedText === null ? '' : trim($editedText);

        if ($edited !== '') {
            return [
                'included' => true,
                'edited' => $edited !== $generated,
                'text' => $edited,
            ];
        }

        if ($generated !== '') {
            return [
                'included' => true,
                'edited' => false,
                'text' => $generated,
            ];
        }

        return [
            'included' => false,
            'edited' => false,
            'text' => null,
        ];
    }

    protected function decode($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return $decoded === null ? $value : $decoded;
        }

        return $value;
    }

    protected function normaliseAction($entry, $key = null): ?array
    {
        if (is_string($entry) || is_numeric($entry)) {
            $text = trim((string) $entry);

            return $text === '' ? null : [
                'action' => 'custom',
                'details' => $text,
                'priority' => 'medium',
            ];
        }

        if (!is_array($entry)) {
            return null;
        }

        $action = $entry['action'] ?? $entry['title'] ?? null;
        $details = $entry['details'] ?? $entry['description'] ?? $entry['text'] ?? null;

        if (is_array($action)) {
            $action = null;
        }

        if ($details === null) {
            $details = $action;
            $action = null;
        }

        if (is_array($details)) {
            $details = implode(' ', array_filter(array_map(
                fn ($part) => is_scalar($part) ? (string) $part : null,
                $details
            )));
        }

        if (!is_string($details) && !is_numeric($details)) {
            return null;
        }

        $details = trim((string) $details);

        if ($details === '') {
            return null;
        }

        $label = is_string($action) && trim($action) !== ''
            ? trim($action)
            : (is_string($key) ? $key : 'custom');

        return [
            'action' => $label,
            'details' => $details,
            'priority' => $this->normalisePriority($entry['priority'] ?? null),
        ];
    }

    /** Collapse any priority spelling onto the canonical whitelist. */
    protected function normalisePriority($priority): string
    {
        return match (strtolower(trim((string) $priority))) {
            'high', 'critical', 'urgent' => 'high',
            'low', 'minor' => 'low',
            default => 'medium',
        };
    }
}
