<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Paddle;

use DateInterval;
use DateTimeImmutable;
use Faker\Provider\DateTime;
use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Factories\TrackParametersProviderFactory;
use Wowmaking\WebPurchases\Providers\PaddleProvider;
use Wowmaking\WebPurchases\Providers\PaypalProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\WithoutCustomerSupportTrait;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

/**
 * @method PaddleProvider getProvider()
 */
class PaddleClient extends PurchasesClient
{
    use WithoutCustomerSupportTrait;

    private const STATUS_ACTIVE = 'processed';
    private $vendorId;

    private $vendorAuthCode;

    private $isSandbox;

    public function __construct(string $vendorId, string $vendorAuthCode, bool $isSandbox = false)
    {
        $this->vendorId = $vendorId;
        $this->vendorAuthCode = $vendorAuthCode;
        $this->isSandbox = $isSandbox;
        $this->loadProvider();
        $this->trackParametersProviderFactory = new TrackParametersProviderFactory();
    }

    public function getPrices(array $pricesIds = []): array
    {
        $plans = $this->getProvider()->listPlans($pricesIds);
        $prices = [];

        foreach ($plans as $plan) {
            $price = new Price();
            $price->setId((string)$plan['id']);
            foreach($plan['recurring_price'] as $currency => $amount) {
                $price->setAmount($amount);
                $price->setCurrency($currency);
            }
            $price->setPeriod($plan['billing_period'], $plan['billing_type']);

            //Hardcode trial period and price for all subscription plan
            $price->setTrialPeriodDays(3);
            $price->setTrialPriceAmount(1);

            $prices[] = $price;
        }
        return $prices;
    }

    public function generateCustomPayLink($planId, $amount,  $currency,  $trialPriceAmount, $trialPeriodDays): string {
        $customPayLink = $this->getProvider()->generatePayLink($planId, $amount,  $currency,  $trialPriceAmount);
        return $customPayLink;
    }

    public function subscriptionCreationProcess(array $data)
    {
        $checkoutId = $data['checkout_id'] ?? null;

        if (!$checkoutId) {
            throw new InvalidArgumentException('Not all required parameters were passed.');
        }

        $paddleOrderDetails = $this->getOrder($checkoutId);
        $paddleOrderDetails['customer_id'] = $data['customer_id'];

        return $paddleOrderDetails;
    }

    public function getSubscriptions(string $customerId): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $this->getProvider()->cancelSubscription($subscriptionId, 'Cancel request.');

        $subscription = new Subscription();

        return $subscription;
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        $subscription = new Subscription();

        $subscription->setTransactionId($providerResponse['order']['subscription_id']);
        $subscription->setPlanName($providerResponse['order']['product_id']);
        $subscription->setEmail($providerResponse['order']['customer']['email']);
        $subscription->setCustomerId($providerResponse['customer_id']);
        $subscription->setCurrency($providerResponse['order']['currency']);
        $subscription->setAmount($providerResponse['order']['total']);
        $subscription->setCreatedAt(date('Y-m-d H:i:s'));

        $subscription->setState($providerResponse['state']);
        $subscription->setIsActive($providerResponse['state'] === self::STATUS_ACTIVE);
        $subscription->setProvider(PurchasesClient::PAYMENT_SERVICE_PADDLE);
        $subscription->setProviderResponse($providerResponse);
        return $subscription;
    }

    public function loadProvider(): void
    {
        $this->setProvider(new PaddleProvider($this->vendorId, $this->vendorAuthCode, $this->isSandbox));
    }

    private function getOrder(string $checkoutId): array
    {
        return $this->getProvider()->getOrder($checkoutId);
    }


    protected function getPurchaseClientType(): string
    {
        return self::PAYMENT_SERVICE_PADDLE;
    }
}
