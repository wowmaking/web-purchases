<?php

namespace Wowmaking\WebPurchases\Interfaces;

interface PurchasesInterface
{
    public function getPrices();

    public function createCustomer($data);

    public function getCustomer($customerId);

    public function updateCustomer($customerId, $data);

    public function createSubscription($data);

    public function getSubscriptions($customerId);

    public function cancelSubscription($subscriptionId);
}