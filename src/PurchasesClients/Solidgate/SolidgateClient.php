<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\PurchasesClients\Solidgate;

use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\SolidgateProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\WithoutCustomerSupportTrait;
use Wowmaking\WebPurchases\Resources\Entities\Price;
use Wowmaking\WebPurchases\Resources\Entities\PriceCurrency;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use yii\db\Exception;

/**
 * @property SolidgateProvider $provider
 */
class SolidgateClient extends PurchasesClient
{
    use WithoutCustomerSupportTrait;

    private const STATUS_ACTIVE = 'active';
    private const STATUS_REDEMPTION = 'redemption';

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
    )
    {
        $this->merchantId = $merchantId;

        parent::__construct($secretKey);

        $this->webHookProvider = new SolidgateProvider($webhookMerchantId, $webhookSecretKey);
    }

    public function isSupportsPrices(): bool
    {
        return true;
    }

    public function getPrices(array $pricesIds = []): array
    {
        $limit = 100;
        $offset = 0;
        $products = [];
        do {
            $partProducts = $this->retriveProducts($limit, $offset);
            $products = array_merge($products, $partProducts['data']);
            if (count($products) == $partProducts['pagination']['total_count']) {
                break;
            }
            $offset += $limit;
        } while (true);
        foreach ($products as $product) {
            $priceData = $this->retriveProductPrice($product['id']);

            $price = new Price();
            $price->setId($product['id']);
            $price->setPeriod($product['billing_period']['value'], strtoupper($product['billing_period']['unit'][0]));
            $price->setProductName($product['name']);


            foreach ($priceData['data'] as $item) {
                if ($item['default']) {
                    $priceData = $item;
                } else {
                    if ($item['status'] == 'active') {
                        $priceCurrency = new PriceCurrency();
                        $priceCurrency->setId($item['id']);
                        $priceCurrency->setAmount($item['product_price'] / 100);
                        $priceCurrency->setCountry($item['country']);
                        $priceCurrency->setCurrency($item['currency']);
                        if (isset($product['trial']) && isset($product['trial']['billing_period'])) {
                            $priceCurrency->setTrialPriceAmount($item['trial_price'] / 100);
                        }
                        $price->addCurrency($priceCurrency);
                    }
                }
            }

            $price->setAmount($priceData['product_price'] / 100);
            $price->setCurrency($priceData['currency']);


            if (isset($product['trial']) && isset($product['trial']['billing_period'])) {
                if ($product['trial']['billing_period']['unit'] == 'day') {
                    $price->setTrialPeriodDays($product['trial']['billing_period']['value']);
                } elseif($product['trial']['billing_period']['unit'] == 'week'){
                    $price->setTrialPeriodDays($product['trial']['billing_period']['value'] * 7);
                } elseif($product['trial']['billing_period']['unit'] == 'month'){
                    $price->setTrialPeriodDays($product['trial']['billing_period']['value'] * 30);
                } elseif($product['trial']['billing_period']['unit'] == 'year'){
                    $price->setTrialPeriodDays($product['trial']['billing_period']['value'] * 365);
                }
                $price->setTrialPriceAmount($priceData['trial_price'] / 100);
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

    public function cancelSubscription(string $subscriptionId, bool $force = false, string $cancelCode = "8.14"): Subscription
    {
        if (!$force) {
            $subscriptionData = $this->getSubscription($subscriptionId);
            if ($subscriptionData['subscription']['status'] == 'redemption') {
                $force = true;
            }
        }
        $result = json_decode(
            $this->provider->cancelSubscription(['subscription_id' => $subscriptionId, 'force' => $force, 'cancel_code' => $cancelCode]),
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
        $subscription->setIsActive(
            in_array(
                $providerResponse['subscription']['status'],
                [self::STATUS_ACTIVE, self::STATUS_REDEMPTION],
                true
            )
        );
        $subscription->setProvider(PurchasesClient::PAYMENT_SERVICE_SOLIDGATE);
        $subscription->setProviderResponse($providerResponse);

        if (isset($providerResponse['subscription']['cancelled_at'])) {
            $subscription->setCanceledAt($providerResponse['subscription']['cancelled_at']);
        }

        if ($providerResponse['subscription']['trial']) {
            $subscription->setTrialStartAt($providerResponse['subscription']['started_at']);
            if (isset($providerResponse['subscription']['next_charge_at'])) {
                $subscription->setTrialEndAt($providerResponse['subscription']['next_charge_at']);
            } else {
                $subscription->setTrialEndAt($providerResponse['subscription']['expired_at']);
            }
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

    public function getSubscription(string $subscriptionId)
    {
        return json_decode(
            $this->provider->subscriptionStatus(['subscription_id' => $subscriptionId]),
            true
        );
    }

    protected function getPurchaseClientType(): string
    {
        return self::PAYMENT_SERVICE_SOLIDGATE;
    }

    public function checkOrderStatus(string $orderId)
    {
        return json_decode(
            $this->provider->checkOrderStatus(['order_id' => $orderId]),
            true
        );
    }

    public function checkOrderStatusAlternativePayment(string $orderId)
    {
        return json_decode(
            $this->provider->checkOrderStatusAlternativePayment(['order_id' => $orderId]),
            true
        );
    }

    public function oneTimePayment(string $orderId, int $amount, string $currency, string $productCode, string $cardToken,
                                   string $orderDescription, string $email, string $ipAddress, ?string $successUrl, ?string $failUrl, string $deviceId, bool $force3ds = false, bool $isIdfm = true, bool $isRebill = false,
                                   string $paramMetadata = 'idfm', array $solidMetadata = [])
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'one_time_product_code' => $productCode
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'one_time_product_code' => $productCode
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['one_time_product_code'] = $productCode;
            $orderMetadata = $solidMetadata;
        }

        $data = [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'recurring_token' => $cardToken,
            'order_description' => $orderDescription,
            'order_items' => $productCode,
            'type' => 'auth',
            'settle_interval' => 144,
            'order_metadata' => $orderMetadata,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'payment_type' => '1-click',
            'platform' => 'WEB',
            'force3ds' => $force3ds
        ];

        if ($failUrl) {
            $data['fail_url'] = $failUrl;
        }

        if($isRebill){
            $data['payment_type'] = 'rebill';
        }

        if ($successUrl) {
            $data['success_url'] = $successUrl;
        }

        return json_decode(
            $this->provider->recurring($data),
            true
        );
    }

    public function oneTimePaymentAlternativePayment(string $orderId, int $amount, string $currency, string $productCode, string $token,
                                                     string $orderDescription, string $email, string $ipAddress, string $deviceId, bool $isIdfm = true,
                                                     string $paramMetadata = 'idfm', array $solidMetadata = [])
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'one_time_product_code' => $productCode
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'one_time_product_code' => $productCode
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['one_time_product_code'] = $productCode;
            $orderMetadata = $solidMetadata;
        }

        $data = [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'token' => $token,
            'order_description' => $orderDescription,
            'order_metadata' => $orderMetadata,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'platform' => 'WEB'
        ];
        return json_decode(
            $this->provider->recurringAlternativePayment($data),
            true
        );
    }


    public function reactivate(string $subscriptionId): bool
    {
        $response = json_decode(
            $this->provider->restore(['subscription_id' => $subscriptionId]),
            true);

        if (isset($response['status']) && $response['status'] == 'ok') {
            return true;
        } else {
            throw new \Exception(json_encode($response['error']['messages']), 415);
        }
    }

    public function changePlan(string $subscriptionId, string $planCode): bool
    {
        $response = json_decode(
            $this->provider->changePlan(['subscription_id' => $subscriptionId, 'new_product_id' => $planCode]),
            true);
        if (isset($response['status']) && $response['status'] == 'ok') {
            return true;
        } else {
            throw new \Exception(json_encode($response['error']['messages']), 415);
        }
    }

    public function createSubscriptionByCardToken(string $orderId, string $productCode, string $cardToken,
                                                  string $orderDescription, string $email, string $customerAccountId, string $ipAddress, string $successUrl, string $failUrl, string $idfm)
    {
        $data = [
            'order_id' => $orderId,
            'recurring_token' => $cardToken,
            'order_description' => $orderDescription,
            'order_metadata' => [
                'idfm' => $idfm,
                'product_id' => $productCode
            ],
            'product_id' => $productCode,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'success_url' => $successUrl,
            'payment_type' => 'recurring',
            'fail_url' => $failUrl,
            'platform' => 'WEB',
            'customer_account_id' => $customerAccountId,
        ];
        return json_decode(
            $this->provider->recurring($data),
            true
        );
    }

    public function retriveProducts($limit, $offset)
    {
        $data = ['pagination[limit]' => $limit, 'pagination[offset]' => $offset, 'filter[status]' => 'active'];
        return json_decode(
            $this->provider->retriveProducts($data),
            true
        );
    }

    public function retriveProductPrice($productId)
    {
        return json_decode(
            $this->provider->retriveProductPrice($productId),
            true
        );
    }

    public function applePay($productId, $orderId, $deviceId, $customerId, $customerEmail, $ipAddress, $platform, $signature, $data, $header, $version, bool $isIdfm = true, string $currency = null, string $geoCountry = null, string $paramMetadata = 'idfm', array $solidMetadata = [])
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'product_id' => $productId
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'product_id' => $productId
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['product_id'] = $productId;
            $orderMetadata = $solidMetadata;
        }

        $data = [
            'product_id' => $productId,
            'order_id' => $orderId,
            'order_description' => $deviceId,
            'customer_account_id' => $customerId,
            'customer_email' => $customerEmail,
            'ip_address' => $ipAddress,
            'platform' => $platform,
            'data' => $data,
            'signature' => $signature,
            'header' => $header,
            'version' => $version,
            'order_metadata' => $orderMetadata,
        ];

        if ($currency) {
            $data['currency'] = $currency;
        }

        if ($geoCountry) {
            $data['geo_country'] = $geoCountry;
        }

        return json_decode(
            $this->provider->applePay($data),
            true
        );
    }

    public function applePayOneTimePayment(
        int    $amount,
        string $currency,
        string $orderId,
        string $deviceId,
        string $productCode,
        string $customerEmail,
        string $ipAddress,
        string $platform,
        string $data,
               $header,
        string $signature,
        string $version,
        bool   $isIdfm = true,
        string $paramMetadata = 'idfm',
        array  $solidMetadata = []
    )
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'one_time_product_code' => $productCode
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'one_time_product_code' => $productCode
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['one_time_product_code'] = $productCode;
            $orderMetadata = $solidMetadata;
        }

        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'order_description' => $deviceId,
            'order_items' => $productCode,
            'customer_email' => $customerEmail,
            'ip_address' => $ipAddress,
            'platform' => $platform,
            'data' => $data,
            'header' => $header,
            'signature' => $signature,
            'version' => $version,
            'order_metadata' => $orderMetadata,
        ];

        return json_decode(
            $this->provider->applePay($data),
            true
        );
    }

    public function getSubscriptionReport($dateFrom, $dateTo, $cursor = null)
    {

        $data = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
        if ($cursor) {
            $data['next_page_iterator'] = $cursor;
        }
        return json_decode(
            $this->provider->subscriptionReport($data),
            true
        );
    }

    public function getCardOrderReport($dateFrom, $dateTo, $cursor = null)
    {

        $data = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
        if ($cursor) {
            $data['next_page_iterator'] = $cursor;
        }
        return json_decode(
            $this->provider->cardOrderReport($data),
            true
        );
    }

    public function customRequestToPayApi(string $method, array $params = [])
    {
        return json_decode(
            $this->provider->sendRequestToPayApi($method, $params),
            true);
    }

    public function customRequestToGateApi(string $method, array $params = [])
    {
        return json_decode(
            $this->provider->sendRequestToGateApi($method, $params),
            true);
    }

    public function initAlternativePayment(string $paymentMethod, string $orderId, string $productId,
                                           string $orderDescription, string $email, string $customerAccountId, string $ipAddress, string $deviceId, string $currency = null, string $geoCountry = null, bool $isIdfm = true,
                                           string $paramMetadata = 'idfm', array $solidMetadata = [])
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'product_id' => $productId
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'product_id' => $productId
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['product_id'] = $productId;
            $orderMetadata = $solidMetadata;
        }

        $params = [
            'payment_method' => $paymentMethod,
            'product_id' => $productId,
            'order_id' => $orderId,
            'order_description' => $orderDescription,
            'customer_account_id' => $customerAccountId,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'platform' => 'WEB',
            'order_metadata' => $orderMetadata,
        ];

        if($currency){
            $params['currency'] = $currency;
        }

        if($geoCountry){
            $params['billing_address'] = ['country'=> $geoCountry];
        }

        return json_decode(
            $this->provider->initAlternativePayment($params),
            true);
    }

    public function initAlternativeOneTimePayment(string $paymentMethod, string $orderId, string $productId, int $amount, string $currency,
                                                  string $orderDescription, string $email, string $customerAccountId, string $ipAddress, string $deviceId, bool $isIdfm = true,
                                                  string $paramMetadata = 'idfm', array $solidMetadata = [])
    {
        $orderMetadata = [
            'idfm' => $deviceId,
            'one_time_product_code' => $productId
        ];

        if (!$isIdfm) {
            $orderMetadata = [
                'idfv' => $deviceId,
                'one_time_product_code' => $productId
            ];
        }

        if ($paramMetadata === 'solid_metadata') {
            $solidMetadata['one_time_product_code'] = $productId;
            $orderMetadata = $solidMetadata;
        }

        $params = [
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'order_description' => $orderDescription,
            'customer_account_id' => $customerAccountId,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'platform' => 'WEB',
            'order_metadata' => $orderMetadata,
        ];
        return json_decode(
            $this->provider->initAlternativePayment($params),
            true);
    }
}

