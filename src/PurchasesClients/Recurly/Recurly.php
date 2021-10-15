<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Recurly;

use Recurly\Client;
use Recurly\RecurlyError;
use Recurly\Resources\Plan;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class Recurly extends PurchasesClient
{
    public function loadProvider()
    {
        $this->setProvider(new Client($this->getConfig()->getSecretApiKey()));
    }

    /**
     * @return Client
     */
    public function getProvider(): Client
    {
        return $this->provider;
    }

    /**
     * @return Price[]
     */
    public function getPrices(): array
    {
        $response = $this->getProvider()->listPlans();

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
            $response = $this->getProvider()->getAccount('code-' . $code);
        } catch (RecurlyError $e) {
            $response = $this->getProvider()->createAccount([
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
        $response = $this->getProvider()->updateAccount($customerId, $data);

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
        $response = $this->getProvider()->getAccount($customerId);

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
        $response = $this->getProvider()->listAccountSubscriptions('code-' . $customerId);

        $subscriptions = [];

        /** @var \Recurly\Resources\Subscription $item */
        foreach ($response as $item) {
            $subscriptions[] = $this->buildSubscriptionResource($item);;
        }

        return $subscriptions;
    }

    /**
     * @param array $data
     * @return mixed|\Recurly\Resources\Subscription
     */
    public function subscriptionCreationProcess(array $data)
    {
        return $this->getProvider()->createSubscription([
            'plan_code' => $data['price_id'],
            'account' => [
                'code' => $data['customer_id'],
                'billing_info' => [
                    'token_id' => $data['token_id']
                ]
            ],
            'currency' => 'USD'
        ]);
    }

    /**
     * @param string $subscriptionId
     * @return Subscription
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $response = $this->getProvider()->terminateSubscription($subscriptionId);

        return $this->buildSubscriptionResource($response);
    }

    /**
     * @param \Recurly\Resources\Subscription $data
     * @return Subscription
     */
    public function buildSubscriptionResource($data): Subscription
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
