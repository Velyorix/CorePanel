<?php

namespace Tests\Unit\Providers;

use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\Models\Payment;
use Core\Providers\Contracts\NodeProviderInterface;
use Core\Providers\Contracts\PaymentGatewayInterface;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\NodeResourcesResponse;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ReflectionClass;
use ReflectionNamedType;

class ProviderContractsTest extends TestCase
{
    #[DataProvider('providerInterfacesProvider')]
    public function test_interface_defines_required_methods(
        string $interface,
        array $methods,
    ): void {
        $reflection = new ReflectionClass($interface);

        $this->assertTrue($reflection->isInterface());

        foreach ($methods as $methodName => $expectedReturnType) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "{$interface} must define [{$methodName}()].",
            );

            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();

            $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
            $this->assertSame($expectedReturnType, $returnType->getName());
        }
    }

    public function test_server_provider_interface_documents_lifecycle_contract(): void
    {
        $reflection = new ReflectionClass(ServerProviderInterface::class);
        $classDoc = (string) $reflection->getDocComment();
        $createDoc = (string) $reflection->getMethod('create')->getDocComment();

        $this->assertStringContainsString('Server lifecycle contract', $classDoc);
        $this->assertStringContainsString('idempotent', strtolower($createDoc));
    }

    public function test_node_provider_interface_documents_infrastructure_contract(): void
    {
        $reflection = new ReflectionClass(NodeProviderInterface::class);
        $doc = (string) $reflection->getDocComment();

        $this->assertStringContainsString('Node infrastructure contract', $doc);
        $this->assertStringContainsString('connection tests', strtolower($doc));
    }

    public function test_payment_gateway_interface_documents_billing_mapping(): void
    {
        $reflection = new ReflectionClass(PaymentGatewayInterface::class);
        $doc = (string) $reflection->getDocComment();

        $this->assertStringContainsString('Payment provider contract', $doc);
        $this->assertStringContainsString('charge()', $doc);
        $this->assertStringContainsString('refund()', $doc);
        $this->assertStringContainsString('validate()', $doc);
    }

    public static function providerInterfacesProvider(): array
    {
        return [
            'server provider' => [
                ServerProviderInterface::class,
                [
                    'key' => 'string',
                    'label' => 'string',
                    'create' => ProvisioningResponse::class,
                    'suspend' => ProvisioningResponse::class,
                    'unsuspend' => ProvisioningResponse::class,
                    'terminate' => ProvisioningResponse::class,
                    'reinstall' => ProvisioningResponse::class,
                    'getStatus' => ProvisioningResponse::class,
                ],
            ],
            'node provider' => [
                NodeProviderInterface::class,
                [
                    'key' => 'string',
                    'label' => 'string',
                    'testConnection' => NodeOperationResponse::class,
                    'sync' => NodeOperationResponse::class,
                    'getResources' => NodeResourcesResponse::class,
                ],
            ],
            'payment gateway' => [
                PaymentGatewayInterface::class,
                [
                    'key' => 'string',
                    'label' => 'string',
                    'charge' => PaymentGatewayResult::class,
                    'refund' => PaymentGatewayResult::class,
                    'validate' => PaymentGatewayResult::class,
                ],
            ],
        ];
    }

    public function test_contract_method_signatures_accept_expected_arguments(): void
    {
        $service = new Service([
            'id' => 1,
            'client_id' => 1,
            'product_id' => 1,
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $request = ProvisioningRequest::fromService($service);
        $node = NodeConnectionRequest::fromArray([
            'id' => 1,
            'module' => 'stub',
            'hostname' => 'node-01.example.test',
        ]);
        $payment = new Payment(['id' => 1, 'amount' => '10.00', 'currency' => 'EUR']);
        $context = new PaymentContext(returnUrl: 'https://example.test/return');

        $server = new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function getStatus(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: $request->externalId);
            }
        };

        $nodeProvider = new class implements NodeProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function testConnection(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function sync(NodeConnectionRequest $node): NodeOperationResponse
            {
                return NodeOperationResponse::success();
            }

            public function getResources(NodeConnectionRequest $node): NodeResourcesResponse
            {
                return NodeResourcesResponse::failed();
            }
        };

        $gateway = new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function charge(Payment $payment, PaymentContext $context): PaymentGatewayResult
            {
                return PaymentGatewayResult::pending();
            }

            public function refund(Payment $payment, string $amount): PaymentGatewayResult
            {
                return PaymentGatewayResult::refunded();
            }

            public function validate(Payment $payment): PaymentGatewayResult
            {
                return PaymentGatewayResult::completed();
            }
        };

        $this->assertInstanceOf(ProvisioningResponse::class, $server->create($request));
        $this->assertInstanceOf(ProvisioningResponse::class, $server->getStatus($request));
        $this->assertInstanceOf(NodeOperationResponse::class, $nodeProvider->testConnection($node));
        $this->assertInstanceOf(PaymentGatewayResult::class, $gateway->charge($payment, $context));
    }
}
