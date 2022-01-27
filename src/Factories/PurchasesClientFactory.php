<?php

namespace Wowmaking\WebPurchases\Factories;

use InvalidArgumentException;
use Wowmaking\WebPurchases\PurchasesClients\PayPal\PayPalClient;
use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\PurchasesClients\Recurly\RecurlyClient;
use Wowmaking\WebPurchases\PurchasesClients\Stripe\StripeClient;
use Wowmaking\WebPurchases\Services\FbPixel\FbPixelService;
use Wowmaking\WebPurchases\Services\Subtruck\SubtruckService;

class PurchasesClientFactory
{
    public function create(array $clientParams, array $subtruckParams = [], array $fbPixelParams = [])
    {
        $purchasesClient = $this->resolvePurchasesClient($clientParams);

        $purchasesClient->setSubtruck($this->resolveSubtruck($subtruckParams));
        $purchasesClient->setFbPixel($this->resolveFbPixel($fbPixelParams));

        return $purchasesClient;
    }


    private function resolvePurchasesClient(array $config)
    {
        if (!in_array(($config['client_type'] ?? null), PurchasesClient::getPurchasesClientsTypes())) {
            throw new InvalidArgumentException('Invalid purchases client type.');
        }

        if (!isset($config['secret_key'])) {
            throw new InvalidArgumentException('Invalid purchases client secret.');
        }

        switch ($config['client_type']) {
            case PurchasesClient::PAYMENT_SERVICE_STRIPE:
                $client = new StripeClient($config['secret_key']);
                break;

            case PurchasesClient::PAYMENT_SERVICE_RECURLY:
                $client = new RecurlyClient($config['secret_key']);
                break;

            case PurchasesClient::PAYMENT_SERVICE_PAYPAL:
                if (!isset($config['client_id'], $config['sandbox'])) {
                    throw new InvalidArgumentException('Required parameters for paypal clients was not provided.');
                }

                $client = new PayPalClient($config['client_id'], $config['secret_key'], $config['sandbox']);
                break;

            default:
                throw new InvalidArgumentException('Purchases client initialization error.');
        }

        return $client;
    }

    /**
     * @param  array  $config
     * @return SubtruckService|null
     */
    private function resolveSubtruck(array $config): ?SubtruckService
    {
        if (!isset($config['token'], $config['idfm'])) {
            return null;
        }

        return SubtruckService::service($config['token'], $config['idfm']);
    }

    /**
     * @param  array  $config
     * @return FbPixelService|null
     */
    private function resolveFbPixel(array $config): ?FbPixelService
    {
        if (
            !isset(
                $config['token'],
                $config['pixel_id'],
                $config['domain'],
                $config['ip'],
                $config['user_agent'],
                $config['fbc'],
                $config['fbp']
            )
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