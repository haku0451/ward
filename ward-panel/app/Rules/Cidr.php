<?php

namespace App\Rules;

use App\Support\Cidr as CidrParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CidrParser::isValid($value)) {
            $fail('A valid IPv4 or IPv6 address or CIDR is required.');
        }
    }
}
