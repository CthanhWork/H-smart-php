<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Rules\SafePassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->max(128), new SafePassword((string) $this->user()->email)],
        ];
    }
}
