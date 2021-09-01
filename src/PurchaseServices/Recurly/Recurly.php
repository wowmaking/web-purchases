<?php

namespace Wowmaking\WebPurchases\PurchaseServices\Recurly;

use Recurly\Client;
use Recurly\RecurlyError;
use Recurly\Resources\Plan;
use Wowmaking\WebPurchases\Interfaces\PurchaseService;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

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
     * @return Subscription[]
     */
    public function getSubscriptions(string $customerId): array
    {
        $response = $this->getClient()->listAccountSubscriptions('code-' . $customerId);

        $subscriptions = [];

        /** @var \Recurly\Resources\Subscription $item */
        foreach ($response as $item) {
            $subscriptions[] = $this->buildSubscription($item);;
        }

        return $subscriptions;
    }

    /**
     * @param array $data
     * @return Subscription
     */
    public function createSubscription(array $data): Subscription
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

        return $this->buildSubscription($response);
    }

    /**
     * @param string $subscriptionId
     * @return Subscription
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $response = $this->getClient()->terminateSubscription($subscriptionId);

        return $this->buildSubscription($response);
    }

    /**
     * @param \Recurly\Resources\Subscription $data
     * @return Subscription
     */
    public function buildSubscription($data): Subscription
    {
        $subscription = new Subscription();
        $subscription->setTransactionId($data->getId());
        $subscription->setCustomerId($data->getAccount()->getId());
        $subscription->setCreatedAt($data->getCreatedAt());
        $subscription->setExpireAt($data->getExpiresAt());
        $subscription->setState($data->getState());

        return $subscription;
    }

}
