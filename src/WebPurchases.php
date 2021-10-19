<?php

namespace Wowmaking\WebPurchases;

use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\Recurly\RecurlyClient;
use Wowmaking\WebPurchases\PurchasesClients\Stripe\StripeClient;
use Wowmaking\WebPurchases\Services\FbPixel\FbPixelService;
use Wowmaking\WebPurchases\Services\Subtruck\SubtruckService;

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
    public static function service(array $clientParams, array $subtruckParams, array $fbPixelParams): self
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
        $purchasesClient = $this->resolvePurchasesClient($clientParams);

        $purchasesClient->setSubtruck($this->resolveSubtruck($subtruckParams));
        $purchasesClient->setFbPixel($this->resolveFbPixel($fbPixelParams));

        $this->setPurchasesClient($purchasesClient);
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

    /**
     * @param array $config
     * @return RecurlyClient|StripeClient
     * @throws \Exception
     */
    private function resolvePurchasesClient(array $config)
    {
        if (!in_array(($config['client_type'] ?? null), PurchasesClient::getPurchasesClientsTypes())) {
            throw new \Exception('invalid purchases client type');
        }

        if (!isset($config['secret_key'])) {
            throw new \Exception('invalid purchases client type');
        }

        switch ($config['client_type']) {
            case PurchasesClient::PAYMENT_SERVICE_STRIPE:
                $client = new StripeClient($config['secret_key']);
                break;

            case PurchasesClient::PAYMENT_SERVICE_RECURLY:
                $client = new RecurlyClient($config['secret_key']);
                break;

            default:
                throw new \Exception('payment client initialization error');
        }

        return $client;
    }

    /**
     * @param array $config
     * @return SubtruckService|null
     */
    private function resolveSubtruck(array $config):? SubtruckService
    {
        if (!isset($config['token']) || !isset($config['idfm'])) {
            return null;
        }

        return SubtruckService::service($config['token'], $config['idfm']);
    }

    private function resolveFbPixel(array $config)
    {
        if (
            !isset($config['token']) ||
            !isset($config['pixel_id']) ||
            !isset($config['domain']) ||
            !isset($config['ip']) ||
            !isset($config['user_agent']) ||
            !isset($config['fbc']) ||
            !isset($config['fbp'])
        ) {
            return null;
        }

        return FbPixelService::service(
            $config['token'],
            $config['pixel_id'],
            $config['domain'],
            $config['ip'],
            $config['user_agent'],
            $config['fbc'],
            $config['fbp']
        );
    }

}