<?php

namespace Wowmaking\WebPurchases\Services\RecurlyService;

use Recurly\Client;
use Recurly\Pager;
use Recurly\Resources\Account;
use Recurly\Resources\Subscription;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\Interfaces\PurchasesInterface;

class RecurlyService implements PurchasesInterface
{
    /** @var Client */
    protected $client;

    /** @var PaymentServiceConfig */
    protected $config;

    /**
     * RecurlyService constructor.
     * @param PaymentServiceConfig $config
     */
    public function __construct(PaymentServiceConfig $config)
    {
        $this->setConfig($config);

        $this->setClient(new Client($this->getConfig()->getSecretApiKey()));
    }

    /**
     * @return PaymentServiceConfig
     */
    public function getConfig(): PaymentServiceConfig
    {
        return $this->config;
    }

    /**
     * @param PaymentServiceConfig $config
     */
    public function setConfig(PaymentServiceConfig $config): void
    {
        $this->config = $config;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @return Pager
     */
    public function getPrices(): Pager
    {
        return $this->getClient()->listPlans();
    }

    /**
     * @param $params
     * @return Account
     */
    public function createCustomer($params): Account
    {
        return $this->getClient()->createAccount($params);
    }

    /**
     * @param $customerId
     * @param $params
     * @return Account
     */
    public function updateCustomer($customerId, $params): Account
    {
        return $this->getClient()->updateAccount($customerId, $params);
    }

    /**
     * @param $customerId
     * @return Account
     */
    public function getCustomer($customerId): Account
    {
        return $this->getClient()->getAccount($customerId);
    }

    /**
     * @param $customerId
     * @return Pager
     */
    public function getSubscriptions($customerId): Pager
    {
        return $this->getClient()->listAccountSubscriptions($customerId);
    }

    /**
     * @param $params
     * @return Subscription
     */
    public function createSubscription($params): Subscription
    {
        return $this->getClient()->createSubscription($params);
    }

    /**
     * @param $subscriptionId
     * @return Subscription
     */
    public function cancelSubscription($subscriptionId): Subscription
    {
        return $this->getClient()->cancelSubscription($subscriptionId);
    }

}