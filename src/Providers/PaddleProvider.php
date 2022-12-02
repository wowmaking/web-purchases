<?php

namespace Wowmaking\WebPurchases\Providers;

use GuzzleHttp\Client;
use LogicException;
use yii\httpclient\Exception;

class PaddleProvider
{
    private const VENDOR_URL = 'https://vendors.paddle.com/api/2.0/';
    private const CHECKOUT_URL = 'https://checkout.paddle.com/api/2.0/';

    private const URL_GET_SUBSCRIPTION_PLANS = 'subscription/plans';

    private const URL_GENERATE_PAY_LINK = 'product/generate_pay_link';

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

    public function getPlan(string $planId): array
    {
        return $this->makeRequest('GET', sprintf(self::URL_TPL_GET_PLAN, $planId));
    }


    private function makeRequest(string $method, string $baseUrl, string $endpointUrl, array $body = [])
    {
        if($this->isSandbox) {
            $baseUrl = str_replace("https://", 'https://sandbox-', $baseUrl);
        }

        if($method == 'POST') {
            $body = array_merge(['vendor_id' => $this->vendorId, 'vendor_auth_code' => $this->vendorAuthCode], $body);

            $response = $this->httpClient->request($method, $baseUrl . $endpointUrl, [
                'headers' => [
                    'Accept'     => 'application/json',
                    'Content-type'     => 'application/json',
                ],
                'body' => json_encode($body)
            ]);
        } else {
            throw new \Exception($method." method not supported");
        }
        $response = json_decode($response->getBody(), true);
        if($response['success']){
            return $response['response'];
        } else {
            throw new Exception($response['error']['message']);
        }
    }


}