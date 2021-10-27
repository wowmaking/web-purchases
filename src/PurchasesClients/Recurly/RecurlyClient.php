<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Recurly;

use Recurly\Client as Provider;
use Recurly\Errors\NotFound;
use Recurly\Resources\Plan;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class RecurlyClient extends PurchasesClient
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
     * @throws \Exception
     */
    public function createCustomer(array $data): Customer
    {
        $code = md5($data['email']);

        try {
            $response = $this->getProvider()->getAccount('code-' . $code);
        } catch (NotFound $e) {
            $response = $this->getProvider()->createAccount([
                'email' => $data['email'],
                'code' => $code
            ]);
        }

        return $this->buildCustomerResource($response);
    }

    /**
     * @param string $customerId
     * @param array $data
     * @return Customer
     * @throws \Exception
     */
    public function updateCustomer(string $customerId, array $data): Customer
    {
        $response = $this->getProvider()->updateAccount($customerId, $data);

        return $this->buildCustomerResource($response);
    }

    /**
     * @param string $customerId
     * @return Customer
     * @throws \Exception
     */
    public function getCustomer(string $customerId): Customer
    {
        $response = $this->getProvider()->getAccount($customerId);

        return $this->buildCustomerResource($response);
    }

    /**
     * @param string $customerId
     * @return array
     * @throws \Exception
     */
    public function getSubscriptions(string $customerId): array
    {
        $response = $this->getProvider()->listAccountSubscriptions('code-' . $customerId);

        $subscriptions = [];

        try {
            /** @var \Recurly\Resources\Subscription $item */
            foreach ($response as $item) {
                $subscriptions[] = $this->buildSubscriptionResource($item);
            }
        } catch (NotFound $e) {}

        return $subscriptions;
    }

    /**
     * @param array $data
     * @return \Recurly\Resources\Subscription
     */
    public function subscriptionCreationProcess(array $data): \Recurly\Resources\Subscription
    {
        return $this->getProvider()->createSubscription([
            'plan_code' => $data['price_id'],
            'account' => [
                'code' => $data['customer_id'],
                'email' => $data['email'],
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
     * @throws \Exception
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $response = $this->getProvider()->terminateSubscription($subscriptionId);

        return $this->buildSubscriptionResource($response);
    }

    /**
     * @param $providerResponse
     * @return Customer
     * @throws \Exception
     */
    public function buildCustomerResource($providerResponse): Customer
    {
        if (!$providerResponse instanceof \Recurly\Resources\Account) {
            throw new \Exception('Invalid data object for build customer resource, must be \Recurly\Resources\Account');
        }

        $customer = new Customer();
        $customer->setId($providerResponse->getId()); // recurly puts id in the id field! IT`S GREAT
        $customer->setEmail($providerResponse->getEmail());
        $customer->setProviderResponse($providerResponse->getResponse()->getRawResponse());

        return $customer;
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        if (!$providerResponse instanceof \Recurly\Resources\Subscription) {
            throw new \Exception('Invalid data object for build subscription resource, must be \Recurly\Resources\Subscription');
        }

        $subscription = new Subscription();
        $subscription->setTransactionId($providerResponse->getId());
        $subscription->setEmail($providerResponse->getAccount()->getEmail());
        $subscription->setCurrency($providerResponse->getCurrency());
        $subscription->setAmount($providerResponse->getUnitAmount());
        $subscription->setCustomerId($providerResponse->getAccount()->getCode()); // recurly puts id in the code field! WTF????
        $subscription->setCreatedAt($providerResponse->getCreatedAt());
        $subscription->setExpireAt($providerResponse->getExpiresAt());
        $subscription->setState($providerResponse->getState());
        $subscription->setProviderResponse($providerResponse->getResponse()->getRawResponse());

        return $subscription;
    }

}
