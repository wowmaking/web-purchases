<?php

namespace Wowmaking\WebPurchases\Services\StripeService;

use Stripe\Collection;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\Interfaces\PurchasesInterface;

class StripeService implements PurchasesInterface
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
     * @param mixed $client
     */
    public function setClient($client): void
    {
        $this->client = $client;
    }

    /**
     * @return \Stripe\Collection
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getPrices(): Collection
    {
        return $this->getClient()->prices->all(['expand' => ['data.tiers']]);
    }

    /**
     * @param $data
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createCustomer($data): Customer
    {
        return $this->getClient()->customers->create($data);
    }

    /**
     * @param $customerId
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getCustomer($customerId): Customer
    {
        return $this->getClient()->customers->retrieve($customerId);;
    }

    /**
     * @param $customerId
     * @param $data
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function updateCustomer($customerId, $data): Customer
    {
        return $this->getClient()->customers->update($customerId, $data);;
    }

    /**
     * @param $data
     * @return Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createSubscription($data): Subscription
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

        return $this->getClient()->subscriptions->create($params);
    }

    /**
     * @param $customerId
     * @return Collection
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getSubscriptions($customerId): Collection
    {
        return $this->getClient()->subscriptions->all([
            'customer' => $customerId
        ]);
    }

    /**
     * @param $subscriptionId
     * @return Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function cancelSubscription($subscriptionId): Subscription
    {
        return $this->getClient()->subscriptions->retrieve($subscriptionId)->cancel();
    }
}