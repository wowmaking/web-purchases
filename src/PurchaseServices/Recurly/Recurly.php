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
     * @param array $data
     * @return Customer
     */
    public function createCustomer(array $data): Customer
    {
        $code = md5($data['email']);

        try {
            $response = $this->getClient()->getAccount('code-' . $code);
        } catch (RecurlyError $e) {
            $response = $this->getClient()->createAccount([
                'email' => $code,
                'code' => md5($data['email'])
            ]);
        }

        $customer = new Customer();
        $customer->setId($response->getId());
        $customer->setEmail($response->getEmail());

        return $customer;
    }

    /**
     * @param string $customerId
     * @param array $data
     * @return Customer
     */
    public function updateCustomer(string $customerId, array $data): Customer
    {
        $response = $this->getClient()->updateAccount($customerId, $data);

        $customer = new Customer();
        $customer->setId($response->getId());

        return $customer;
    }

    /**
     * @param string $customerId
     * @return Customer
     */
    public function getCustomer(string $customerId): Customer
    {
        $response = $this->getClient()->getAccount($customerId);

        $customer = new Customer();
        $customer->setId($response->getId());

        return $customer;
    }

    /**
     * @param string $customerId
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription[]
     */
    public function getSubscriptions(string $customerId): array
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
     * @param array $data
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription
     */
    public function createSubscription(array $data): \Wowmaking\WebPurchases\Resources\Entities\Subscription
    {
        $response = $this->getClient()->createSubscription([
            'plan_code' => $data['price_id'],
            'account' => [
                'code' => $data['customer_id'],
                'billing_info' => [
                    'token_id' => $data['token_id']
                ]
            ],
            'currency' => 'USD'
        ]);

        $subscription = new \Wowmaking\WebPurchases\Resources\Entities\Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }

    /**
     * @param string $subscriptionId
     * @return \Wowmaking\WebPurchases\Resources\Entities\Subscription
     */
    public function cancelSubscription(string $subscriptionId): \Wowmaking\WebPurchases\Resources\Entities\Subscription
    {
        $response = $this->getClient()->cancelSubscription($subscriptionId);

        $subscription = new \Wowmaking\WebPurchases\Resources\Entities\Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }

}
