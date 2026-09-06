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

        foreach (['is_active', 'is_general', 'is_combinable'] as $field) {
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
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in([Coupon::DISCOUNT_TYPE_FIXED, Coupon::DISCOUNT_TYPE_PERCENTAGE])],
            'discount_value' => ['required', 'numeric', 'min:0.01', Rule::when($this->input('discount_type') === Coupon::DISCOUNT_TYPE_PERCENTAGE, ['max:100'])],
            'is_active' => ['nullable', 'boolean'],
            'is_general' => ['nullable', 'boolean'],
            'is_combinable' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_usage_limit' => ['nullable', 'integer', 'min:1'],
            'trigger_coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'metadata' => ['nullable', 'array'],
            'campaign' => ['nullable', 'array'],
            'campaign.send_email' => ['nullable', 'boolean'],
            'campaign.send_at' => ['nullable', 'date'],
            'campaign.subject' => ['nullable', 'string', 'max:255'],
            'campaign.message' => ['nullable', 'string', 'max:1000'],
            'campaign.user_ids' => ['nullable', 'array'],
            'campaign.user_ids.*' => ['integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAssignedUsersArePresent($validator);
            $this->validateSelectedClients($validator);

            if ((int) $this->input('trigger_coupon_id') === (int) $this->route('coupon')?->id) {
                $validator->errors()->add('trigger_coupon_id', 'El cupón relacionado no puede ser el mismo cupón.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código del cupón es obligatorio.',
            'code.regex' => 'El código del cupón solo puede contener letras, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un cupón con este código.',
            'name.required' => 'El nombre del cupón es obligatorio.',
            'discount_type.required' => 'El tipo de descuento es obligatorio.',
            'discount_type.in' => 'El tipo de descuento debe ser monto fijo o porcentaje.',
            'discount_value.required' => 'El valor del descuento es obligatorio.',
            'discount_value.numeric' => 'El valor del descuento debe ser numérico.',
            'discount_value.min' => 'El valor del descuento debe ser mayor a cero.',
            'discount_value.max' => 'El porcentaje de descuento no puede ser mayor a 100.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'is_general.boolean' => 'El campo general debe ser verdadero o falso.',
            'is_combinable.boolean' => 'El campo combinable debe ser verdadero o falso.',
            'starts_at.date' => 'La fecha de inicio no tiene un formato válido.',
            'ends_at.date' => 'La fecha de vencimiento no tiene un formato válido.',
            'ends_at.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de inicio.',
            'usage_limit.integer' => 'El límite de usos debe ser un número entero.',
            'usage_limit.min' => 'El límite de usos debe ser al menos 1.',
            'per_user_usage_limit.integer' => 'El límite por usuario debe ser un número entero.',
            'per_user_usage_limit.min' => 'El límite por usuario debe ser al menos 1.',
            'trigger_coupon_id.integer' => 'El cupón relacionado debe ser válido.',
            'trigger_coupon_id.exists' => 'El cupón relacionado seleccionado no existe.',
            'metadata.array' => 'Los metadatos deben enviarse como un objeto.',
            'campaign.array' => 'La campaña debe enviarse como un objeto.',
            'campaign.send_email.boolean' => 'La opción de enviar por correo debe ser verdadero o falso.',
            'user_ids.array' => 'Los usuarios asignados deben enviarse como una lista.',
            'user_ids.*.integer' => 'Uno de los usuarios seleccionados no es válido.',
            'user_ids.*.exists' => 'Uno de los usuarios seleccionados no existe.',
            'campaign.send_at.date' => 'La fecha programada de campaña no tiene un formato válido.',
            'campaign.subject.max' => 'El asunto de la campaña no puede superar 255 caracteres.',
            'campaign.message.max' => 'El mensaje de la campaña no puede superar 1000 caracteres.',
            'campaign.user_ids.array' => 'Los usuarios de la campaña deben enviarse como una lista.',
            'campaign.user_ids.*.integer' => 'Uno de los usuarios de la campaña no es válido.',
            'campaign.user_ids.*.exists' => 'Uno de los usuarios de la campaña no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'description' => 'descripción',
            'discount_type' => 'tipo de descuento',
            'discount_value' => 'valor de descuento',
            'is_active' => 'activo',
            'is_general' => 'general',
            'is_combinable' => 'combinable',
            'starts_at' => 'fecha de inicio',
            'ends_at' => 'fecha de vencimiento',
            'usage_limit' => 'límite de usos',
            'per_user_usage_limit' => 'límite por usuario',
            'trigger_coupon_id' => 'cupón relacionado',
            'user_ids' => 'usuarios asignados',
            'campaign.send_at' => 'fecha programada de campaña',
        ];
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

    protected function validateAssignedUsersArePresent($validator): void
    {
        if ($this->isAssignedCoupon() && collect($this->input('user_ids', []))->filter()->isEmpty()) {
            $validator->errors()->add('user_ids', 'Debes seleccionar al menos un usuario para un cupón asignado.');
        }
    }
}
