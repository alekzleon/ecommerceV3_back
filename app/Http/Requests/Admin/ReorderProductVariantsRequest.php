<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'variants.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
