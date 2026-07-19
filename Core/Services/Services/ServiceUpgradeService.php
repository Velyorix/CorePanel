<?php

namespace Core\Services\Services;

use Carbon\Carbon;
use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\ProrataCalculationInput;
use Core\Billing\DataTransferObjects\ProrataResult;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Core\Billing\DataTransferObjects\RenewalLineInput;
use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Enums\ProrataDirection;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\InvoiceGenerationService;
use Core\Billing\Services\ProrataCalculationService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Services\ProductPricingCalculator;
use Core\Services\Contracts\ModuleActionDispatcher;
use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\DataTransferObjects\ServicePlanChangeData;
use Core\Services\DataTransferObjects\ServicePlanChangeResult;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Exceptions\InvalidServicePlanChangeException;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;

class ServiceUpgradeService
{
    public function __construct(
        private readonly ProrataCalculationService $prorata,
        private readonly ProductPricingCalculator $pricing,
        private readonly InvoiceGenerationService $invoices,
        private readonly ClientCreditService $credits,
        private readonly ModuleActionDispatcher $modules,
        private readonly ServiceActionLogger $logger,
    ) {
    }

    public function preview(Service $service, ServicePlanChangeData $data): ServicePlanChangeResult
    {
        return $this->resolve($service, $data, apply: false, performedBy: null);
    }

    public function changePlan(
        Service $service,
        ServicePlanChangeData $data,
        ?User $performedBy = null,
    ): ServicePlanChangeResult {
        return $this->resolve($service, $data, apply: true, performedBy: $performedBy);
    }

    private function resolve(
        Service $service,
        ServicePlanChangeData $data,
        bool $apply,
        ?User $performedBy,
    ): ServicePlanChangeResult {
        $service = $service->fresh(['client.owner', 'product']) ?? $service;
        $target = $this->assertValidChange($service, $data);

        $targetCycle = $data->billingCycle ?? $service->billing_cycle;
        $targetInterval = $data->billingCycle !== null
            ? $data->customIntervalDays
            : ($data->customIntervalDays ?? $service->custom_interval_days);
        $targetConfig = $data->configData ?? $service->config_data;

        if ($targetCycle === null) {
            throw new InvalidServicePlanChangeException('Service billing cycle is required for a plan change.');
        }

        $prorata = $this->calculateProrata($service, $target, $targetCycle, $targetInterval, $targetConfig);
        $action = $this->actionFor($prorata);

        if (! $apply) {
            return new ServicePlanChangeResult(
                service: $service,
                action: $action,
                prorata: $prorata,
                applied: false,
            );
        }

        return DB::transaction(function () use (
            $service,
            $target,
            $targetCycle,
            $targetInterval,
            $targetConfig,
            $prorata,
            $action,
            $data,
            $performedBy,
        ): ServicePlanChangeResult {
            $snapshot = [
                'from_product_id' => $service->product_id,
                'to_product_id' => $target->id,
                'from_billing_cycle' => $service->billing_cycle?->value,
                'to_billing_cycle' => $targetCycle->value,
                'prorata' => [
                    'direction' => $prorata->direction->value,
                    'net_amount' => $prorata->netAmount,
                    'unused_credit' => $prorata->unusedCredit,
                    'new_charge' => $prorata->newCharge,
                    'days_remaining' => $prorata->daysRemaining,
                    'period_days' => $prorata->periodDays,
                ],
            ];

            $service->forceFill([
                'product_id' => $target->id,
                'module' => $target->module,
                'billing_cycle' => $targetCycle,
                'custom_interval_days' => $targetCycle->requiresCustomInterval()
                    ? $targetInterval
                    : null,
                'config_data' => $targetConfig,
            ])->save();

            $service = $service->fresh(['client.owner', 'product']) ?? $service;

            $moduleResult = $this->modules->dispatch($service, $action);
            $moduleStatus = (string) ($moduleResult['status'] ?? 'failed');
            /** @var array<string, mixed> $moduleResponse */
            $moduleResponse = is_array($moduleResult['response'] ?? null)
                ? $moduleResult['response']
                : [];

            if ($moduleStatus === 'failed') {
                $this->logger->record(
                    $service,
                    $action,
                    ServiceActionLogStatus::Failed,
                    $performedBy?->id,
                    [...$snapshot, 'module' => $moduleResponse],
                );

                throw new InvalidServicePlanChangeException(sprintf(
                    'Module action [%s] failed for service #%d.',
                    $action->value,
                    $service->id,
                ));
            }

            $chargeInvoice = null;
            $creditTransaction = null;

            if ($data->applyBilling) {
                if ($prorata->direction === ProrataDirection::Charge) {
                    $chargeInvoice = $this->createChargeInvoice($service, $target, $targetCycle, $targetInterval, $prorata, $performedBy);
                    $snapshot['charge_invoice_id'] = $chargeInvoice->id;
                } elseif ($prorata->direction === ProrataDirection::Credit) {
                    $creditTransaction = $this->credits->add(
                        $service->client,
                        $prorata->netAmount,
                        ClientCreditTransactionType::Add,
                        description: __('Service #:id plan downgrade prorata credit', ['id' => $service->id]),
                        reference: sprintf('service:%d:downgrade:%d', $service->id, $service->id),
                        idempotencyKey: sprintf('service-plan-downgrade-%d-%s', $service->id, now()->format('YmdHisu')),
                        createdBy: $performedBy,
                    );
                    $snapshot['credit_transaction_id'] = $creditTransaction->id;
                }
            }

            $this->logger->record(
                $service,
                $action,
                ServiceActionLogStatus::Success,
                $performedBy?->id,
                [...$snapshot, 'module' => $moduleResponse],
            );

            return new ServicePlanChangeResult(
                service: $service,
                action: $action,
                prorata: $prorata,
                applied: true,
                chargeInvoice: $chargeInvoice,
                creditTransaction: $creditTransaction,
            );
        });
    }

