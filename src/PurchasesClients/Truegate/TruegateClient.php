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
    private const STATUS_TRIAL = 'TRIAL';
    private const STATUS_GRACE_PERIOD = 'GRACE_PERIOD';
    private const STATUS_DUNNING_PERIOD = 'GRACE_PERIOD';
    private const STATUS_UNPAID = 'UNPAID';
    private const STATUS_CANCELLED = 'CANCELLED';
    private const STATUS_INCOMPLETE = 'INCOMPLETE';
    private const STATUS_INCOMPLETE_EXPIRED = 'INCOMPLETE_EXPIRED';



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
        return $data;
    }

    public function getSubscriptions(string $customerId): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function cancelSubscription(string $subscriptionId, bool $force = false): Subscription
    {
        $params = ['projectId'=> $this->projectId, 'subscriptionId' => $subscriptionId];
        $this->getProvider()->cancelSubscription($params, 'Cancel request.');
        return new Subscription();
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        $subscription = new Subscription();
        $subscription->setTransactionId($providerResponse['subscriptionId']);
        $subscription->setPlanName($providerResponse['subscriptionProductPlanId']);
        $subscription->setEmail($providerResponse['email']);
        $subscription->setCurrency($providerResponse['currency']);
        $subscription->setAmount($providerResponse['amount']);
        $subscription->setCustomerId($providerResponse['customer_id']);

        $subscription->setCreatedAt($providerResponse['createdAt']);
        $subscription->setExpireAt($providerResponse['subscriptionNextChargeAt']);
        $subscription->setState($providerResponse['subscriptionStatus']);
        $subscription->setIsActive(
            in_array(
                $providerResponse['subscriptionStatus'],
                [self::STATUS_ACTIVE, self::STATUS_TRIAL, self::STATUS_DUNNING_PERIOD, self::STATUS_GRACE_PERIOD],
                true
            )
        );
        $subscription->setProvider(PurchasesClient::PAYMENT_SERVICE_TRUEGATE);
        $subscription->setProviderResponse($providerResponse);

        if (isset($providerResponse['subscription']['cancelled_at'])) {
            $subscription->setCanceledAt($providerResponse['subscription']['cancelled_at']);
        }

        if ($providerResponse['subscriptionStatus'] == self::STATUS_TRIAL) {
            $subscription->setTrialStartAt($providerResponse['createdAt']);
            if (isset($providerResponse['subscriptionNextChargeAt'])) {
                $subscription->setTrialEndAt($providerResponse['subscriptionNextChargeAt']);
            } else {
                $subscription->setTrialEndAt($providerResponse['subscription']['expired_at']);
            }
        }
        return $subscription;
    }

    public function startSubscription(string $planId, string $idfm, string $email, array $metadata = []) {
        $params = [
            'projectId'=> $this->projectId,
            'subscriptionProductPlanId' => $planId,
            'externalUserId' => $idfm,
            'email' => $email,
            'metadata' => $metadata
        ];
        return $this->getProvider()->startSubscription($params);
    }

    public function startOneTimePayment(float $amount, string $currency, string $idfm, string $email, array $metadata = []) {
        return ['transactionId'=> 'test-'.rand(0,999)."-".$amount, 'widget'=> 'https://sdfasdfasdf.com'];
        $params = [
            'projectId'=> $this->projectId,
            'externalUserId' => $idfm,
            'email' => $email,
            'currency' => $currency,
            'amount' => $amount,
            'metadata' => $metadata
        ];
        return $this->getProvider()->startOneTimePayment($params);
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
