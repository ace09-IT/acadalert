<?php

namespace App\Rules;

use App\Services\Api\MockApiClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ApiExists implements ValidationRule
{
    public function __construct(
        protected string $resource,
        protected string $column,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (app(MockApiClient::class)->all($this->resource) as $row) {
            if ($this->isSame($row->{$this->column} ?? null, $value)) {
                return;
            }
        }

        $fail('The selected :attribute is invalid.');
    }

    protected function isSame(mixed $stored, mixed $value): bool
    {
        if (is_numeric($stored) && is_numeric($value)) {
            return (int) $stored === (int) $value;
        }

        return strcasecmp((string) $stored, (string) $value) === 0;
    }
}
