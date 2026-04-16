<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Commands;

use Illuminate\Console\Command;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;
use Throwable;

/**
 * Signed request tester — the Postman replacement for Qredit's HMAC API.
 *
 * Examples:
 *   php artisan qredit:call auth
 *       --api-key=EdVfej... --secret-key=B9E0236B... --sandbox
 *
 *   php artisan qredit:call create-order
 *       --payload-file=/tmp/order.json
 *
 *   php artisan qredit:call list-payments
 *       --payload='{"dateFrom":"01/01/2026","dateTo":"31/12/2026"}'
 *
 *   php artisan qredit:call create-payment --dry-run
 *       --payload='{"amountCents":3200,"orderReference":"1234"}'
 */
class CallApiCommand extends Command
{
    protected $signature = 'qredit:call
                            {method? : Endpoint method — see --list for the full set}
                            {--api-key= : Public API key (falls back to QREDIT_API_KEY)}
                            {--secret-key= : Secret API key for signing (falls back to QREDIT_SECRET_KEY)}
                            {--sandbox : Force sandbox environment (default)}
                            {--production : Force production environment}
                            {--language=EN : Accept-Language header value}
                            {--scheme= : Authorization scheme prefix, defaults to config("qredit.signing.scheme")}
                            {--case=lower : Hex case for signature — lower|upper}
                            {--payload= : Inline JSON payload}
                            {--payload-file= : Read JSON payload from a file}
                            {--id= : Resource reference for get/update/delete calls}
                            {--reason= : Reason string for cancel calls}
                            {--dry-run : Sign + print the request but do not send it}
                            {--list : List supported methods and exit}
                            ';

    protected $description = 'Send a signed request to the Qredit gateway — replaces Postman when HMAC is required.';

    /**
     * Map of --method values to the Qredit service method they invoke.
     * Arg shape: ['method-name' => ['service' => 'createOrder', 'kind' => 'body']]
     *
     * kind:
     *   'body'        → payload JSON is passed as the first arg.
     *   'query'       → payload JSON is passed as the first arg (GET).
     *   'id'          → --id is the first arg, payload is optional second.
     *   'id+payload'  → --id first, payload second.
     *   'id+reason'   → --id first, --reason second.
     *   'none'        → no args.
     */
    protected const METHODS = [
        'auth' => ['service' => '__auth__', 'kind' => 'none'],
        'list-customers' => ['service' => 'listCustomers', 'kind' => 'query'],
        'list-orders' => ['service' => 'listOrders', 'kind' => 'query'],
        'create-order' => ['service' => 'createOrder', 'kind' => 'body'],
        'get-order' => ['service' => 'getOrder', 'kind' => 'id'],
        'update-order' => ['service' => 'updateOrder', 'kind' => 'id+payload'],
        'cancel-order' => ['service' => 'cancelOrder', 'kind' => 'id+reason'],
        'list-payments' => ['service' => 'listPayments', 'kind' => 'query'],
        'create-payment' => ['service' => 'createPayment', 'kind' => 'body'],
        'get-payment' => ['service' => 'getPayment', 'kind' => 'id'],
        'update-payment' => ['service' => 'updatePayment', 'kind' => 'id+payload'],
        'cancel-payment' => ['service' => 'deletePayment', 'kind' => 'id+reason'],
        'generate-qr' => ['service' => 'generateQR', 'kind' => 'query'],
        'calculate-fees' => ['service' => 'calculateFees', 'kind' => 'body'],
        'init-payment' => ['service' => 'initPayment', 'kind' => 'body'],
        'list-transactions' => ['service' => 'listTransactions', 'kind' => 'query'],
        'change-clearing-status' => ['service' => 'changeClearingStatus', 'kind' => 'body'],
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->listMethods();

            return self::SUCCESS;
        }

        $methodKey = $this->argument('method');

        if (! isset(self::METHODS[$methodKey])) {
            $this->error("Unknown method [{$methodKey}]. Run with --list to see supported methods.");

            return self::FAILURE;
        }

        $config = $this->buildClientOptions();

        $this->renderCredentialsSummary($methodKey, $config);

        if ($this->option('dry-run')) {
            return $this->runDryRun($methodKey, $config);
        }

