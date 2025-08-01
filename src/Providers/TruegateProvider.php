<?php

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client;
use LogicException;

class TruegateProvider
{
    private const BASE_URL_SANDBOX = 'https://public-api.asapcashier-dev.com/api/v1/';
    private const BASE_URL_PROD = 'https://public-api.asapcashier.truegate.tech/api/v1/';

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var string|null
     */
    private $accessToken;

    public function __construct(string $clientSecret, bool $sandbox = false)
    {
        $this->clientSecret = $clientSecret;
        $this->httpClient = new Client(['base_uri' => $sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD]);
    }

    public function listPlans(array $params): array
    {
        return $this->makeRequest('POST', 'subscriptions/subscription-product-plans', $params);
    }

    public function startSubscription(array $params) {
        return $this->makeRequest('POST', 'subscriptions/start', $params, ['metadata']);
    }

    public function startOneTimePayment(array $params) {
        return $this->makeRequest('POST', 'pay/start', $params, ['metadata']);
    }

    public function oneTimePayment(array $params) {
        return $this->makeRequest('POST', 'one-time-payments/create', $params, ['metadata']);
    }

    public function cancelSubscription(array $params) {

        return $this->makeRequest('POST', 'subscriptions/cancel', $params, ['isHard']);
    }

    public function getTransactionDetails(array $params) {
        return $this->makeRequest('POST', 'subscriptions/transactions/details', $params);
    }

    private function makeRequest(string $method, string $path, array $body = [], array $exceptFildsForSign = [])
    {
        if($exceptFildsForSign) {
            $fieldsForSign = array_diff_key($body, array_flip($exceptFildsForSign));
        } else {
            $fieldsForSign = $body;
        }

        $body['signature'] = $this->signRequest($fieldsForSign);
        $response = $this->httpClient->request($method, $path, [
            'headers' =>
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-forwarded-for' => '109.105.255.253'
                ],
            'body' => json_encode($body)
        ]);


        return json_decode($response->getBody(), true);
    }

    private function signRequest(array $payload): string {
        $params = implode('&', array_map(function($k, $v) {
            return "$k=$v";
        }, array_keys($payload), $payload));

        $signature = hash_hmac("sha256", $params, $this->clientSecret);
        return $signature;
    }






}
