<?php

namespace Wowmaking\WebPurchases\Interfaces;

use Wowmaking\WebPurchases\Dto\TrackDataDto;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

interface PurchasesClientInterface
{
    public function isSupportsCustomers(): bool;

    public function isSupportsPrices(): bool;

    public function getPaymentFormData(array $attributes): array;

    /**
     * @param array $pricesIds
     * @return Price[]
     */
    public function getPrices(array $pricesIds = []): array;

    /**
     * @param array $data
     * @return Customer
     */
    public function createCustomer(array $data): Customer;

    /**
     * @return Customer[]
     */
    public function getCustomers(array $params): array;

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

    public function createSubscription(array $data, TrackDataDto $trackDataDto = null): Subscription;

    /**
     * @param array $data
     * @return mixed
     */
    public function subscriptionCreationProcess(array $data);

    /**
     * @param string $customerId
     * @return Subscription[]
     */
    public function getSubscriptions(string $customerId): array;

    /**
     * @param string $subscriptionId
     * @return Subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $force = false): Subscription;

    /**
     * @param $providerResponse
     * @return Customer
     */
    public function buildCustomerResource($providerResponse): Customer;

    /**
     * @param $providerResponse
     * @return Subscription
     */
    public function buildSubscriptionResource($providerResponse): Subscription;

    public function loadProvider();
}
