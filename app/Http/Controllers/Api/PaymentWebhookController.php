<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Core\Billing\Exceptions\InvalidPaymentException;
use Core\Billing\Exceptions\InvalidPaymentWebhookException;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incoming provider webhooks — signature verification is delegated to the gateway.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        try {
            $payment = $this->payments->handleWebhook(
                $gateway,
                $this->payload($request),
                $this->headers($request),
            );
        } catch (UnknownPaymentGatewayException|UnsupportedGatewayOperationException) {
            return response()->json([
                'message' => 'Webhook endpoint not available for this gateway.',
            ], 404);
        } catch (InvalidPaymentWebhookException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        } catch (InvalidPaymentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'payment_id' => $payment?->id,
            'status' => $payment?->status->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $json = $request->json()?->all();

        if (is_array($json) && $json !== []) {
            return $json;
        }

        $all = $request->all();

        return is_array($all) ? $all : [];
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (! is_string($name) || $name === '' || ! is_array($values) || $values === []) {
                continue;
            }

            $value = $values[0] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $headers[strtolower($name)] = $value;
        }

        return $headers;
    }
}
