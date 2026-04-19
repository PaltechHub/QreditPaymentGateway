<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Commands;

use Illuminate\Console\Command;
use Qredit\LaravelQredit\Facades\Qredit;

class QreditTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qredit:test
                            {--force : Force re-authentication}';

    /**
     * The console command description.
     */
    protected $description = 'Test Qredit API connection and authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Qredit API connection...');

        try {
            // Test authentication
            $this->line('Authenticating...');
            $token = Qredit::authenticate($this->option('force'));
            $this->info('✓ Authentication successful');

            // Display configuration
            $this->newLine();
            $this->table(
                ['Configuration', 'Value'],
                [
                    ['Environment', Qredit::isSandbox() ? 'Sandbox' : 'Production'],
                    ['API URL', Qredit::getApiUrl()],
                    ['Token Caching', config('qredit.cache_token') ? 'Enabled' : 'Disabled'],
                    ['Webhook Path', config('qredit.webhook.path')],
                    ['Language', config('qredit.language', 'en')],
                ]
            );

            // Test API call
            $this->newLine();
            $this->line('Testing API call...');

            try {
                $payments = Qredit::listPayments(['limit' => 1]);
                $this->info('✓ API call successful');
            } catch (\Exception $e) {
                $this->warn('⚠ API call failed: ' . $e->getMessage());
                $this->line('This might be normal if you have no payments yet.');
            }

            $this->newLine();
            $this->info('All tests completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());

            $this->newLine();
            $this->warn('Please check your configuration:');
            $this->line('1. Ensure QREDIT_API_KEY is set in your .env file');
            $this->line('2. Check if QREDIT_SANDBOX is set correctly');
            $this->line('3. Verify your API key is valid');

            return Command::FAILURE;
        }
    }
}