<?php

namespace Wowmaking\WebPurchases\Interfaces;

use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

interface PurchasesClientInterface
{
    /**
     * @return array
     */
    public function getPrices(): array;

    /**
     * @param array $data
     * @return Customer
     */
    public function createCustomer(array $data): Customer;

    /**
     * @param string $customerId
     * @return Customer
     */
    public function getCustomer(string $customerId): Customer;

    /**
     * @param string $customerId
     * @param array $data
     * @return Customer
     */
    public function updateCustomer(string $customerId, array $data): Customer;

    /**
     * @param array $data
     * @return Subscription
     */
    public function createSubscription(array $data): Subscription;

    /**
     * @param array $data
     * @return mixed
     */
    public function subscriptionCreationProcess(array $data);

    /**
     * @param string $customerId
     * @return array
     */
    public function getSubscriptions(string $customerId): array;

    /**
     * @param string $subscriptionId
     * @return Subscription
     */
    public function cancelSubscription(string $subscriptionId): Subscription;

    /**
     * @param $data
     * @return Subscription
     */
    public function buildSubscriptionResource($data): Subscription;

    public function loadProvider();
}
