<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Commands;

use Illuminate\Console\Command;

/**
 * One-shot onboarding for new consumers.
 *
 *   php artisan qredit:install              — single-tenant (reads .env)
 *   php artisan qredit:install --tenancy    — multi-tenant (prints stub + guidance)
 */
class InstallCommand extends Command
{
    protected $signature = 'qredit:install
                            {--tenancy : Print a CredentialProvider stub for multi-tenant apps}
                            {--publish : Re-publish config and migrations}';

    protected $description = 'Scaffold the Qredit SDK in a host Laravel app.';

    public function handle(): int
    {
        if ($this->option('publish')) {
            $this->call('vendor:publish', ['--tag' => 'qredit-config', '--force' => true]);
            $this->call('vendor:publish', ['--tag' => 'qredit-migrations', '--force' => true]);
        } else {
            $this->call('vendor:publish', ['--tag' => 'qredit-config']);
        }

        $this->newLine();
        $this->line('<info>Qredit SDK installed.</info>');
        $this->newLine();

        if (! $this->option('tenancy')) {
            $this->renderSingleTenantInstructions();
        } else {
            $this->renderMultiTenantInstructions();
        }

        return self::SUCCESS;
    }

    protected function renderSingleTenantInstructions(): void
    {
        $this->line('Next steps (single-tenant):');
        $this->line('  1. Add to your <comment>.env</comment>:');
        $this->line('       <comment>QREDIT_API_KEY=...</comment>');
        $this->line('       <comment>QREDIT_SECRET_KEY=...</comment>');
        $this->line('       <comment>QREDIT_SANDBOX=true</comment>');
        $this->line('  2. Add to your <comment>routes/web.php</comment>:');
        $this->line('       <comment>Route::qreditSign();</comment>');
        $this->line('       <comment>Route::qreditWebhook();</comment>');
        $this->line('  3. Use the facade anywhere:');
        $this->line('       <comment>Qredit::createOrder([...]);</comment>');
        $this->line('  4. Smoke-test the signed flow (the Postman replacement):');
        $this->line('       <comment>php artisan qredit:call --list</comment>');
    }

    protected function renderMultiTenantInstructions(): void
    {
        $this->line('Next steps (multi-tenant):');
        $this->line('  1. Implement the CredentialProvider contract:');
        $this->newLine();
        $this->line('<comment>    namespace App\\Qredit;</comment>');
        $this->line('<comment>    use Qredit\\LaravelQredit\\Contracts\\CredentialProvider;</comment>');
        $this->line('<comment>    use Qredit\\LaravelQredit\\Tenancy\\QreditCredentials;</comment>');
        $this->newLine();
        $this->line('<comment>    class TenantCredentialProvider implements CredentialProvider {</comment>');
        $this->line('<comment>        public function credentialsFor(?string $tenantId = null): QreditCredentials {</comment>');
        $this->line('<comment>            $tenant = $tenantId ?? app(\'current.tenant.id\');</comment>');
        $this->line('<comment>            $creds  = Tenant::find($tenant)->qredit_credentials;</comment>');
        $this->line('<comment>            return new QreditCredentials(</comment>');
        $this->line('<comment>                apiKey: $creds->api_key,</comment>');
        $this->line('<comment>                secretKey: $creds->secret_key,</comment>');
        $this->line('<comment>                sandbox: $creds->sandbox,</comment>');
        $this->line('<comment>                tenantId: (string) $tenant,</comment>');
        $this->line('<comment>            );</comment>');
        $this->line('<comment>        }</comment>');
        $this->line('<comment>        public function isConfiguredFor(?string $tenantId = null): bool { /* ... */ }</comment>');
        $this->line('<comment>    }</comment>');
        $this->newLine();
        $this->line('  2. Bind it in <comment>app/Providers/AppServiceProvider.php</comment>:');
        $this->line('<comment>     $this->app->bind(CredentialProvider::class, TenantCredentialProvider::class);</comment>');
        $this->newLine();
        $this->line('  3. Pick a TenantResolver (or write one):');
        $this->line('       - <comment>SubdomainTenantResolver</comment>');
        $this->line('       - <comment>HeaderTenantResolver</comment>');
        $this->line('       - <comment>CallbackTenantResolver</comment>');
        $this->newLine();
        $this->line('  4. Always pass explicit tenant in queue jobs:');
        $this->line('<comment>     Qredit::forTenant($this->tenantId)->createOrder([...]);</comment>');
    }
}
