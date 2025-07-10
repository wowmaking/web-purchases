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

    private function makeRequest(string $method, string $path, array $body = [])
    {
        $body['signature'] = $this->signRequest($body);

        $response = $this->httpClient->request($method, $path, [
            'headers' =>
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            'body' => json_encode($body)
        ]);

        return json_decode($response->getBody(), true);
    }

    private function signRequest(array $payload): string {
        $params = http_build_query($payload);

        $signature = hash_hmac("sha256", $params, $this->clientSecret);
        return $signature;
    }

}
