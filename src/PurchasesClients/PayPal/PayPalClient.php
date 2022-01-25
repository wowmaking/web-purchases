<?php

namespace Wowmaking\WebPurchases\PurchasesClients\PayPal;

use LogicException;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class PayPalClient extends PurchasesClient
{
    public function isSupportsCustomers(): bool
    {
        return false;
    }

    public function getPrices(array $pricesIds = []): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function createCustomer(array $data): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function getCustomers(array $params): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function getCustomer(string $customerId): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function updateCustomer(string $customerId, array $data): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function subscriptionCreationProcess(array $data)
    {
        // TODO: Implement subscriptionCreationProcess() method.
    }

    public function getSubscriptions(string $customerId): array
    {
        // TODO: Implement getSubscriptions() method.
    }

    public function cancelSubscription(string $subscriptionId): Subscription
    {
        // TODO: Implement cancelSubscription() method.
    }

    public function buildCustomerResource($providerResponse): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        // TODO: Implement buildSubscriptionResource() method.
    }

    public function loadProvider()
    {
        // TODO: Implement loadProvider() method.
    }

    /**
     * @throws LogicException
     */
    private function throwNoRealization(string $methodName): void
    {
        throw new LogicException(sprintf('"%s" method is not realized yet.', $methodName));
    }

}