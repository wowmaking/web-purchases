<?php

namespace Wowmaking\WebPurchases;

use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\Services\RecurlyService\RecurlyService;
use Wowmaking\WebPurchases\Services\StripeService\StripeService;
use Wowmaking\WebPurchases\Interfaces\PurchasesInterface;

class WebPurchases
{
    protected const PAYMENT_SERVICE_STRIPE = 'stripe';

    protected const PAYMENT_SERVICE_RECURLY = 'recurly';

    /** @var self */
    private static $service;

    /** @var string */
    protected $client_type;

    /** @var PurchasesInterface */
    protected $client;

    /**
     * @param string $client_type
     * @param string $secret_api_key
     * @param string $public_api_key
     * @return PurchasesInterface
     * @throws \Exception
     */
    public static function client(string $client_type, string $secret_api_key, string $public_api_key): PurchasesInterface
    {
        if (!self::$service instanceof self) {
            self::$service = new self($client_type, $secret_api_key, $public_api_key);
        }

        return self::$service->getClient();
    }

    /**
     * WebPurchases constructor.
     * @param string $client_type
     * @param string $secret_api_key
     * @param string $public_api_key
     * @throws \Exception
     */
    protected function __construct(string $client_type, string $secret_api_key, string $public_api_key)
    {
        if (!in_array($client_type, [self::PAYMENT_SERVICE_RECURLY, self::PAYMENT_SERVICE_STRIPE])) {
            throw new \Exception('invalid client type');
        }

        $config = PaymentServiceConfig::create($secret_api_key, $public_api_key);

        $this->setClientType($client_type)->loadClient($config);
    }

    /**
     * @return string
     */
    protected function getClientType(): string
    {
        return $this->client_type;
    }

    /**
     * @param string $client_type
     * @return $this
     */
    protected function setClientType(string $client_type): WebPurchases
    {
        $this->client_type = $client_type;

        return $this;
    }

    /**
     * @return PurchasesInterface
     */
    protected function getClient(): PurchasesInterface
    {
        return $this->client;
    }

    /**
     * @param PurchasesInterface $client
     * @return $this
     */
    protected function setClient(PurchasesInterface $client): WebPurchases
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param PaymentServiceConfig $config
     * @return $this
     * @throws \Exception
     */
    protected function loadClient(PaymentServiceConfig $config): WebPurchases
    {
        switch ($this->getClientType()) {
            case self::PAYMENT_SERVICE_STRIPE:
                $client = new StripeService($config);
                break;

            case self::PAYMENT_SERVICE_RECURLY:
                $client = new RecurlyService($config);
                break;

            default:
                throw new \Exception('client create error');
        }

        $this->setClient($client);

        return $this;
    }

}