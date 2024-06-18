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
     * @return static
     * @throws \Exception
     */
    public static function service(array $clientParams, array $subtruckParams = []): self
    {
        if (!self::$service instanceof self) {
            self::$service = new self($clientParams, $subtruckParams);
        }

        return self::$service;
    }

    /**
     * WebPurchases constructor.
     * @param array $clientParams
     * @param array $subtruckParams
     * @throws \Exception
     */
    protected function __construct(array $clientParams, array $subtruckParams = [])
    {
        $this->setPurchasesClient((new PurchasesClientFactory())->create($clientParams, $subtruckParams));
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