        try {
            $qredit = Qredit::make($config + ['skip_auth' => $methodKey === 'auth']);
        } catch (Throwable $e) {
            $this->error('Failed to initialize Qredit client: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $result = $this->callService($qredit, $methodKey);
        } catch (Throwable $e) {
            $this->error('API call failed: '.$e->getMessage());

            if (method_exists($e, 'getResponse')) {
                $this->line('Response body:');
                $this->line(json_encode($e->getResponse(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return self::FAILURE;
        }

        $this->info('Response:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * Build the options array for Qredit::make(), pulling from flags then config.
     *
     * @return array<string, mixed>
     */
    protected function buildClientOptions(): array
    {
        $sandbox = ! $this->option('production');

        return [
            'api_key' => $this->option('api-key') ?? config('qredit.api_key'),
            'secret_key' => $this->option('secret-key') ?? config('qredit.secret_key'),
            'sandbox' => $sandbox,
            'language' => $this->option('language') ?? config('qredit.language', 'EN'),
            'auth_scheme' => $this->option('scheme') ?: config('qredit.signing.scheme'),
            'signature_case' => $this->option('case') ?? config('qredit.signing.case', 'lower'),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function renderCredentialsSummary(string $methodKey, array $config): void
    {
        $this->info('Qredit signed request');
        $this->table(
            ['Field', 'Value'],
            [
                ['Method', $methodKey.' → '.self::METHODS[$methodKey]['service']],
                ['Environment', $config['sandbox'] ? 'sandbox' : 'production'],
                ['API Key', $this->maskSecret($config['api_key'] ?? '')],
                ['Secret Key', $this->maskSecret($config['secret_key'] ?? '')],
                ['Scheme', $config['auth_scheme']],
                ['Case', $config['signature_case']],
                ['Language', $config['language']],
            ]
        );
    }

    protected function maskSecret(?string $s): string
    {
        if (! $s) {
            return '(unset)';
        }

        $len = strlen($s);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($s, 0, 4).str_repeat('*', max(4, $len - 8)).substr($s, -4);
    }

    protected function listMethods(): void
    {
        $rows = [];
        foreach (self::METHODS as $key => $meta) {
            $rows[] = [$key, $meta['service'], $meta['kind']];
        }

        $this->table(['--method', 'service call', 'args'], $rows);
    }

    /**
     * Parse --payload or --payload-file into an array.
     *
     * @return array<string, mixed>
     */
    protected function parsePayload(): array
    {
        if ($file = $this->option('payload-file')) {
            if (! is_file($file)) {
                throw new \RuntimeException("Payload file not found: {$file}");
            }
            $raw = (string) file_get_contents($file);
        } else {
            $raw = (string) ($this->option('payload') ?? '');
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON payload: '.json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function runDryRun(string $methodKey, array $config): int
    {
        try {
            $payload = $this->parsePayload();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Inject msgId if the payload doesn't already carry one so we can sign.
        if (! isset($payload['msgId'])) {
            $payload['msgId'] = 'dryrun_'.uniqid('', true);
        }

        $secret = $config['secret_key'] ?? '';
        if (! is_string($secret) || $secret === '') {
            $this->error('--secret-key is required for --dry-run.');

            return self::FAILURE;
        }

        $values = ValueFlattener::flatten($payload);

        $signature = HmacSigner::sign(
            $secret,
            (string) $payload['msgId'],
            $values,
            $config['signature_case'] === 'upper' ? HmacSigner::CASE_UPPER : HmacSigner::CASE_LOWER,
        );

        $this->info("Dry run — request would be for [{$methodKey}]:");
        $this->line('msgId:     '.$payload['msgId']);
        $this->line('sorted:    '.HmacSigner::buildMessage($values));
        $this->line('scheme:    '.$config['auth_scheme']);
        $this->line('signature: '.$signature);
        $this->line('header:    '.$config['auth_scheme'].' '.$signature);
        $this->newLine();
        $this->line('Payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * Dispatch the request through the Qredit service.
     */
    protected function callService(Qredit $qredit, string $methodKey): mixed
    {
        $meta = self::METHODS[$methodKey];
        $service = $meta['service'];

        if ($service === '__auth__') {
            $token = $qredit->authenticate(true);

            return ['token' => $token, 'cached_key' => sha1($qredit->getConnector()->getApiKey())];
        }

        $payload = $this->parsePayload();
        $id = $this->option('id');
        $reason = $this->option('reason');

        return match ($meta['kind']) {
            'body' => $qredit->{$service}($payload),
            'query' => $qredit->{$service}($payload),
            'id' => $qredit->{$service}($this->requireId($id)),
            'id+payload' => $qredit->{$service}($this->requireId($id), $payload),
            'id+reason' => $qredit->{$service}($this->requireId($id), $reason),
            'none' => $qredit->{$service}(),
        };
    }

    protected function requireId(?string $id): string
    {
        if (! $id) {
            throw new \RuntimeException('--id is required for this method.');
        }

        return $id;
    }
}
