<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\QreditManager;

/**
 * Ready-made webhook receiver.
 *
 * Multi-tenant flow:
 *   1. TenantResolver::tenantIdFromWebhook() extracts the tenant from the
 *      request (usually the `{tenant}` route param the macro wires up).
 *   2. QreditManager resolves that tenant's client, which holds their specific
 *      secret key.
 *   3. That client verifies the Authorization HMAC against the tenant's secret.
 *   4. Events are dispatched with the tenant id so listeners can scope work.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected QreditManager $manager,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::warning('Qredit webhook: INBOUND (raw, pre-verification)', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_params' => $request->route()?->parameters() ?? [],
            'authorization' => $request->header('Authorization'),
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->header('User-Agent'),
            'headers' => collect($request->headers->all())
                ->except(['cookie', 'authorization'])
                ->map(static fn ($v) => is_array($v) && count($v) === 1 ? $v[0] : $v)
                ->toArray(),
            'raw_body' => $request->getContent(),
            'payload' => $request->all(),
        ]);

        $tenantId = $this->manager->tenants()->tenantIdFromWebhook($request);
        $payload = $request->all();

        try {
            $client = $this->manager->forTenant($tenantId);

            $processed = $client->processWebhook(
                $payload,
                $request->header('Authorization'),
            );

            $processed['tenant_id'] = $tenantId;

            if (config('qredit.debug', false)) {
                Log::channel(config('qredit.logging.channel', 'stack'))
                    ->debug('Qredit webhook received', $processed);
            }

            event(new WebhookReceived($processed));
            $this->dispatchSpecificEvent($processed);

            return response()->json(['status' => 'RECEIVED']);
        } catch (QreditException $e) {
            Log::channel(config('qredit.logging.channel', 'stack'))
                ->warning('Qredit webhook rejected', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);

            return response()->json(['status' => 'rejected', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel(config('qredit.logging.channel', 'stack'))
                ->error('Qredit webhook handler blew up', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function dispatchSpecificEvent(array $processed): void
    {
        $eventType = $processed['event'] ?? null;
        $data = ($processed['data'] ?? []) + ['_tenant_id' => $processed['tenant_id'] ?? null];

        switch ($eventType) {
            case 'payment.completed':
            case 'payment.success':
            case 'transaction':
                event(new PaymentCompleted($data));
                break;

            case 'payment.failed':
            case 'payment.declined':
                event(new PaymentFailed($data));
                break;

            case 'order.cancelled':
            case 'order.canceled':
                event(new OrderCancelled($data));
                break;
        }
    }
}
