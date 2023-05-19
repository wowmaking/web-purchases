<?php

namespace Wowmaking\WebPurchases\PurchasesClients\Paddle;

use Carbon\Carbon;
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
use Wowmaking\WebPurchases\Services\Subtruck\SubtruckService;

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

    private $publicKey;

    public function __construct(string $vendorId, string $vendorAuthCode, string $publicKey, bool $isSandbox = false)
    {
        $this->vendorId = $vendorId;
        $this->vendorAuthCode = $vendorAuthCode;
        $this->publicKey = $publicKey;
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
            switch ($currency) {
                case 'AUD':
                    $trialPriceAmount = 1.49;
                    break;
                case 'CAD':
                    $trialPriceAmount = 1.39;
                    break;
                default:
                    $trialPriceAmount = 1;
                    break;
            }
            $price->setTrialPriceAmount($trialPriceAmount);

            $prices[] = $price;
        }
        return $prices;
    }

    public function generateCustomPayLink($planId, $amount,  $currency,  $trialPriceAmount, $successUrl = null): string {
        $customPayLink = $this->getProvider()->generatePayLink($planId, $amount,  $currency,  $trialPriceAmount, $successUrl);
        return $customPayLink;
    }

    public function reschedulingSubscription($subscriptionId, $trialPeriodDays, $trialPrice) {
        $payments = $this->getProvider()->getPayments($subscriptionId);
        $paidPayment = null;
        $nextPayment = null;
        foreach($payments as $payment) {
            if(($payment['is_paid'] == 1) && $payment['amount'] == $trialPrice){
                $paidPayment = $payment;
            }
            if(($payment['is_paid'] == 0)) {
                $nextPayment = $payment;
            }
        }
        if($paidPayment && $nextPayment){
            if($nextPayment['payout_date'] > $paidPayment['payout_date']){
                $nextPayDate = Carbon::parse($paidPayment['payout_date'])->addDays($trialPeriodDays)->format("Y-m-d");
                $this->getProvider()->reschedulingPayments($nextPayment['id'], $nextPayDate);
            }
        }
    }


    public function subscriptionCreationProcess(array $data)
    {
        $checkoutId = $data['checkout_id'] ?? null;

        if (!$checkoutId) {
            throw new InvalidArgumentException('Not all required parameters were passed.');
        }
        $attempt = 0;
        do {
            $paddleOrderDetails = $this->getOrder($checkoutId);
            if(isset($paddleOrderDetails['state']) && $paddleOrderDetails['state'] != 'processing') {
                break;
            }
            sleep(1);
            ++$attempt;
            if($attempt > 10) {
                throw new \Exception("Subscription in processing state is a lot of time");
            }
        } while(true);

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

    public function  verifyWebhook(array $post): bool{
        $public_key = openssl_get_publickey($this->publicKey);

        $signature = base64_decode($post['p_signature']);

        // Get the fields sent in the request, and remove the p_signature parameter
        $fields = $post;
        unset($fields['p_signature']);

        // ksort() and serialize the fields
        ksort($fields);
        foreach($fields as $k => $v) {
            if(!in_array(gettype($v), array('object', 'array'))) {
                $fields[$k] = "$v";
            }
        }
        $data = serialize($fields);

        // Verify the signature
        $verification = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

        if($verification == 1) {
            return true;
        } else {
            return false;
        }
    }

    protected function getPurchaseClientType(): string
    {
        return self::PAYMENT_SERVICE_PADDLE;
    }
    
    public function getSubtruck(): ?SubtruckService
    {
        return null;
    }

    public function getSubscription(string $subscriptionId)
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function reactivate(string $subscriptionId): bool
    {
        $this->throwNoRealization(__METHOD__);
    }

    public function changePlan(string $subscriptionId, string $planCode): bool
    {
        $this->throwNoRealization(__METHOD__);
    }

}
