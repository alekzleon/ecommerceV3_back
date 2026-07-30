<?php

namespace App\Services\Tenancy;

use App\Models\EcommerceSetting;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stancl\Tenancy\Database\Models\Domain;

class TenantProvisioningService
{
    protected const RESERVED_SUBDOMAINS = [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'localhost',
    ];

    public function createStore(array $payload): array
    {
        $storeName = trim((string) ($payload['store_name'] ?? ''));
        $subdomain = $this->normalizeSubdomain((string) ($payload['subdomain'] ?? ''));
        $ownerName = trim((string) ($payload['owner_name'] ?? ''));
        $ownerEmail = strtolower(trim((string) ($payload['owner_email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $publish = (bool) ($payload['publish'] ?? false);

        $this->validate($storeName, $subdomain, $ownerName, $ownerEmail, $password);

        $tenantId = $subdomain;
        $localDomain = "{$subdomain}.localhost";
        $productionDomain = "{$subdomain}.cloudishop.mx";

        if (Tenant::query()->whereKey($tenantId)->exists()) {
            throw new InvalidArgumentException("El tenant [{$tenantId}] ya existe.");
        }

        if (Domain::query()->whereIn('domain', [$localDomain, $productionDomain])->exists()) {
            throw new InvalidArgumentException('El subdominio ya está registrado.');
        }

        $tenant = Tenant::create([
            'id' => $tenantId,
            'plan_key' => config('plans.default', 'free'),
            'subscription_status' => Tenant::STATUS_ACTIVE,
            'subscription_started_at' => now(),
            'subscription_ends_at' => $this->defaultPlanEndsAt(),
            'data' => [
                'store_name' => $storeName,
                'owner_email' => $ownerEmail,
                'provisioned_at' => now()->toISOString(),
            ],
        ]);

        $tenant->domains()->create(['domain' => $localDomain]);
        $tenant->domains()->create(['domain' => $productionDomain]);

        $tenant->run(function () use ($storeName, $ownerName, $ownerEmail, $password, $publish) {
            $this->seedTenantBaseData($storeName, $ownerName, $ownerEmail, $password, $publish);
        });

        return [
            'tenant_id' => $tenant->id,
            'database' => $tenant->database()->getName(),
            'domains' => [
                'local' => $localDomain,
                'production' => $productionDomain,
            ],
            'admin' => [
                'name' => $ownerName,
                'email' => $ownerEmail,
            ],
            'storefront' => [
                'is_published' => $publish,
            ],
            'subscription' => [
                'plan_key' => $tenant->plan_key,
                'status' => $tenant->subscription_status,
                'ends_at' => $tenant->subscription_ends_at?->toISOString(),
            ],
        ];
    }

    public function checkSubdomain(string $value): array
    {
        $subdomain = $this->normalizeSubdomain($value);
        $localDomain = "{$subdomain}.localhost";
        $productionDomain = "{$subdomain}.cloudishop.mx";
        $valid = $this->isValidSubdomain($subdomain);
        $reserved = in_array($subdomain, self::RESERVED_SUBDOMAINS, true);
        $exists = $valid && (
            Tenant::query()->whereKey($subdomain)->exists()
            || Domain::query()->whereIn('domain', [$localDomain, $productionDomain])->exists()
        );

        return [
            'subdomain' => $subdomain,
            'valid' => $valid,
            'available' => $valid && ! $reserved && ! $exists,
            'reserved' => $reserved,
            'exists' => $exists,
            'domains' => [
                'local' => $localDomain,
                'production' => $productionDomain,
            ],
        ];
    }

    protected function seedTenantBaseData(
        string $storeName,
        string $ownerName,
        string $ownerEmail,
        string $password,
        bool $publish
    ): void {
        (new RoleSeeder())->run();
        (new ModuleSeeder())->run();
        (new RoleModuleSeeder())->run();

        $role = Role::query()->where('name', 'super_admin')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => $ownerEmail],
            [
                'role_id' => $role->id,
                'name' => $ownerName,
                'username' => Str::slug(Str::before($ownerEmail, '@'), '_') ?: 'admin',
                'password' => Hash::make($password),
                'must_change_password' => false,
            ]
        );

        SiteSetting::query()->firstOrCreate([], [
            'site_title' => $storeName,
            'logo_disk' => 'public',
            'logo_path' => null,
            'favicon_disk' => 'public',
            'favicon_path' => null,
            'contact_numbers' => [],
            'social_links' => [
                'instagram' => null,
                'facebook' => null,
                'tiktok' => null,
            ],
            'meta' => [
                'title' => $storeName,
                'description' => null,
                'keywords' => [],
            ],
            'loyalty' => [
                'first_purchase_discount_enabled' => false,
                'first_purchase_discount_percentage' => 0,
                'cashback_enabled' => false,
                'cashback_earn_percentage' => 0,
                'cashback_redeem_enabled' => false,
                'cashback_max_redeem_percentage' => 100,
            ],
        ]);

        EcommerceSetting::setValue(EcommerceSetting::KEY_STOREFRONT, [
            'is_published' => $publish,
            'construction_title' => 'Ecommerce en construcción',
            'construction_message' => 'Estamos preparando la tienda. Vuelve pronto.',
        ]);

        EcommerceSetting::setValue(EcommerceSetting::KEY_HOME_TEMPLATE, [
            'active_template' => EcommerceSetting::HOME_TEMPLATE_CLASSIC,
        ]);

        EcommerceSetting::setValue(EcommerceSetting::KEY_NAV_TITLE, [
            'title' => $storeName,
        ]);

        EcommerceSetting::setValue(EcommerceSetting::KEY_ABANDONED_CART, [
            'enabled' => true,
            'abandon_after_minutes' => 60,
            'recovery_link_expires_hours' => 48,
            'send_email' => true,
            'send_whatsapp' => true,
        ]);

        EcommerceSetting::setValue(EcommerceSetting::KEY_SALE_NOTIFICATIONS, [
            'enabled' => true,
            'send_email' => true,
            'send_whatsapp' => false,
            'admin_email' => $ownerEmail,
            'admin_whatsapp' => null,
        ]);
    }

    protected function defaultPlanEndsAt(): ?\Carbon\CarbonInterface
    {
        $defaultPlan = config('plans.default', 'free');
        $trialDays = (int) config("plans.plans.{$defaultPlan}.trial_days", 0);

        return $trialDays > 0 ? now()->addDays($trialDays) : null;
    }

    public function normalizeSubdomain(string $value): string
    {
        return Str::slug(strtolower(trim($value)));
    }

    protected function validate(string $storeName, string $subdomain, string $ownerName, string $ownerEmail, string $password): void
    {
        if ($storeName === '') {
            throw new InvalidArgumentException('El nombre de la tienda es obligatorio.');
        }

        if (! $this->isValidSubdomain($subdomain)) {
            throw new InvalidArgumentException('El subdominio debe tener 3 a 63 caracteres: letras, números y guiones.');
        }

        if (in_array($subdomain, self::RESERVED_SUBDOMAINS, true)) {
            throw new InvalidArgumentException('El subdominio está reservado.');
        }

        if ($ownerName === '') {
            throw new InvalidArgumentException('El nombre del dueño/admin es obligatorio.');
        }

        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo del dueño/admin no es válido.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
    }

    protected function isValidSubdomain(string $subdomain): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain) === 1;
    }
}
