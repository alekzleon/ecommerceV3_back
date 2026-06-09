<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('promotion')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', "unique:promotions,slug,$id"],
            'description' => ['nullable', 'string'],

            'type' => ['required', 'string', Rule::enum(PromotionType::class)],

            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],

            'requires_login' => ['boolean'],
            'is_general' => ['boolean'],
            'is_combinable' => ['boolean'],

            'priority' => ['nullable', 'integer'],

            'config' => ['required', 'array'],
            'config.buy_quantity' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUNDLE_PAY_X_TAKE_Y->value,
                    PromotionType::BUY_X_GET_Y->value,
                    PromotionType::BUY_X_GET_DISCOUNT->value,
                    PromotionType::BUY_SKU_GET_GIFT_ITEM->value,
                ], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'config.pay_quantity' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::BUNDLE_PAY_X_TAKE_Y->value),
                'nullable',
                'integer',
                'min:0',
            ],
            'config.discount_percentage' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_X_GET_Y->value,
                    PromotionType::BUY_X_GET_DISCOUNT->value,
                    PromotionType::DIRECT_PERCENTAGE->value,
                ], true)),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'config.promotional_price' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::STRIKETHROUGH_PRICE->value),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'config.brand' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                ], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'config.minimum_amount' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                ], true)),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'config.gift_quantity' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_SKU_GET_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                ], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'config.target_product_id' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_X_GET_Y->value,
                    PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                ], true)),
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'config.target_quantity' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_X_GET_Y->value,
                    PromotionType::BRAND_AMOUNT_GET_PRODUCT->value,
                ], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'config.selection_required' => ['nullable', 'boolean'],

            'product_ids' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::BUY_SKU_GET_GIFT_ITEM->value),
                'nullable',
                'array',
                'min:1',
            ],
            'product_ids.*' => ['exists:products,id'],

            'gift_item_ids' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_SKU_GET_GIFT_ITEM->value,
                    PromotionType::BRAND_AMOUNT_CHOOSE_GIFT_ITEM->value,
                ], true)),
                'nullable',
                'array',
                'min:1',
            ],
            'gift_item_ids.*' => ['exists:gift_items,id'],
        ];
    }
}
