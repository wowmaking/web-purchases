<?php

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client;
use LogicException;
use yii\httpclient\Exception;

class PaddleProvider
{
    private const VENDOR_URL = 'https://vendors.paddle.com/api/2.0/';
    private const CHECKOUT_URL = 'https://checkout.paddle.com/api/1.0/';

    private const URL_GET_SUBSCRIPTION_PLANS = 'subscription/plans';
    private const URL_GENERATE_PAY_LINK = 'product/generate_pay_link';
    private const URL_GET_ORDER_DETAILS = 'order';
    private const URL_CANCEL_SUBSCRIPTION = 'subscription/users_cancel';
    private const URL_GET_PAYMENTS = 'subscription/payments';
    private const URL_RESCHEDULING_PAYMENT = 'subscription/payments_reschedule';


    /**
     * @var int
     */
    private $vendorId;

    /**
     * @var string
     */
    private $vendorAuthCode;

    /**
     * @var bool
     */
    private $isSandbox;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var string|null
     */

    public function __construct(int $vendorId, string $vendorAuthCode, bool $isSandbox = false)
    {
        $this->vendorId = $vendorId;
        $this->vendorAuthCode = $vendorAuthCode;
        $this->isSandbox = $isSandbox;
        $this->httpClient = new Client();
    }

    public function listPlans(): array
    {
        $endpointUrl = self::URL_GET_SUBSCRIPTION_PLANS;
        $baseUrl = self::VENDOR_URL;

        $subscriptionPlans = $this->makeRequest("POST", $baseUrl, $endpointUrl);

        return $subscriptionPlans;
    }

    public function generatePayLink($planId, $amount,  $currency,  $trialPriceAmount): string {
        $endpointUrl = self::URL_GENERATE_PAY_LINK;
        $baseUrl = self::VENDOR_URL;
        $payload = ['product_id'=> $planId, 'prices'=> ["$currency:$trialPriceAmount"], 'recurring_prices'=>["$currency:$amount"]];
        $payLink = $this->makeRequest("POST", $baseUrl, $endpointUrl, $payload);
        return $payLink['url'];
    }


    public function getOrder(string $checkoutId): array
    {
        $endpointUrl = self::URL_GET_ORDER_DETAILS;
        $baseUrl = self::CHECKOUT_URL;

        return $this->makeRequest("GET", $baseUrl, $endpointUrl, ['checkout_id'=>$checkoutId]);
    }

    public function cancelSubscription($subscriptionId) {
        $endpointUrl = self::URL_CANCEL_SUBSCRIPTION;
        $baseUrl = self::VENDOR_URL;
        $payload = ['subscription_id'=>$subscriptionId];
        $this->makeRequest("POST", $baseUrl, $endpointUrl, $payload);
    }

    public function getPayments($subscriptionId){
        $endpointUrl = self::URL_GET_PAYMENTS;
        $baseUrl = self::VENDOR_URL;
        $payload = ['subscription_id'=>$subscriptionId];
        $payments = $this->makeRequest("POST", $baseUrl, $endpointUrl, $payload);
        return $payments;
    }

    public function reschedulingPayments($paymentId, $date){
        $endpointUrl = self::URL_RESCHEDULING_PAYMENT;
        $baseUrl = self::VENDOR_URL;
        $payload = ['payment_id'=>$paymentId, 'date'=> $date];
        $payments = $this->makeRequest("POST", $baseUrl, $endpointUrl, $payload);
    }


    private function makeRequest(string $method, string $baseUrl, string $endpointUrl, array $params = [])
    {
        if($this->isSandbox) {
            $baseUrl = str_replace("https://", 'https://sandbox-', $baseUrl);
        }

        if($method == 'POST') {
            $body = array_merge(['vendor_id' => $this->vendorId, 'vendor_auth_code' => $this->vendorAuthCode], $params);
            $response = $this->httpClient->request($method, $baseUrl . $endpointUrl, [
                'headers' => [
                    'Accept'     => 'application/json',
                    'Content-type'     => 'application/json',
                ],
                'body' => json_encode($body)
            ]);
            $response = json_decode($response->getBody(), true);

            if($response['success']){
                return $response['response'];
            } else {
                throw new Exception($response['error']['message']);
            }

        } elseif($method == 'GET') {
            $response = $this->httpClient->request($method, $baseUrl . $endpointUrl, [
                'headers' => [
                    'Accept'     => 'application/json',
                    'Content-type'     => 'application/json',
                ],
                'query' => $params,
            ]);
            $response = json_decode($response->getBody(), true);
            return $response;
        } else {
            throw new \Exception($method." method not supported");
        }

    }


}