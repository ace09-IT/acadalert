<?php

namespace App\Rules;

use App\Services\Api\MockApiClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ApiUnique implements ValidationRule
{
    public function __construct(
        protected string $resource,
        protected string $column,
        protected mixed $ignoreId = null,
        protected string $idColumn = 'id',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (app(MockApiClient::class)->all($this->resource) as $row) {
            if (!$this->isSame($row->{$this->column} ?? null, $value)) {
                continue;
            }

            // `unique:...,<ignoreId>` excludes the row being updated.
            if ($this->ignoreId !== null && $this->isSame($row->{$this->idColumn} ?? null, $this->ignoreId)) {
                continue;
            }

            $fail('The :attribute has already been taken.');

            return;
        }
    }

    protected function isSame(mixed $stored, mixed $value): bool
    {
        if (is_numeric($stored) && is_numeric($value)) {
            return (int) $stored === (int) $value;
        }

        return strcasecmp((string) $stored, (string) $value) === 0;
    }
}
