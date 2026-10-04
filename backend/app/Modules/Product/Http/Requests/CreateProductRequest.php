<?php

namespace App\Modules\Product\Http\Requests;

use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'condition' => ['required', 'string', Rule::in(Product::CONDITIONS)],
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'seller_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
