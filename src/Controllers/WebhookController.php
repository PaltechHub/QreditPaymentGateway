<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Exceptions\QreditException;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from Qredit.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Get the signature from headers
            $signature = $request->header('X-Qredit-Signature')
                ?? $request->header('X-Signature')
                ?? null;

            // Process the webhook
            $processed = Qredit::processWebhook(
                $request->all(),
                $signature
            );

            // Log the webhook if debug is enabled
            if (config('qredit.debug', false)) {
                Log::channel(config('qredit.logging.channel', 'stack'))
                    ->debug('Qredit webhook received', $processed);
            }

            // Dispatch general webhook received event
            event(new WebhookReceived($processed));

            // Dispatch specific events based on webhook type
            $this->dispatchSpecificEvent($processed);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (QreditException $e) {
            Log::channel(config('qredit.logging.channel', 'stack'))
                ->error('Qredit webhook error', [
                    'error' => $e->getMessage(),
                    'payload' => $request->all(),
                ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            Log::channel(config('qredit.logging.channel', 'stack'))
                ->error('Unexpected webhook error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Dispatch specific events based on webhook type.
     */
    protected function dispatchSpecificEvent(array $processed): void
    {
        $eventType = $processed['event'] ?? null;
        $data = $processed['data'] ?? [];

        switch ($eventType) {
            case 'payment.completed':
            case 'payment.success':
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

            // Add more event types as needed
        }
    }
}