<?php

namespace App\Modules\Product\Http\Requests;

class UpdateProductRequest extends CreateProductRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image_ids' => ['sometimes', 'array'],
            'remove_image_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'image_order' => ['sometimes', 'array'],
            'image_order.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
