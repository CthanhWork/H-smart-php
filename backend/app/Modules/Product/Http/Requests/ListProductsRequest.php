<?php

namespace App\Modules\Product\Http\Requests;

use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:200'],
            'page' => ['sometimes', 'array'],
            'page.number' => ['sometimes', 'integer', 'min:0'],
            'page.size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.condition' => ['sometimes', Rule::in(Product::CONDITIONS)],
            'filter.min_price' => ['sometimes', 'integer', 'min:1'],
            'filter.max_price' => ['sometimes', 'integer', 'min:1', Rule::when($this->input('filter.min_price') !== null, ['gte:filter.min_price'])],
            'filter.status' => ['sometimes', Rule::in(Product::STATUSES)],
        ];
    }
}
