<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Stripe;

use Stripe\StripeClient;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class Stripe extends PurchasesClient
{
    public function loadProvider()
    {
        $this->setProvider(new StripeClient($this->getConfig()->getSecretApiKey()));
    }

    /**
     * @return StripeClient
     */
    public function getProvider(): StripeClient
    {
        return $this->provider;
    }

    /**
     * @return array
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getPrices(): array
    {
        $response = $this->getProvider()->prices->all(['expand' => ['data.tiers']]);

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
        $response = $this->getProvider()->customers->create($data);

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
        $response = $this->getProvider()->customers->retrieve($customerId);

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
        $response = $this->getProvider()->customers->update($customerId, $data);

        $customer = new Customer();
        $customer->setId($response->id);

        return $customer;
    }

    /**
     * @param string $customerId
     * @return Subscription[]
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getSubscriptions(string $customerId): array
    {
        $response = $this->getProvider()->subscriptions->all([
            'customer' => $customerId
        ]);

        $subscriptions = [];

        /** @var \Stripe\Subscription $item */
        foreach ($response as $item) {
            $subscriptions[] = $this->buildSubscriptionResource($item);
        }

        return $subscriptions;
    }

    /**
     * @param array $data
     * @return mixed|\Stripe\Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function subscriptionCreationProcess(array $data)
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

        $customer = $this->getProvider()->customers->retrieve($data['customer_id']);
        if (!isset($customer->invoice_settings->default_payment_method)) {
            $this->getProvider()->paymentMethods->attach($data['payment_method_id'], [
                'customer' => $data['customer_id']
            ]);
        }

        return $this->getProvider()->subscriptions->create($params);
    }

    /**
     * @param string $subscriptionId
     * @return Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $response = $this->getProvider()->subscriptions->retrieve($subscriptionId)->cancel();

        return $this->buildSubscriptionResource($response);
    }

    /**
     * @param \Stripe\Subscription $data
     * @return Subscription
     */
    public function buildSubscriptionResource($data): Subscription
    {
        $subscription = new Subscription();
        $subscription->setTransactionId($data->id);
        $subscription->setCustomerId($data->customer);
        $subscription->setCreatedAt(date('Y-m-d H:i:s', $data->created));
        $subscription->setExpireAt(isset($data->ended_at) ? date('Y-m-d H:i:s', $data->ended_at) : null);
        $subscription->setState($data->status);

        return $subscription;
    }
}
