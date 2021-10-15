<?php

namespace Wowmaking\WebPurchases;

use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;
use Wowmaking\WebPurchases\Models\PurchasesClientConfig;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\Recurly\Recurly;
use Wowmaking\WebPurchases\PurchasesClients\Stripe\Stripe;

class WebPurchases
{
    /** @var self */
    private static $service;

    /** @var PurchasesClientInterface */
    protected $client;

    /**
     * WebPurchases constructor.
     * @param string $clientType
     * @param string $secretKey
     * @param string $publicKey
     * @param string|null $magnusToken
     * @param string|null $idfm
     * @throws \Exception
     */
    protected function __construct(string $clientType, string $secretKey, string $publicKey, ?string $magnusToken = null, ?string $idfm = null)
    {
        if (!in_array($clientType, PurchasesClient::getPurchasesClientsTypes())) {
            throw new \Exception('invalid client type');
        }

        $config = PurchasesClientConfig::create($clientType, $secretKey, $publicKey, $magnusToken, $idfm);
        $this->loadClient($config);
    }

    /**
     * @return PurchasesClientInterface
     */
    public function getClient(): PurchasesClientInterface
    {
        return $this->client;
    }

    /**
     * @param PurchasesClientInterface $client
     * @return $this
     */
    public function setClient(PurchasesClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param string $clientType
     * @param string $secretKey
     * @param string $publicKey
     * @param string|null $magnusToken
     * @param string|null $idfm
     * @return PurchasesClientInterface
     * @throws \Exception
     */
    public static function client(string $clientType, string $secretKey, string $publicKey, ?string $magnusToken = null, ?string $idfm = null): PurchasesClientInterface
    {
        if (!self::$service instanceof self) {
            self::$service = new self($clientType, $secretKey, $publicKey, $magnusToken, $idfm);
        }

        return self::$service->getClient();
    }

    /**
     * @param PurchasesClientConfig $config
     * @return $this
     * @throws \Exception
     */
    protected function loadClient(PurchasesClientConfig $config): self
    {
        switch ($config->getClientType()) {
            case PurchasesClient::PAYMENT_SERVICE_STRIPE:
                $client = new Stripe($config);
                break;

            case PurchasesClient::PAYMENT_SERVICE_RECURLY:
                $client = new Recurly($config);
                break;

            default:
                throw new \Exception('client create error');
        }

        $this->setClient($client);

        return $this;
    }

}