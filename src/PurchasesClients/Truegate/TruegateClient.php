<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Truegate;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\PaypalProvider;
use Wowmaking\WebPurchases\Providers\TruegateProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\WithoutCustomerSupportTrait;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

/**
 * @method TruegateProvider getProvider()
 */
class TruegateClient extends PurchasesClient
{
    use WithoutCustomerSupportTrait;

    private const STATUS_ACTIVE = 'ACTIVE';

    private const TENURE_TYPE_TRIAL = 'TRIAL';
    private const TENURE_TYPE_REGULAR = 'REGULAR';

    private const INTERVAL_UNIT_DAYS_MAP = [
        'DAY' => 1,
        'WEEK' => 7,
        'MONTH' => 30,
        'YEAR' => 365
    ];

    private $projectId;

    private $isSandbox;

    public function __construct(string $projectId, string $secretKey, bool $isSandbox = false)
    {
        $this->projectId = $projectId;
        $this->isSandbox = $isSandbox;

        parent::__construct($secretKey);
    }

    public function getPrices(array $pricesIds = []): array
    {
        $params = ['projectId'=> $this->projectId];
        $plans = $this->getProvider()->listPlans($params, 1);

        $prices = [];

        foreach ($plans as $plan) {
            if ($plan['state'] !== self::STATUS_ACTIVE) {
                continue;
            }

            $price = new Price();
            $price->setId($plan['id']);
            $price->setType(Price::TYPE_SUBSCRIPTION);
            $price->setProductName($plan['name']);
            $price->setAmount($plan['price']);
            $price->setCurrency($plan['currency']);
            $price->setPeriod((int) $plan['duration'], (string) $plan['durationUnit']);
            if(isset($plan['trial']) && $plan['trial']){
                $intervalUnit = $plan['trial']['durationUnit'];
                $intervalCount = $plan['trial']['duration'];
                $intervalDays = self::INTERVAL_UNIT_DAYS_MAP[$intervalUnit] ?? 0;
                $price->setTrialPeriodDays($intervalDays * $intervalCount);
                $price->setTrialPriceAmount($plan['trial']['price']?$plan['trial']['price']:0);
            }
            $prices[] = $price;
        }
        return $prices;
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

    public function cancelSubscription(string $subscriptionId, bool $force = false): Subscription
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
        $this->setProvider(new TruegateProvider($this->secretKey, $this->isSandbox));
    }

    public function getSubscription(string $subscriptionId)
    {
        return $this->getProvider()->getSubscription($subscriptionId);
    }

    public function checkOrderStatus(string $orderId)
    {
        return $this->getProvider()->checkOrderStatus($orderId);
    }

    public function refund(string $orderId, float $amount, string $currency)
    {
        return $this->getProvider()->refund($orderId, $amount, $currency);
    }

    private function getCustomerIdFromCustomId(string $customId): string
    {
        $customIdParts = explode('|', $customId);

        return $customIdParts[0];
    }

    protected function getPurchaseClientType(): string
    {
        return self::PAYMENT_SERVICE_PAYPAL;
    }

    public function reactivate(string $subscriptionId): bool
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function changePlan(string $subscriptionId, string $planCode): bool
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function getTransactions($startDate, $endDate, $page) {
        return $this->getProvider()->getTransactions($startDate, $endDate, $page);
    }

    public function getDisputes($params){
        return $this->getProvider()->getDistutes($params);
    }

    public function getDisputeDetails($id) {
        return $this->getProvider()->getDistuteDetails($id);
    }

}
