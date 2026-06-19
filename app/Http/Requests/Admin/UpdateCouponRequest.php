<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['code', 'name', 'description'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $data[$field] = filled($value) ? trim((string) $value) : null;
            }
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        foreach (['is_active', 'is_general'] as $field) {
            if ($this->has($field)) {
                $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in([Coupon::DISCOUNT_TYPE_FIXED, Coupon::DISCOUNT_TYPE_PERCENTAGE])],
            'discount_value' => ['required', 'numeric', 'min:0.01', Rule::when($this->input('discount_type') === Coupon::DISCOUNT_TYPE_PERCENTAGE, ['max:100'])],
            'is_active' => ['nullable', 'boolean'],
            'is_general' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'user_ids' => [Rule::requiredIf(fn () => $this->isAssignedCoupon()), 'nullable', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateSelectedClients($validator));
    }

    protected function isAssignedCoupon(): bool
    {
        return filter_var($this->input('is_general', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false;
    }

    protected function validateSelectedClients($validator): void
    {
        $userIds = collect($this->input('user_ids', []))->map(fn ($id) => (int) $id)->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $clientIds = User::query()
            ->whereIn('id', $userIds)
            ->where('role_id', User::ROLE_CLIENTE)
            ->pluck('id');

        if ($userIds->diff($clientIds)->isNotEmpty()) {
            $validator->errors()->add('user_ids', 'Solo puedes asignar clientes al cupón.');
        }
    }
}