    private function assertValidChange(Service $service, ServicePlanChangeData $data): Product
    {
        if (! $service->status->isBillable()) {
            throw new InvalidServicePlanChangeException(
                'Only active or suspended services can change plan.',
            );
        }

        if ($service->next_billing_date === null) {
            throw new InvalidServicePlanChangeException(
                'Service next_billing_date is required for prorata plan changes.',
            );
        }

        if ($service->billing_cycle === null) {
            throw new InvalidServicePlanChangeException(
                'Service billing cycle is required for a plan change.',
            );
        }

        $target = Product::query()->find($data->targetProductId);

        if ($target === null) {
            throw new InvalidServicePlanChangeException('Target product was not found.');
        }

        if ($target->status !== ProductStatus::Published) {
            throw new InvalidServicePlanChangeException('Target product must be published.');
        }

        $current = $service->product;

        if ($current === null) {
            throw new InvalidServicePlanChangeException('Service product was not found.');
        }

        if ($current->type !== $target->type) {
            throw new InvalidServicePlanChangeException('Target product must be the same product type.');
        }

        if ($current->category_id !== $target->category_id) {
            throw new InvalidServicePlanChangeException('Target product must be in the same category.');
        }

        if ((string) $current->module !== (string) $target->module) {
            throw new InvalidServicePlanChangeException('Target product must use the same module.');
        }

        $targetCycle = $data->billingCycle ?? $service->billing_cycle;
        $targetInterval = $data->billingCycle !== null
            ? $data->customIntervalDays
            : ($data->customIntervalDays ?? $service->custom_interval_days);

        if ($targetCycle === null) {
            throw new InvalidServicePlanChangeException('Target billing cycle is required.');
        }

        if ($targetCycle->requiresCustomInterval()) {
            try {
                $targetCycle->days($targetInterval);
            } catch (\InvalidArgumentException $exception) {
                throw new InvalidServicePlanChangeException($exception->getMessage(), 0, $exception);
            }
        }

        $pricing = $target->pricingFor($targetCycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidServicePlanChangeException(
                'Target product has no enabled pricing for the selected billing cycle.',
            );
        }

        $targetConfig = $data->configData ?? $service->config_data;

        if (
            $service->product_id === $target->id
            && $service->billing_cycle === $targetCycle
            && $service->custom_interval_days === ($targetCycle->requiresCustomInterval() ? $targetInterval : null)
            && $service->config_data == $targetConfig
        ) {
            throw new InvalidServicePlanChangeException('Service is already on the requested plan.');
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>|null  $targetConfig
     */
    private function calculateProrata(
        Service $service,
        Product $target,
        BillingCycle $targetCycle,
        ?int $targetInterval,
        ?array $targetConfig,
    ): ProrataResult {
        $periodEnd = $service->next_billing_date->copy()->startOfDay();
        $periodDays = $service->billing_cycle->days($service->custom_interval_days);
        $periodStart = $periodEnd->copy()->subDays($periodDays);

        $oldAddons = $this->addonsFromConfig($service->config_data);
        $newAddons = $this->addonsFromConfig($targetConfig);

        $oldAmount = $this->pricing->recurringTotalForProduct(
            $service->product,
            $service->billing_cycle,
            $oldAddons,
        );
        $newAmount = $this->pricing->recurringTotalForProduct(
            $target,
            $targetCycle,
            $newAddons,
        );

        return $this->prorata->calculate(new ProrataCalculationInput(
            oldPeriodAmount: $oldAmount,
            newPeriodAmount: $newAmount,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            changeAt: Carbon::now(),
            billingCycle: $service->billing_cycle,
            customIntervalDays: $service->custom_interval_days,
        ));
    }

    private function actionFor(ProrataResult $prorata): ServiceAction
    {
        return match ($prorata->direction) {
            ProrataDirection::Charge => ServiceAction::Upgrade,
            ProrataDirection::Credit => ServiceAction::Downgrade,
            ProrataDirection::None => ((float) $prorata->newPeriodAmount >= (float) $prorata->oldPeriodAmount)
                ? ServiceAction::Upgrade
                : ServiceAction::Downgrade,
        };
    }

    private function createChargeInvoice(
        Service $service,
        Product $target,
        BillingCycle $targetCycle,
        ?int $targetInterval,
        ProrataResult $prorata,
        ?User $performedBy,
    ): Invoice {
        $input = RenewalInvoiceInput::fromClient(
            $service->client,
            serviceId: $service->id,
            billingPeriodEnd: $service->next_billing_date,
            line: new RenewalLineInput(
                description: __('Service #:id plan upgrade (prorata)', ['id' => $service->id]),
                unitPrice: $prorata->netAmount,
                productId: $target->id,
                productName: $target->name,
                productSlug: $target->slug,
                billingCycle: $targetCycle,
                customIntervalDays: $targetCycle->requiresCustomInterval() ? $targetInterval : null,
            ),
            notes: __('Prorata upgrade charge for service #:id', ['id' => $service->id]),
            createdBy: $performedBy?->id,
        );

        return $this->invoices->createServiceCharge($input, $performedBy);
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return list<string>
     */
    private function addonsFromConfig(?array $config): array
    {
        $addons = $config['addons'] ?? [];

        if (! is_array($addons)) {
            return [];
        }

        return array_values(array_filter(
            $addons,
            static fn (mixed $addon): bool => is_string($addon) && $addon !== '',
        ));
    }
}
