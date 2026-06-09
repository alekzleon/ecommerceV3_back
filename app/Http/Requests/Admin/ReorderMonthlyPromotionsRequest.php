<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMonthlyPromotionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_promotions' => ['required', 'array', 'min:1'],
            'monthly_promotions.*.id' => ['required', 'integer', 'exists:monthly_promotions,id'],
            'monthly_promotions.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
