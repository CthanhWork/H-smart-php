<?php

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'offer_id' => ['required_without:product_id', 'prohibits:product_id', 'integer', 'exists:offers,id'],
            'product_id' => ['required_without:offer_id', 'prohibits:offer_id', 'integer', 'exists:products,id'],
            'expected_price' => ['required_with:product_id', 'integer', 'min:0'],
            'idempotency_key' => ['required_with:product_id', 'string', 'min:16', 'max:80'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'payment_method' => ['sometimes', 'string', 'in:vnpay,cod'],
            'accept_policy' => ['required', 'accepted'],
            'expected_shipping_fee' => ['required', 'integer', 'min:0'],
            'expected_commission_rate' => ['sometimes', 'integer', 'between:0,100'],
            'expected_cod_deposit_percent' => ['required', 'integer', 'between:20,30'],
            'buyer_id' => ['prohibited'],
            'seller_id' => ['prohibited'],
            'status' => ['prohibited'],
            'total_amount' => ['prohibited'],
        ];
    }
}
