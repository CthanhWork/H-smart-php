<?php

namespace App\Modules\Offer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'offer_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'buyer_id' => ['prohibited'],
            'seller_id' => ['prohibited'],
            'status' => ['prohibited'],
            'expires_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Vui lòng chọn sản phẩm.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'offer_price.required' => 'Vui lòng nhập giá đề nghị.',
            'offer_price.min' => 'Giá đề nghị phải lớn hơn 0.',
            'offer_price.max' => 'Giá đề nghị quá lớn.',
        ];
    }
}
