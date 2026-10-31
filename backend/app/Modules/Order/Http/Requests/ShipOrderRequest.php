<?php

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'shipping_carrier' => ['required', 'string', 'min:2', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
        ];
    }
}
