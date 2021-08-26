<?php

namespace Wowmaking\WebPurchases\PurchaseServices\Recurly;

use Recurly\Client;
use Recurly\Pager;
use Recurly\RecurlyError;
use Recurly\Resources\Account;
use Recurly\Resources\Plan;
use Recurly\Resources\Subscription;
use Wowmaking\WebPurchases\Interfaces\PurchaseService;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;

class Recurly implements PurchaseService
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
     * @return Price[]
     */
    public function getPrices(): array
    {
        $response = $this->getClient()->listPlans();

        $prices = [];

        /** @var Plan $item */
        foreach ($response as $item) {

            $price = new Price();
            $price->setId($item->getCode());
            $price->setAmount($item->getCurrencies()[0]->getUnitAmount());
            $price->setCurrency($item->getCurrencies()[0]->getCurrency());
            $price->setTrialPeriodDays($item->getTrialLength());
            $price->setTrialPriceAmount($item->getCurrencies()[0]->getSetupFee());

            $prices[] = $price;
        }

        return $prices;
    }

    /**
     * @param $params
     * @return Customer
     */
    public function createCustomer($params): Customer
    {
        $code = md5($params['email']);

        try {
            $response = $this->getClient()->getAccount('code-' . $code);
        } catch (RecurlyError $e) {
            $response = $this->getClient()->createAccount([
                'email' => $code,
                'code' => md5($params['email'])
            ]);
        }

        $customer = new Customer();
        $customer->setId($response->getId());
        $customer->setEmail($response->getEmail());

        return $customer;
    }

    /**
     * @param $customerId
     * @param $params
     * @return Customer
     */
    public function updateCustomer($customerId, $params): Customer
    {
        $response = $this->getClient()->updateAccount($customerId, $params);

        $customer = new Customer();
        $customer->setId($response->getId());

        return $customer;
    }

    /**
     * @param $customerId
     * @return Customer
     */
    public function getCustomer($customerId): Customer
    {
        $response = $this->getClient()->getAccount($customerId);

        $customer = new Customer();
        $customer->setId($response->getId());

        return $customer;
    }

    /**
     * @param $customerId
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription[]
     */
    public function getSubscriptions($customerId): array
    {
        $response = $this->getClient()->listAccountSubscriptions($customerId);

        $subscriptions = [];

        /** @var Subscription $item */
        foreach ($response as $item) {
            $subscription = new \Wowmaking\WebPurchases\Resources\Entities\Subscription();
            $subscription->setTransactionId($item->getId());
            $subscription->setCreatedAt($item->getCreatedAt());

            $subscriptions[] = $subscription;
        }

        return $subscriptions;
    }

    /**
     * @param $params
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription
     */
    public function createSubscription($params): \Wowmaking\WebPurchases\Resources\Entities\Subscription
    {
        $response = $this->getClient()->createSubscription($params);

        $subscription = new \Wowmaking\WebPurchases\Resources\Entities\Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }

    /**
     * @param $subscriptionId
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription
     */
    public function cancelSubscription($subscriptionId): \Wowmaking\WebPurchases\Resources\Entities\Subscription
    {
        $response = $this->getClient()->cancelSubscription($subscriptionId);

        $subscription = new \Wowmaking\WebPurchases\Resources\Entities\Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }

}
