<?php

namespace App\Services;

use App\Mail\AccountNotificationMail;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TenantNotificationService
{
    public function sendToUser(
        User $user,
        string $subject,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        array $details = [],
    ): void {
        $this->send(
            $user->email,
            $subject,
            $title,
            $message,
            $actionUrl,
            $actionLabel,
            $details,
            $this->currentBrandName()
        );
    }

    public function sendToTenantOwner(
        Tenant $tenant,
        string $subject,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        array $details = [],
    ): void {
        $contact = $this->tenantContact($tenant);

        if (blank($contact['email'])) {
            Log::warning('Tenant notification skipped because no owner email was found.', [
                'tenant_id' => $tenant->id,
                'subject' => $subject,
            ]);

            return;
        }

        $this->send(
            $contact['email'],
            $subject,
            $title,
            $message,
            $actionUrl,
            $actionLabel,
            $details,
            $contact['brand'] ?: 'Cloudi Shop'
        );
    }

    private function send(
        ?string $email,
        string $subject,
        string $title,
        string $message,
        ?string $actionUrl,
        ?string $actionLabel,
        array $details,
        string $brandName,
    ): void {
        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->send(new AccountNotificationMail(
                subjectLine: $subject,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
                details: $details,
                brandName: $brandName,
            ));
        } catch (Throwable $exception) {
            Log::warning('Account notification email could not be sent.', [
                'email' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function tenantContact(Tenant $tenant): array
    {
        $fallbackEmail = data_get($tenant->data, 'owner_email');
        $fallbackBrand = data_get($tenant->data, 'store_name', 'Cloudi Shop');

        try {
            return $this->runForTenant($tenant, function () use ($fallbackEmail, $fallbackBrand) {
                return [
                    'email' => $fallbackEmail ?: $this->firstOwnerEmail(),
                    'brand' => SiteSetting::query()->value('site_title') ?: $fallbackBrand,
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Tenant notification contact could not be resolved.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'email' => $fallbackEmail,
                'brand' => $fallbackBrand,
            ];
        }
    }

    private function runForTenant(Tenant $tenant, callable $callback): mixed
    {
        if (tenant() && (string) tenant('id') === (string) $tenant->id) {
            return $callback();
        }

        return $tenant->run($callback);
    }

    private function firstOwnerEmail(): ?string
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['super_admin', 'admin']))
            ->orderBy('id')
            ->value('email')
            ?: User::query()->orderBy('id')->value('email');
    }

    private function currentBrandName(): string
    {
        try {
            return SiteSetting::query()->value('site_title') ?: 'Cloudi Shop';
        } catch (Throwable) {
            return 'Cloudi Shop';
        }
    }
}
