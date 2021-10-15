<?php

namespace Wowmaking\WebPurchases\PurchasesClients;

use GuzzleHttp\Client;
use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;
use Wowmaking\WebPurchases\Models\PurchasesClientConfig;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

abstract class PurchasesClient implements PurchasesClientInterface
{
    public const PAYMENT_SERVICE_STRIPE = 'stripe';
    public const PAYMENT_SERVICE_RECURLY = 'recurly';

    protected $provider;

    /** @var PurchasesClientConfig */
    protected $config;

    /**
     * @return string[]
     */
    public static function getPurchasesClientsTypes(): array
    {
        return [
            self::PAYMENT_SERVICE_RECURLY,
            self::PAYMENT_SERVICE_STRIPE
        ];
    }

    /**
     * PurchasesClient constructor.
     * @param PurchasesClientConfig $config
     * @throws \Exception
     */
    public function __construct(PurchasesClientConfig $config)
    {
        $this->setConfig($config);

        $this->loadProvider();
    }

    /**
     * @return mixed
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param $provider
     * @return $this
     */
    public function setProvider($provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return PurchasesClientConfig
     */
    public function getConfig(): PurchasesClientConfig
    {
        return $this->config;
    }

    /**
     * @param PurchasesClientConfig $config
     */
    public function setConfig(PurchasesClientConfig $config): void
    {
        $this->config = $config;
    }

    public function createSubscription(array $data): Subscription
    {
        $response = $this->subscriptionCreationProcess($data);

        $this->subscriptionToSubtruck($response);

        return $this->buildSubscriptionResource($response);
    }

    /**
     * @param $response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function subscriptionToSubtruck($response)
    {
        $response = (new Client())->request('POST', 'https://subtruck.magnus.ms/api/v2/transaction/', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => [
                'idfm' => $this->getConfig()->getIdfm(),
                'token' => $this->getConfig()->getMagnusToken(),
                'transaction' => json_encode($response),
            ]
        ]);
    }
}