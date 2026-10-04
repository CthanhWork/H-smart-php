<?php

namespace App\Modules\Product\Http\Requests;

class UpdateProductRequest extends CreateProductRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_image_ids' => ['sometimes', 'array'],
            'remove_image_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'image_order' => ['sometimes', 'array'],
            'image_order.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
