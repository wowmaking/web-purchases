<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\PurchasesClients\Solidgate;

use InvalidArgumentException;
use LogicException;
use Wowmaking\WebPurchases\Providers\SolidgateProvider;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\WithoutCustomerSupportTrait;
use Wowmaking\WebPurchases\Resources\Entities\Price;
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
            foreach ($priceData['data'] as $item) {
                if ($item['default']) {
                    $priceData = $item;
                }
            }

            $price = new Price();
            $price->setId($product['id']);
            $price->setAmount($priceData['product_price'] / 100);
            $price->setCurrency($priceData['currency']);
            $price->setPeriod($product['billing_period']['value'], strtoupper($product['billing_period']['unit'][0]));
            $price->setProductName($product['name']);

            if (isset($product['trial']) && isset($product['trial']['billing_period'])) {
                if ($product['trial']['billing_period']['unit'] == 'day') {
                    $price->setTrialPeriodDays($product['trial']['billing_period']['value']);
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
                                   string $orderDescription, string $email, string $ipAddress, ?string $successUrl, ?string $failUrl, string $idfm, bool $force3ds = false)
    {
        $data = [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'recurring_token' => $cardToken,
            'order_description' => $orderDescription,
            'order_items' => $productCode,
            'type' => 'auth',
            'settle_interval' => 144,
            'order_metadata' => [
                'idfm' => $idfm,
                'one_time_product_code' => $productCode
            ],
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'payment_type' => '1-click',
            'platform' => 'WEB',
            'force3ds' => $force3ds
        ];

        if ($failUrl) {
            $data['fail_url'] = $failUrl;
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
                                                     string $orderDescription, string $email, string $ipAddress, string $idfm)
    {
        $data = [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'token' => $token,
            'order_description' => $orderDescription,
            'order_metadata' => [
                'idfm' => $idfm,
                'one_time_product_code' => $productCode
            ],
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

    public function applePay($productId, $orderId, $idfm, $customerId, $customerEmail, $ipAddress, $platform, $signature, $data, $header, $version)
    {
        $data = [
            'product_id' => $productId,
            'order_id' => $orderId,
            'order_description' => $idfm,
            'customer_account_id' => $customerId,
            'customer_email' => $customerEmail,
            'ip_address' => $ipAddress,
            'platform' => $platform,
            'data' => $data,
            'signature' => $signature,
            'header' => $header,
            'version' => $version,
            'order_metadata' => [
                'idfm' => $idfm,
                'product_id' => $productId
            ],
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
                                           string $orderDescription, string $email, string $customerAccountId, string $ipAddress, string $idfm)
    {
        $params = [
            'payment_method' => $paymentMethod,
            'product_id' => $productId,
            'order_id' => $orderId,
            'order_description' => $orderDescription,
            'customer_account_id' => $customerAccountId,
            'customer_email' => $email,
            'ip_address' => $ipAddress,
            'platform' => 'WEB',
            'order_metadata' => [
                'idfm' => $idfm,
                'product_id' => $productId
            ],
        ];
        return json_decode(
            $this->provider->initAlternativePayment($params),
            true);
    }

    public function initAlternativeOneTimePayment(string $paymentMethod, string $orderId, string $productId, int $amount, string $currency,
                                                  string $orderDescription, string $email, string $customerAccountId, string $ipAddress, string $idfm)
    {
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
            'order_metadata' => [
                'idfm' => $idfm,
                'one_time_product_code' => $productId
            ],
        ];
        return json_decode(
            $this->provider->initAlternativePayment($params),
            true);
    }
}

