<?php

namespace Wowmaking\WebPurchases;

use Wowmaking\WebPurchases\Factories\PurchasesClientFactory;
use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;

class WebPurchases
{
    /** @var self */
    private static $service;

    /** @var PurchasesClientInterface */
    private $purchasesClient;

    /**
     * @param array $clientParams
     * @param array $subtruckParams
     * @param array $fbPixelParams
     * @return static
     * @throws \Exception
     */
    public static function service(array $clientParams, array $subtruckParams = [], array $fbPixelParams = []): self
    {
        if (!self::$service instanceof self) {
            self::$service = new self($clientParams, $subtruckParams, $fbPixelParams);
        }

        return self::$service;
    }

    /**
     * WebPurchases constructor.
     * @param array $clientParams
     * @param array $subtruckParams
     * @param array $fbPixelParams
     * @throws \Exception
     */
    protected function __construct(array $clientParams, array $subtruckParams = [], array $fbPixelParams = [])
    {
        $this->setPurchasesClient((new PurchasesClientFactory())->create($clientParams, $subtruckParams, $fbPixelParams));
    }

    /**
     * @return PurchasesClientInterface
     */
    public function getPurchasesClient(): PurchasesClientInterface
    {
        return $this->purchasesClient;
    }

    /**
     * @param PurchasesClientInterface $purchasesClient
     * @return $this
     */
    public function setPurchasesClient(PurchasesClientInterface $purchasesClient): self
    {
        $this->purchasesClient = $purchasesClient;

        return $this;
    }
}