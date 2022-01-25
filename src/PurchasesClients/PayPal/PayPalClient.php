<?php

namespace Wowmaking\WebPurchases\PurchasesClients\PayPal;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\PaypalProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

/**
 * @method PaypalProvider getProvider()
 */
class PayPalClient extends PurchasesClient
{
    private const TENURE_TYPE_TRIAL = 'TRIAL';
    private const TENURE_TYPE_REGULAR = 'REGULAR';

    private $clientId;

    private $isSandbox;

    public function __construct(string $clientId, string $secretKey, bool $isSandbox = false)
    {
        parent::__construct($secretKey);

        $this->clientId = $clientId;
        $this->isSandbox = $isSandbox;
    }

    public function isSupportsCustomers(): bool
    {
        return false;
    }

    public function getPrices(array $pricesIds = []): array
    {
        $this->throwNoRealization(__METHOD__);
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
        // Getting single cycle length
        $startDate = new DateTimeImmutable($providerResponse['start_time']);
        $nextPaymentData = new DateTimeImmutable($providerResponse['billing_info']['next_billing_time']);

        $paymentCycleLength = $startDate->diff($nextPaymentData);

        // Getting trial start/end & expire_at
        $cycles = $providerResponse['billing_info']['cycle_executions'];

        $trialStartsAt = null;
        $trialEndsAt = null;
        $expiresAt = null;

        $trialCycles = 0;
        $totalCycles = 0;

        foreach ($cycles as $cycle) {
            if ($cycle['tenure_type'] === self::TENURE_TYPE_TRIAL) {
                if ($trialStartsAt !== null) {
                    $trialStartsAt = $startDate->format('c');
                }

                $trialCycles += $cycle['total_cycles'];
                $totalCycles += $cycle['total_cycles'];
            }

            if ($cycle['tenure_type'] === self::TENURE_TYPE_REGULAR) {
                $totalCycles += $cycle['total_cycles'];
            }
        }

        if ($trialCycles) {
            $trialLength = new DateInterval(sprintf('P%dD', $paymentCycleLength->days * $trialCycles));
            $trialEndsAt = $startDate->add($trialLength)->format('c');
        }

        if ($totalCycles) {
            $totalLength = new DateInterval(sprintf('P%dD', $paymentCycleLength->days * $totalCycles));
            $expiresAt = $startDate->add($totalLength)->format('c');
        }


        $subscription = new Subscription();

        $subscription->setTransactionId($providerResponse['id']);
        $subscription->setPlanName($providerResponse['plan_id']);
        $subscription->setEmail($providerResponse['subscriber']['email_address']);
        $subscription->setCurrency($providerResponse['shipping_amount']['currency_code']);
        $subscription->setAmount($providerResponse['shipping_amount']['value']);
        $subscription->setCustomerId($this->getCustomerIdFromCustomId($providerResponse['custom_id']));
        $subscription->setCreatedAt($providerResponse['create_time']);
        $subscription->setTrialStartAt($trialStartsAt);
        $subscription->setTrialEndAt($trialEndsAt);
        $subscription->setExpireAt($expiresAt);
        $subscription->setState($providerResponse['status']);
        $subscription->setIsActive($providerResponse['status'] === 'ACTIVE');
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