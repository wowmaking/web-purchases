<?php

namespace Wowmaking\WebPurchases\Interfaces;

use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

interface PurchaseService
{
    public function getPrices(): array;

    public function createCustomer($data): Customer;

    public function getCustomer($customerId): Customer;

    public function updateCustomer($customerId, $data): Customer;

    public function createSubscription($data): Subscription;

    public function getSubscriptions($customerId): array;

    public function cancelSubscription($subscriptionId): Subscription;
}
