<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CustomerPfrProfile;
use App\Models\CustomerProfile;
use App\Models\Family;
use App\Models\GiftItem;
use App\Models\MonthlyPromotion;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'token',
        'current_password',
        'password_confirmation',
    ];

    public function record(array $data, ?Request $request = null): ?ActivityLog
    {
        $request ??= request();
        $actor = $data['actor'] ?? Auth::user();

        return ActivityLog::create([
            'user_id' => $data['user_id'] ?? $actor?->id,
            'actor_type' => $data['actor_type'] ?? $this->actorType($actor),
            'module' => $data['module'],
            'action' => $data['action'],
            'summary' => $data['summary'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_values' => $this->sanitize($data['old_values'] ?? null),
            'new_values' => $this->sanitize($data['new_values'] ?? null),
            'metadata' => $this->sanitize($data['metadata'] ?? null),
            'ip' => $data['ip'] ?? $request?->ip(),
            'user_agent' => $data['user_agent'] ?? $request?->userAgent(),
        ]);
    }

    public function created(Model $model): ?ActivityLog
    {
        $context = $this->context($model);

        return $this->record([
            ...$context,
            'action' => 'created',
            'summary' => $context['label'] . ' creado',
            'new_values' => $this->attributesForLog($model),
            'metadata' => [
                'name' => $this->displayName($model),
            ],
        ]);
    }

    public function updated(Model $model): ?ActivityLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return null;
        }

        $fields = array_keys($changes);
        $context = $this->context($model);
        $action = $this->updatedAction($model, $fields);

        return $this->record([
            ...$context,
            'action' => $action,
            'summary' => $this->updatedSummary($context['label'], $action),
            'old_values' => $this->originalValues($model, $fields),
            'new_values' => Arr::only($model->getAttributes(), $fields),
            'metadata' => [
                'name' => $this->displayName($model),
                'fields_changed' => $fields,
            ],
        ]);
    }

    public function deleted(Model $model): ?ActivityLog
    {
        $context = $this->context($model);

        return $this->record([
            ...$context,
            'action' => 'deleted',
            'summary' => $context['label'] . ' eliminado',
            'old_values' => $this->attributesForLog($model),
            'metadata' => [
                'name' => $this->displayName($model),
            ],
        ]);
    }

    private function context(Model $model): array
    {
        $map = [
            Product::class => ['module' => 'products', 'entity_type' => 'product', 'label' => 'Producto'],
            Promotion::class => ['module' => 'promotions', 'entity_type' => 'promotion', 'label' => 'Promoción'],
            GiftItem::class => ['module' => 'promotions', 'entity_type' => 'gift_item', 'label' => 'Artículo de regalo'],
            MonthlyPromotion::class => ['module' => 'marketing', 'entity_type' => 'monthly_promotion', 'label' => 'Promoción del mes'],
            Banner::class => ['module' => 'marketing', 'entity_type' => 'banner', 'label' => 'Banner'],
            CustomerProfile::class => ['module' => 'customers', 'entity_type' => 'customer_profile', 'label' => 'Perfil de cliente'],
            CustomerPfrProfile::class => ['module' => 'customers', 'entity_type' => 'customer_pfr_profile', 'label' => 'Perfil PFR de cliente'],
            UserAddress::class => ['module' => 'customers', 'entity_type' => 'customer_address', 'label' => 'Dirección de cliente'],
            Role::class => ['module' => 'roles', 'entity_type' => 'role', 'label' => 'Rol'],
            Category::class => ['module' => 'categories', 'entity_type' => 'category', 'label' => 'Categoría'],
            Family::class => ['module' => 'families', 'entity_type' => 'family', 'label' => 'Familia'],
            User::class => [
                'module' => $this->isCustomer($model) ? 'customers' : 'users',
                'entity_type' => $this->isCustomer($model) ? 'customer' : 'user',
                'label' => $this->isCustomer($model) ? 'Cliente' : 'Usuario',
            ],
        ];

        $context = $map[$model::class] ?? [
            'module' => class_basename($model),
            'entity_type' => Str::of(class_basename($model))->snake()->toString(),
            'label' => class_basename($model),
        ];

        return [
            ...$context,
            'entity_id' => $model->getKey(),
        ];
    }

    private function updatedAction(Model $model, array $fields): string
    {
        if (count($fields) === 1 && in_array('is_active', $fields, true)) {
            return 'status_changed';
        }

        if ($model instanceof Product && in_array('default_price', $fields, true)) {
            return 'price_changed';
        }

        return 'updated';
    }

    private function updatedSummary(string $label, string $action): string
    {
        return match ($action) {
            'status_changed' => 'Estado de ' . strtolower($label) . ' actualizado',
            'price_changed' => 'Precio de ' . strtolower($label) . ' actualizado',
            default => $label . ' actualizado',
        };
    }

    private function originalValues(Model $model, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $model->getOriginal($field)])
            ->all();
    }

    private function attributesForLog(Model $model): array
    {
        return Arr::except($model->getAttributes(), ['updated_at']);
    }

    private function displayName(Model $model): ?string
    {
        foreach (['name', 'title', 'email', 'sku', 'code'] as $field) {
            if (filled($model->{$field} ?? null)) {
                return (string) $model->{$field};
            }
        }

        return null;
    }

    private function actorType(?User $actor): string
    {
        if (! $actor) {
            return 'system';
        }

        return $this->isCustomer($actor) ? 'customer' : 'admin';
    }

    private function isCustomer(Model $model): bool
    {
        return (int) ($model->role_id ?? 0) === User::ROLE_CLIENTE;
    }

    private function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->reject(fn ($item, $key) => in_array((string) $key, self::SENSITIVE_KEYS, true))
            ->map(fn ($item) => is_array($item) ? $this->sanitize($item) : $item)
            ->all();
    }
}
