<?php

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client;
use LogicException;

class PaypalProvider
{
    private const BASE_URL_SANDBOX = 'https://api-m.sandbox.paypal.com/v1';
    private const BASE_URL_PROD = 'https://api-m.paypal.com/v1';

    private const URL_TPL_GET_SUBSCRIPTION = '/billing/subscriptions/%s';
    private const URL_TPL_CANCEL_SUBSCRIPTION = '/billing/subscriptions/%s/cancel';
    private const URL_TPL_LIST_PLANS = '/billing/plans?page_size=20';

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

        return $this->collectAllPlans($link);
    }

    private function collectAllPlans(string $link): array
    {
        $result = $this->makeRequest('GET', $link);

        $plans = $result['plans'];

        if (isset($result['links'])) {
            foreach ($result['links'] as $link) {
                if ($link['rel'] === 'next') {
                    return array_merge($plans, $this->collectAllPlans($link['href']));
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
            'body' => $body
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
        $basicAuth = base64_encode($this->clientId . ':' . $this->clientSecret);
        $response = $this->httpClient->post('/oauth2/token', [
            'headers' =>
                [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $basicAuth
                ],
            'body' => 'grant_type=client_credentials',
        ]);

        $data = json_decode($response->getBody(), true);

        if (!$data['access_token']) {
            throw new LogicException('Something went wrong.');
        }

        $this->accessToken = $data['access_token'];
    }
}