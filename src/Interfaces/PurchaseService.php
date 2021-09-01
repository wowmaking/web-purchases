<?php

namespace Wowmaking\WebPurchases\Interfaces;

use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

interface PurchaseService
{
    public function getPrices(): array;

    public function createCustomer(array $data): Customer;

    public function getCustomer(string $customerId): Customer;

    public function updateCustomer(string $customerId, array $data): Customer;

    public function createSubscription(array $data): Subscription;

    public function getSubscriptions(string $customerId): array;

    public function cancelSubscription(string $subscriptionId): Subscription;

    public function buildSubscription($data): Subscription;
}
