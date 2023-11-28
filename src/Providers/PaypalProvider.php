<?php

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client;
use LogicException;

class PaypalProvider
{
    private const BASE_URL_SANDBOX = 'https://api-m.sandbox.paypal.com';
    private const BASE_URL_PROD = 'https://api-m.paypal.com';
    private const URL_CHECK_OREDER_STATUS = '/v2/payments/captures/%s';
    private const URL_TPL_TOKEN = '/v1/oauth2/token';
    private const URL_TPL_GET_SUBSCRIPTION = '/v1/billing/subscriptions/%s';
    private const URL_TPL_CANCEL_SUBSCRIPTION = '/v1/billing/subscriptions/%s/cancel';
    private const URL_TPL_REFUND_PAYMENT = '/v2/payments/captures/%s/refund';
    private const URL_TPL_LIST_PLANS = '/v1/billing/plans?page_size=20';
    private const URL_TPL_GET_PLAN = '/v1/billing/plans/%s';

    /**
     * @var string
     */
    private $clientId;

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

    public function __construct(string $clientId, string $clientSecret, bool $sandbox = false)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->httpClient = new Client(['base_uri' => $sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD]);
    }

    public function listPlans(array $pricesIds = []): array
    {
        $link = self::URL_TPL_LIST_PLANS;

        if ($pricesIds) {
            $link .= '&plan_ids=' . implode(',', $pricesIds);
        }

        $plans = $this->collectAllPlans($link);
        $detailedPlans = [];

        foreach ($plans as $plan) {
            $detailedPlans[] = $this->getPlan($plan['id']);
        }

        return $detailedPlans;
    }

    public function getPlan(string $planId): array
    {
        return $this->makeRequest('GET', sprintf(self::URL_TPL_GET_PLAN, $planId));
    }

    private function collectAllPlans(string $currentLink): array
    {
        $result = $this->makeRequest('GET', $currentLink);

        $plans = $result['plans'];

        if (isset($result['links'])) {
            foreach ($result['links'] as $link) {
                if ($link['rel'] === 'next') {
                    $url = str_replace('api', 'api-m', $link['href']);
                    return array_merge($plans, $this->collectAllPlans($url));
                }
            }
        }
        return $plans;
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('GET', sprintf(self::URL_TPL_GET_SUBSCRIPTION, $subscriptionId));
    }

    public function cancelSubscription(string $subscriptionId, string $reason)
    {
        return $this->makeRequest(
            'POST',
            sprintf(self::URL_TPL_CANCEL_SUBSCRIPTION, $subscriptionId),
            ['reason' => $reason]
        );
    }

    public function refund(string $orderId, float $amount, string $currency)
    {
        return $this->makeRequest(
            'POST',
            sprintf(self::URL_TPL_REFUND_PAYMENT, $orderId),
            ['amount' => [
                'value' => $amount,
                'currency_code' => $currency]
            ]
        );
    }


    public function checkOrderStatus(string $orderId)
    {
        return $this->makeRequest(
            'GET',
            sprintf(self::URL_CHECK_OREDER_STATUS, $orderId),
        );
    }

    private function makeRequest(string $method, string $path, array $body = [])
    {
        $response = $this->httpClient->request($method, $path, [
            'headers' =>
                [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->getAccessToken()
                ],
            'body' => json_encode($body)
        ]);

        return json_decode($response->getBody(), true);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $this->refreshAccessToken();

        return $this->accessToken;
    }

    private function refreshAccessToken(): void
    {
        $response = $this->httpClient->post(self::URL_TPL_TOKEN, [
            'auth' => [$this->clientId, $this->clientSecret],
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Content-Type' => 'application/json',
            ],
            'body' => 'grant_type=client_credentials',
        ]);

        $data = json_decode($response->getBody(), true);

        if (!$data['access_token']) {
            throw new LogicException('Something went wrong.');
        }

        $this->accessToken = $data['access_token'];
    }

    public function getTransactions($startDate, $endDate, $page)
    {
        return $this->makeRequest('GET', "/v1/reporting/transactions?start_date={$startDate}T00:00:00-0000&end_date={$endDate}T23:59:59-0000&page_size=500&page=$page");
    }
}
