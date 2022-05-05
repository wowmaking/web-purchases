<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\PurchasesClients\Solidgate;

use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\SolidgateProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\WithoutCustomerSupportTrait;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

/**
 * @property SolidgateProvider $provider
 */
class SolidgateClient extends PurchasesClient
{
    use WithoutCustomerSupportTrait;

    private const STATUS_ACTIVE = 'active';

    protected $webHookProvider;

    /**
     * @var string
     */
    protected $merchantId;

    public function __construct(
        string $merchantId,
        string $secretKey,
        string $webhookMerchantId,
        string $webhookSecretKey
    ) {
        $this->merchantId = $merchantId;

        parent::__construct($secretKey);

        $this->webHookProvider = new SolidgateProvider($webhookMerchantId, $webhookSecretKey);
    }

    public function isSupportsPrices(): bool
    {
        return false;
    }

    public function getPrices(array $pricesIds = []): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function subscriptionCreationProcess(array $data)
    {
        $customerId = $data['customer_id'] ?? null;
        $subscriptionId = $data['subscription_id'] ?? null;

        if (!$customerId || !$subscriptionId) {
            throw new InvalidArgumentException('Not all required parameters were passed.');
        }

        $subscriptionData = $this->getSubscription($subscriptionId);

        if ($subscriptionData['customer']['customer_account_id'] !== $customerId) {
            throw new LogicException('Subscription assigned for another customer.');
        }

        return $subscriptionData;
    }

    public function getSubscriptions(string $customerId): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $result = json_decode(
            $this->provider->cancelSubscription(['subscription_id' => $subscriptionId]),
            true
        );

        if (!$result || $result['status'] !== 'ok') {
            $providerException = $this->provider->getException();

            if (!$providerException) {
                throw new LogicException('Something went wrong.');
            }

            throw $providerException;
        }


        return $this->buildSubscriptionResource($this->getSubscription($subscriptionId));
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        $subscription = new Subscription();

        $subscription->setTransactionId($providerResponse['subscription']['id']);
        $subscription->setPlanName($providerResponse['product']['id']);
        $subscription->setEmail($providerResponse['customer']['customer_email']);
        $subscription->setCurrency($providerResponse['product']['currency']);
        $subscription->setAmount(
            $providerResponse['product']['amount'] / 100
        ); // Solidgate provide amount like this: 999, which originally was 9.99
        $subscription->setCustomerId($providerResponse['customer']['customer_account_id']);
        $subscription->setCreatedAt($providerResponse['subscription']['started_at']);
        $subscription->setExpireAt($providerResponse['subscription']['expired_at']);
        $subscription->setState($providerResponse['subscription']['status']);
        $subscription->setIsActive($providerResponse['subscription']['status'] === self::STATUS_ACTIVE);
        $subscription->setProvider(PurchasesClient::PAYMENT_SERVICE_SOLIDGATE);
        $subscription->setProviderResponse($providerResponse);

        if ($providerResponse['subscription']['trial'] && isset($providerResponse['subscription']['next_charge_at'])) {
            $subscription->setTrialStartAt($providerResponse['subscription']['started_at']);
            $subscription->setTrialEndAt($providerResponse['subscription']['next_charge_at']);
        }

        return $subscription;
    }

    public function loadProvider()
    {
        $this->setProvider(new SolidgateProvider($this->merchantId, $this->secretKey));
    }

    public function getPaymentFormData(array $attributes): array
    {
        return $this->provider->formMerchantData($attributes);
    }

    public function validateSignature(string $incomeSignature, string $body): bool
    {
        $signature = $this->webHookProvider->generateSignature($body);

        return $incomeSignature === $signature;
    }

    protected function getSubscription(string $subscriptionId): array
    {
        return json_decode(
            $this->provider->subscriptionStatus(['subscription_id' => $subscriptionId]),
            true
        );
    }
}
