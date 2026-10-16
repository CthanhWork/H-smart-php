<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminProductHideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do ẩn sản phẩm',
            'reason.min' => 'Lý do phải có ít nhất 10 ký tự',
            'reason.max' => 'Lý do không được vượt quá 500 ký tự',
        ];
    }
}
