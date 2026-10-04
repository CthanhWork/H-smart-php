<?php

namespace App\Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class SafePassword implements ValidationRule
{
    public function __construct(private readonly string $email) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $lower = Str::lower($value);
        $localPart = Str::before($this->email, '@');
        $common = [
            'password', 'passw0rd', 'qwerty', '123456', 'abcdefgh', 'abc123',
            'letmein', 'admin', 'iloveyou', 'welcome', 'monkey', 'dragon',
            'football', 'sunshine', 'princess', 'trustno1',
        ];

        if (preg_match('/[\x00-\x1F\x7F]/u', $value)
            || ($localPart !== '' && mb_strlen($localPart) >= 4 && str_contains($lower, $localPart))
            || collect($common)->contains(fn (string $word) => str_contains($lower, $word))
            || preg_match('/^(.)\1{7,}$/u', $value)) {
            $fail('Mật khẩu quá dễ đoán hoặc chứa thông tin tài khoản.');
        }
    }
}
