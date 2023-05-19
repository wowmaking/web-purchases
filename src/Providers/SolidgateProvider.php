<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Throwable;

class SolidgateProvider
{
    protected const BASE_SUBSCRIPTION_API_URI = 'https://subscriptions.solidgate.com/api/v1/';

    protected const PAY_API_URI = 'https://pay.solidgate.com/api/v1/';

    /**
     * @var HttpClient
     */
    protected $subscriptionsApiClient;

    /**
     * @var string
     */
    protected $merchantId;

    /**
     * @var string
     */
    protected $privateKey;

    /**
     * @var Throwable|null
     */
    protected $exception;

    public function __construct(
        string $merchantId,
        string $privateKey,
        string $baseSubscriptionApiUri = self::BASE_SUBSCRIPTION_API_URI,
        string $basePayApiUrl = self::PAY_API_URI
    ) {
        $this->merchantId = $merchantId;
        $this->privateKey = $privateKey;

        $this->subscriptionsApiClient = new HttpClient(
            [
                'base_uri' => $baseSubscriptionApiUri,
                'verify' => true,
            ]
        );

        $this->payApiClient = new HttpClient(
            [
                'base_uri' => $basePayApiUrl,
                'verify' => true,
            ]
        );
    }

    public function subscriptionStatus(array $attributes): string
    {
        return $this->sendRequest('subscription/status', $attributes);
    }

    public function cancelSubscription(array $attributes): string
    {
        return $this->sendRequest('subscription/cancel', $attributes);
    }

    public function formMerchantData(array $attributes): array
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return [
            'paymentIntent' => $encryptedFormData,
            'merchant' => $this->merchantId,
            'signature' => $signature,
        ];
    }

    public function generateSignature(string $data): string
    {
        return base64_encode(
            hash_hmac(
                'sha512',
                $this->merchantId . $data . $this->merchantId,
                $this->privateKey
            )
        );
    }

    protected function sendRequest(string $method, array $attributes): string
    {
        $request = $this->makeRequest($method, $attributes);

        try {
            $response = $this->subscriptionsApiClient->send($request);
            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    protected function sendRequestToPayApi(string $method, array $attributes): string
    {
        $request = $this->makeRequest($method, $attributes);

        try {
            $response = $this->payApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    protected function base64UrlEncode(string $data): string
    {
        return strtr(base64_encode($data), '+/', '-_');
    }

    protected function generateEncryptedFormData(array $attributes): string
    {
        $attributes = json_encode($attributes);
        $secretKey = substr($this->privateKey, 0, 32);

        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLen);

        $encrypt = openssl_encrypt($attributes, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $iv);

        return $this->base64UrlEncode($iv . $encrypt);
    }

    protected function makeRequest(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Merchant' => $this->merchantId,
            'Signature' => $this->generateSignature($body),
        ];
        return new Request('POST', $path, $headers, $body);
    }

    public function checkOrderStatus($attributes) {
        return $this->sendRequestToPayApi('status', $attributes);
    }

    public function recurring($attributes){
        return $this->sendRequestToPayApi('recurring', $attributes);
    }

    public function refund($attributes) {
        return $this->sendRequestToPayApi('refund', $attributes);
    }

    public function restore($attributes){
        return $this->sendRequest('subscription/restore', $attributes);
    }

    public function changePlan($attributes){
        return $this->sendRequest('subscription/switch-subscription-product', $attributes);
    }

}

