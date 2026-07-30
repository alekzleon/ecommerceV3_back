<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;
use Throwable;

class CreateTenantStore extends Command
{
    protected $signature = 'tenants:create-store
        {subdomain : Subdominio de la tienda, por ejemplo zapatos}
        {--store-name= : Nombre visible de la tienda}
        {--owner-name= : Nombre del dueño/admin}
        {--owner-email= : Correo del dueño/admin}
        {--password= : Contraseña inicial del dueño/admin}
        {--publish : Crear la tienda publicada en lugar de construcción}';

    protected $description = 'Provisiona una tienda tenant con base de datos, dominios, settings y usuario admin.';

    public function handle(TenantProvisioningService $provisioningService): int
    {
        $subdomain = (string) $this->argument('subdomain');

        $payload = [
            'subdomain' => $subdomain,
            'store_name' => $this->option('store-name') ?: $this->ask('Nombre de la tienda', str($subdomain)->headline()->toString()),
            'owner_name' => $this->option('owner-name') ?: $this->ask('Nombre del dueño/admin', 'Admin ' . str($subdomain)->headline()->toString()),
            'owner_email' => $this->option('owner-email') ?: $this->ask('Correo del dueño/admin', "admin@{$subdomain}.test"),
            'password' => $this->option('password') ?: $this->secret('Contraseña inicial'),
            'publish' => (bool) $this->option('publish'),
        ];

        try {
            $result = $provisioningService->createStore($payload);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Tienda tenant provisionada correctamente.');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Tenant ID', $result['tenant_id']],
                ['Base de datos', $result['database']],
                ['Dominio local', $result['domains']['local']],
                ['Dominio producción', $result['domains']['production']],
                ['Admin email', $result['admin']['email']],
                ['Publicada', $result['storefront']['is_published'] ? 'sí' : 'no'],
            ]
        );

        return self::SUCCESS;
    }
}
