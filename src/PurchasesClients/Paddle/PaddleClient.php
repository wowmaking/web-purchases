<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Paddle;

use DateInterval;
use DateTimeImmutable;
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
        $customerId = $data['customer_id'] ?? null;
        $subscriptionId = $data['subscription_id'] ?? null;

        if (!$customerId || !$subscriptionId) {
            throw new InvalidArgumentException('Not all required parameters were passed.');
        }

        $paypalSubscriptionData = $this->getSubscription($subscriptionId);

        $customId = $paypalSubscriptionData['custom_id'] ?? null;

        if (!$customId) {
            throw new LogicException('Missed custom id for subscription.');
        }

        $subscriptionCustomerId = $this->getCustomerIdFromCustomId($customId);

        if ($subscriptionCustomerId !== $customerId) {
            throw new LogicException('Subscription assigned for another customer.');
        }

        return $paypalSubscriptionData;
    }

    public function getSubscriptions(string $customerId): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function cancelSubscription(string $subscriptionId): Subscription
    {
        $this->getProvider()->cancelSubscription($subscriptionId, 'Cancel request.');

        $paypalSubscriptionData = $this->getSubscription($subscriptionId);

        return $this->buildSubscriptionResource($paypalSubscriptionData);
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        $trialInterval = null;
        $regularInterval = null;

        $plan = $this->getProvider()->getPlan($providerResponse['plan_id']);

        if (isset($plan['billing_cycles']) && $plan['billing_cycles']) {
            $trialCycle = false;
            foreach ($plan['billing_cycles'] as $billingCycle) {
                if ($billingCycle['tenure_type'] === self::TENURE_TYPE_REGULAR) {
                    $intervalUnit = $billingCycle['frequency']['interval_unit'];
                    $intervalCount = $billingCycle['frequency']['interval_count'];

                    $regularInterval = DateInterval::createFromDateString("$intervalCount $intervalUnit");
                }

                if ($billingCycle['tenure_type'] === self::TENURE_TYPE_TRIAL && !$trialCycle) {
                    $trialCycle = true;

                    $intervalUnit = $billingCycle['frequency']['interval_unit'];
                    $intervalCount = $billingCycle['frequency']['interval_count'];

                    $trialInterval = DateInterval::createFromDateString("$intervalCount $intervalUnit");
                }
            }
        }

        $subscription = new Subscription();

        $subscription->setTransactionId($providerResponse['id']);
        $subscription->setPlanName($providerResponse['plan_id']);
        $subscription->setEmail($providerResponse['subscriber']['email_address']);
        $subscription->setCurrency(
            $providerResponse['billing_info']['last_payment']['amount']['currency_code']
            ?? $providerResponse['shipping_amount']['currency_code']
        );
        $subscription->setAmount($providerResponse['billing_info']['last_payment']['amount']['value']
            ?? $providerResponse['shipping_amount']['value']);
        $subscription->setCustomerId($this->getCustomerIdFromCustomId($providerResponse['custom_id']));
        $subscription->setCreatedAt($providerResponse['create_time']);

        $startDate = new DateTimeImmutable($providerResponse['start_time']);

        if ($trialInterval) {
            $subscription->setTrialStartAt($startDate->format('c'));
            $subscription->setTrialEndAt($startDate->add($trialInterval)->format('c'));
        }

        if ($regularInterval) {
            $regularEnds = $startDate->add($regularInterval);

            if ($trialInterval) {
                $regularEnds = $regularEnds->add($trialInterval);
            }

            $subscription->setExpireAt($regularEnds->format('c'));
        }

        $subscription->setState($providerResponse['status']);
        $subscription->setIsActive($providerResponse['status'] === self::STATUS_ACTIVE);
        $subscription->setProvider(PurchasesClient::PAYMENT_SERVICE_PAYPAL);
        $subscription->setProviderResponse($providerResponse);

        return $subscription;
    }

    public function loadProvider(): void
    {
        $this->setProvider(new PaddleProvider($this->vendorId, $this->vendorAuthCode, $this->isSandbox));
    }

    private function getSubscription(string $subscriptionId): array
    {
        return $this->getProvider()->getSubscription($subscriptionId);
    }

    private function getCustomerIdFromCustomId(string $customId): string
    {
        $customIdParts = explode('|', $customId);

        return $customIdParts[0];
    }

    protected function getPurchaseClientType(): string
    {
        return self::PAYMENT_SERVICE_PADDLE;
    }
}
