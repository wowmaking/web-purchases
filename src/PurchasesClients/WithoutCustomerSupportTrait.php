<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\PurchasesClients;

use LogicException;
use Wowmaking\WebPurchases\Resources\Entities\Customer;

trait WithoutCustomerSupportTrait
{
    /**
     * @throws LogicException
     */
    abstract protected function throwNoRealization(string $methodName): void;

    public function isSupportsCustomers(): bool
    {
        return false;
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

    public function buildCustomerResource($providerResponse): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }
}
