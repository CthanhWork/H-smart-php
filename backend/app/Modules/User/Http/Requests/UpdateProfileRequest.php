<?php

namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['present', 'nullable', 'string', 'max:150'],
            'phone_number' => ['present', 'nullable', 'string', 'min:8', 'max:30'],
            'shipping_address' => ['present', 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }
}
