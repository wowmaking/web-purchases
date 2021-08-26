<?php

namespace Wowmaking\WebPurchases;

use Wowmaking\WebPurchases\Interfaces\PurchaseService;
use Wowmaking\WebPurchases\Models\PaymentServiceConfig;
use Wowmaking\WebPurchases\PurchaseServices\Recurly\Recurly;
use Wowmaking\WebPurchases\PurchaseServices\Stripe\Stripe;;

class WebPurchases
{
    public const PAYMENT_SERVICE_STRIPE = 'stripe';

    public const PAYMENT_SERVICE_RECURLY = 'recurly';

    /** @var self */
    private static $service;

    /** @var string */
    protected $client_type;

    /** @var PurchaseService */
    protected $client;

    /**
     * @param string $client_type
     * @param string $secret_api_key
     * @param string $public_api_key
     * @return PurchaseService
     * @throws \Exception
     */
    public static function client(string $client_type, string $secret_api_key, string $public_api_key): PurchaseService
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
    protected function setClientType(string $client_type): self
    {
        $this->client_type = $client_type;

        return $this;
    }

    /**
     * @return PurchaseService
     */
    protected function getClient(): PurchaseService
    {
        return $this->client;
    }

    /**
     * @param PurchaseService $client
     * @return $this
     */
    protected function setClient(PurchaseService $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param PaymentServiceConfig $config
     * @return $this
     * @throws \Exception
     */
    protected function loadClient(PaymentServiceConfig $config): self
    {
        switch ($this->getClientType()) {
            case self::PAYMENT_SERVICE_STRIPE:
                $client = new Stripe($config);
                break;

            case self::PAYMENT_SERVICE_RECURLY:
                $client = new Recurly($config);
                break;

            default:
                throw new \Exception('client create error');
        }

        $this->setClient($client);

        return $this;
    }

}