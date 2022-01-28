<?php

namespace Wowmaking\WebPurchases\PurchasesClients\PayPal;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\PaypalProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

/**
 * @method PaypalProvider getProvider()
 */
class PayPalClient extends PurchasesClient
{
    private const STATUS_ACTIVE = 'ACTIVE';

    private const TENURE_TYPE_TRIAL = 'TRIAL';
    private const TENURE_TYPE_REGULAR = 'REGULAR';

    private const INTERVAL_UNIT_DAYS_MAP = [
        'DAY' => 1,
        'WEEK' => 7,
        'MONTH' => 30,
        'YEAR' => 365
    ];

    private $clientId;

    private $isSandbox;

    public function __construct(string $clientId, string $secretKey, bool $isSandbox = false)
    {
        $this->clientId = $clientId;
        $this->isSandbox = $isSandbox;

        parent::__construct($secretKey);
    }

    public function isSupportsCustomers(): bool
    {
        return false;
    }

    public function getPrices(array $pricesIds = []): array
    {
        $plans = $this->getProvider()->listPlans($pricesIds);

        $prices = [];

        foreach ($plans as $plan) {
            if ($plan['status'] !== self::STATUS_ACTIVE) {
                continue;
            }

            if (!isset($plan['billing_cycles']) || !$plan['billing_cycles']) {
                continue;
            }

            $price = new Price();
            $price->setId($plan['id']);

            $trialCycle = false;
            foreach ($plan['billing_cycles'] as $billingCycle) {
                if ($billingCycle['tenure_type'] === self::TENURE_TYPE_REGULAR) {
                    $price->setAmount($billingCycle['pricing_scheme']['fixed_price']['value']);
                    $price->setCurrency($billingCycle['pricing_scheme']['fixed_price']['currency_code']);
                }

                if ($billingCycle['tenure_type'] === self::TENURE_TYPE_TRIAL && !$trialCycle) {
                    $trialCycle = true;

                    $intervalUnit = $billingCycle['frequency']['interval_unit'];
                    $intervalCount = $billingCycle['frequency']['interval_count'];

                    $intervalDays = self::INTERVAL_UNIT_DAYS_MAP[$intervalUnit] ?? 0;

                    $price->setTrialPeriodDays($intervalDays * $intervalCount);
                    $price->setTrialPriceAmount($billingCycle['pricing_scheme']['fixed_price']['value']);
                }
            }

            $prices[] = $price;
        }

        return $prices;
    }

    public function createCustomer(array $data): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function getCustomers(array $params): array
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function getCustomer(string $customerId): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function updateCustomer(string $customerId, array $data): Customer
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

    public function buildCustomerResource($providerResponse): Customer
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function buildSubscriptionResource($providerResponse): Subscription
    {
        $trialInterval = null;
        $regularInterval = null;

        $plans = $this->getProvider()->listPlans([$providerResponse['plan_id']]);
        $plan = current($plans);

        if ($plan && isset($plan['billing_cycles']) && $plan['billing_cycles']) {
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
        $subscription->setCurrency($providerResponse['shipping_amount']['currency_code']);
        $subscription->setAmount($providerResponse['shipping_amount']['value']);
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
        $this->setProvider(new PaypalProvider($this->clientId, $this->secretKey, $this->isSandbox));
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

    /**
     * @throws LogicException
     */
    private function throwNoRealization(string $methodName): void
    {
        throw new LogicException(sprintf('"%s" method is not realized yet.', $methodName));
    }

}