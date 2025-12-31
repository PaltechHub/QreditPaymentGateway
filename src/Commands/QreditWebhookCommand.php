<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class QreditWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qredit:webhook
                            {--generate-secret : Generate a new webhook secret}';

    /**
     * The console command description.
     */
    protected $description = 'Manage Qredit webhooks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('generate-secret')) {
            return $this->generateSecret();
        }

        return $this->showWebhookInfo();
    }

    /**
     * Generate a new webhook secret.
     */
    protected function generateSecret(): int
    {
        $secret = Str::random(64);

        $this->info('Generated webhook secret:');
        $this->line($secret);
        $this->newLine();
        $this->warn('Add this to your .env file:');
        $this->line("QREDIT_WEBHOOK_SECRET=\"{$secret}\"");
        $this->newLine();
        $this->info('Configure this secret in your Qredit dashboard webhook settings.');

        return Command::SUCCESS;
    }

    /**
     * Show webhook information.
     */
    protected function showWebhookInfo(): int
    {
        $this->info('Qredit Webhook Configuration');
        $this->newLine();

        $webhookUrl = url(config('qredit.webhook.prefix', '') . config('qredit.webhook.path', '/qredit/webhook'));

        $this->table(
            ['Setting', 'Value'],
            [
                ['Status', config('qredit.webhook.enabled') ? 'Enabled' : 'Disabled'],
                ['Webhook URL', $webhookUrl],
                ['Path', config('qredit.webhook.path')],
                ['Prefix', config('qredit.webhook.prefix') ?: '(none)'],
                ['Middleware', implode(', ', config('qredit.webhook.middleware', []))],
                ['Secret Configured', config('qredit.webhook.secret') ? 'Yes' : 'No'],
                ['Signature Verification', config('qredit.verify_webhook_signature') ? 'Enabled' : 'Disabled'],
            ]
        );

        $this->newLine();
        $this->info('To configure webhooks:');
        $this->line('1. Copy the webhook URL above');
        $this->line('2. Add it to your Qredit dashboard webhook settings');
        $this->line('3. Generate a secret with: php artisan qredit:webhook --generate-secret');
        $this->line('4. Configure the secret in both .env and Qredit dashboard');

        if (!config('qredit.webhook.secret')) {
            $this->newLine();
            $this->warn('⚠ Webhook secret is not configured!');
            $this->line('Run: php artisan qredit:webhook --generate-secret');
        }

        return Command::SUCCESS;
    }
}