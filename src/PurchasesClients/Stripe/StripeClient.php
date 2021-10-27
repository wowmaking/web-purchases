<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Stripe;

use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use \Stripe\StripeClient as Provider;

class StripeClient extends PurchasesClient
{
    public function loadProvider()
    {
        $provider = new Provider($this->getSecretKey());

        $this->setProvider($provider);
    }

    /**
     * @return Provider
     */
    public function getProvider(): Provider
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

        return $this->buildCustomerResource($response);
    }

    /**
     * @param string $customerId
     * @return Customer
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getCustomer(string $customerId): Customer
    {
        $response = $this->getProvider()->customers->retrieve($customerId);

        return $this->buildCustomerResource($response);
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

        return $this->buildCustomerResource($response);
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
     * @return \Stripe\Subscription
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function subscriptionCreationProcess(array $data): \Stripe\Subscription
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
     * @param $providerResponse
     * @return Customer
     * @throws \Exception
     */
    public function buildCustomerResource($providerResponse): Customer
    {
        if (!$providerResponse instanceof \Stripe\Customer) {
            throw new \Exception('Invalid data object for build customer resource, must be \Stripe\Customer');
        }

        $customer = new Customer();
        $customer->setId($providerResponse->id);
        $customer->setEmail($providerResponse->email);
        $customer->setProviderResponse($providerResponse);

        return $customer;
    }

    /**
     * @param $providerResponse
     * @return Subscription
     * @throws \Exception
     */
    public function buildSubscriptionResource($providerResponse): Subscription
    {
        if (!$providerResponse instanceof \Stripe\Subscription) {
            throw new \Exception('Invalid data object for build subscription resource, must be \Stripe\Subscription');
        }

        $customer = $this->getCustomer($providerResponse->customer);

        $subscription = new Subscription();
        $subscription->setTransactionId($providerResponse->id);
        $subscription->setEmail($customer->getEmail());
        $subscription->setCurrency(strtoupper($providerResponse->plan->currency));
        $subscription->setAmount($providerResponse->plan->amount_paid / 100);
        $subscription->setCustomerId($customer->getId());
        $subscription->setCreatedAt(date('Y-m-d H:i:s', $providerResponse->created));
        $subscription->setExpireAt(isset($providerResponse->ended_at) ? date('Y-m-d H:i:s', $providerResponse->ended_at) : null);
        $subscription->setState($providerResponse->status);
        $subscription->setProviderResponse($providerResponse);

        return $subscription;
    }
}
