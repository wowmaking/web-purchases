<?php

namespace Wowmaking\WebPurchases\PurchaseServices\Stripe;

use Wowmaking\WebPurchases\Interfaces\PurchaseService;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Stripe\StripeClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use Wowmaking\WebPurchases\Resources\Lists\Prices;

class Stripe implements PurchaseService
{
    /** @var StripeClient */
    protected $client;

    /** @var PaymentServiceConfig */
    protected $config;

    /**
     * StripeService constructor.
     * @param PaymentServiceConfig $config
     */
    public function __construct(PaymentServiceConfig $config)
    {
        $this->setConfig($config);

        $this->setClient(new StripeClient($this->getConfig()->getSecretApiKey()));
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
     * @return StripeClient
     */
    public function getClient(): StripeClient
    {
        return $this->client;
    }

    /**
     * @param StripeClient $client
     */
    public function setClient(StripeClient $client): void
    {
        $this->client = $client;
    }

    /**
     * @return array
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getPrices(): array
    {
        $response = $this->getClient()->prices->all(['expand' => ['data.tiers']]);

        $prices = [];

        /** @var \Stripe\Price $item */
        foreach ($response as $item) {

            $price = new Price();
            $price->setId($item->id);
            $price->setAmount($item->unit_amount);
            $price->setCurrency($item->currency);
            $price->setTrialPeriodDays($item->recurring->trial_period_days ?? 0);
            $price->setTrialPriceAmount(0);

            $prices[] = $price;
        }

        return $prices;
    }

    /**
     * @param array $data
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createCustomer(array $data): Customer
    {
        $response = $this->getClient()->customers->create($data);

        $customer = new Customer();
        $customer->setId($response->id);
        $customer->setEmail($response->email);

        return $customer;
    }

    /**
     * @param string $customerId
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getCustomer(string $customerId): Customer
    {
        $response = $this->getClient()->customers->retrieve($customerId);

        $customer = new Customer();
        $customer->setId($response->id);

        return $customer;
    }

    /**
     * @param string $customerId
     * @param array $data
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function updateCustomer(string $customerId, array $data): Customer
    {
        $response = $this->getClient()->customers->update($customerId, $data);

        $customer = new Customer();
        $customer->setId($response->id);

        return $customer;
    }

    /**
     * @param string $customerId
     * @return array
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getSubscriptions(string $customerId): array
    {
        $response = $this->getClient()->subscriptions->all([
            'customer' => $customerId
        ]);

        $subscriptions = [];

        /** @var \Stripe\Subscription $item */
        foreach ($response as $item) {
            $subscription = new Subscription();
            $subscription->setTransactionId($item->id);
            $subscription->setCreatedAt($item->created);

            $subscriptions[] = $subscription;
        }

        return $subscriptions;
    }

    /**
     * @param array $data
     * @return Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createSubscription(array $data): Subscription
    {
        $params = [
            'default_payment_method' => $data['payment_method_id'],
            'customer' => $data['customer_id'],
            'items' => [
                ['price' => $data['price_id']]
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ];

        if (isset($params['trial_period_days'])) {
            $params['trial_period_days'] = $data['trial_period_days'];
        }

        if (isset($params['invoice_price_id'])) {
            $params['add_invoice_items'][] = ['price' => $params['invoice_price_id']];
        }

        $response = $this->getClient()->subscriptions->create($params);

        $subscription = new Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }

    /**
     * @param string $subscriptionId
     * @return Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $response = $this->getClient()->subscriptions->retrieve($subscriptionId)->cancel();

        $subscription = new Subscription();
        $subscription->setTransactionId($response->id);
        $subscription->setCreatedAt($response->created);

        return $subscription;
    }
}
