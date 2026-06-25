<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use App\Models\User;
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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],

            'type' => ['required', 'string', Rule::enum(PromotionType::class)],

            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],

            'requires_login' => ['boolean'],
            'is_general' => ['boolean'],

            'priority' => ['nullable', 'integer'],

            'config' => ['required', 'array'],
            'config.scales' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::PRICE_SCALE_PERCENTAGE->value),
                'nullable',
                'array',
                'min:1',
            ],
            'config.scales.*.from_quantity' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::PRICE_SCALE_PERCENTAGE->value),
                'integer',
                'min:1',
            ],
            'config.scales.*.to_quantity' => ['nullable', 'integer', 'min:1'],
            'config.scales.*.discount_percentage' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::PRICE_SCALE_PERCENTAGE->value),
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'config.scales.*.is_active' => ['nullable', 'boolean'],
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
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    PromotionType::BUY_SKU_GET_GIFT_ITEM->value,
                    PromotionType::PRICE_SCALE_PERCENTAGE->value,
                ], true)),
                'nullable',
                'array',
                'min:1',
                Rule::when($this->input('type') === PromotionType::PRICE_SCALE_PERCENTAGE->value, ['max:1']),
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

            'user_ids' => [
                Rule::requiredIf(fn () => $this->isSpecificCustomerPromotion()),
                'nullable',
                'array',
                'min:1',
            ],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== PromotionType::PRICE_SCALE_PERCENTAGE->value) {
                $this->validateSelectedCustomers($validator);
                return;
            }

            $this->validateScales($validator, $this->input('config.scales', []));
            $this->validateScaleRemovalOrder($validator, $this->input('config.scales', []));
            $this->validateSelectedCustomers($validator);
        });
    }

    protected function isSpecificCustomerPromotion(): bool
    {
        return filter_var($this->input('is_general', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false;
    }

    protected function validateSelectedCustomers($validator): void
    {
        $userIds = collect($this->input('user_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $clientIds = User::query()
            ->whereIn('id', $userIds)
            ->where('role_id', User::ROLE_CLIENTE)
            ->pluck('id');

        $invalidIds = $userIds->diff($clientIds)->values();

        if ($invalidIds->isNotEmpty()) {
            $validator->errors()->add('user_ids', 'Solo puedes asignar clientes a una promoción específica.');
        }
    }

    protected function validateScales($validator, array $scales): void
    {
        $normalized = collect($scales)
            ->values()
            ->map(fn ($scale, $index) => [
                'index' => $index,
                'from' => (int) ($scale['from_quantity'] ?? 0),
                'to' => isset($scale['to_quantity']) && $scale['to_quantity'] !== ''
                    ? (int) $scale['to_quantity']
                    : null,
                'active' => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ]);

        foreach ($normalized as $scale) {
            if ($scale['to'] !== null && $scale['to'] < $scale['from']) {
                $validator->errors()->add("config.scales.{$scale['index']}.to_quantity", 'La cantidad final debe ser mayor o igual a la cantidad inicial.');
            }
        }

        $activeScales = $normalized
            ->filter(fn ($scale) => $scale['active'])
            ->sortBy('from')
            ->values();

        for ($i = 0; $i < $activeScales->count(); $i++) {
            $current = $activeScales[$i];
            $next = $activeScales[$i + 1] ?? null;

            if ($current['to'] === null && $next) {
                $validator->errors()->add("config.scales.{$current['index']}.to_quantity", 'Una escala sin cantidad final debe ser la última escala activa.');
            }

            if (!$next || $current['to'] === null) {
                continue;
            }

            $expectedNextFrom = $current['to'] + 1;

            if ($next['from'] !== $expectedNextFrom) {
                $validator->errors()->add("config.scales.{$next['index']}.from_quantity", "Las escalas deben ser consecutivas. Después de {$current['to']} debe iniciar {$expectedNextFrom}.");
            }
        }

        foreach ($normalized as $scale) {
            if ($scale['active']) {
                continue;
            }

            $hasActiveAbove = $normalized->contains(fn ($candidate) => $candidate['active'] && $candidate['from'] > $scale['from']);

            if ($hasActiveAbove) {
                $validator->errors()->add("config.scales.{$scale['index']}.is_active", 'No puedes desactivar una escala si existe una escala superior activa.');
            }
        }
    }

    protected function validateScaleRemovalOrder($validator, array $newScales): void
    {
        $promotion = $this->route('promotion');

        if (!$promotion || $promotion->type !== PromotionType::PRICE_SCALE_PERCENTAGE) {
            return;
        }

        $oldActiveStarts = collect(data_get($promotion->config, 'scales', []))
            ->filter(fn ($scale) => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true)
            ->pluck('from_quantity')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        $newActiveStarts = collect($newScales)
            ->filter(fn ($scale) => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true)
            ->pluck('from_quantity')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        foreach ($oldActiveStarts as $oldStart) {
            if ($newActiveStarts->contains($oldStart)) {
                continue;
            }

            $hasOldAboveStillActive = $oldActiveStarts
                ->filter(fn ($candidate) => $candidate > $oldStart)
                ->contains(fn ($candidate) => $newActiveStarts->contains($candidate));

            if ($hasOldAboveStillActive) {
                $validator->errors()->add('config.scales', 'No puedes eliminar una escala si existe una escala superior activa.');
                return;
            }
        }
    }
}
