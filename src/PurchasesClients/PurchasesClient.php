<?php

namespace Wowmaking\WebPurchases\PurchasesClients;

use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;
use Wowmaking\WebPurchases\Resources\Entities\Customer;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use Wowmaking\WebPurchases\Services\FbPixel\FbPixelService;
use Wowmaking\WebPurchases\Services\Subtruck\SubtruckService;

abstract class PurchasesClient implements PurchasesClientInterface
{
    public const PAYMENT_SERVICE_STRIPE = 'stripe';
    public const PAYMENT_SERVICE_RECURLY = 'recurly';

    protected $provider;

    /** @var string */
    protected $secretKey;

    /** @var SubtruckService|null */
    private $subtruck;

    /** @var FbPixelService|null */
    private $fbPixel;

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
     * @param $secretKey
     */
    public function __construct($secretKey)
    {
        $this->setSecretKey($secretKey);

        $this->loadProvider();
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @param string $secretKey
     */
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
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
     * @return SubtruckService|null
     */
    public function getSubtruck():? SubtruckService
    {
        return $this->subtruck;
    }

    /**
     * @param SubtruckService|null $subtruck
     */
    public function setSubtruck(?SubtruckService $subtruck): void
    {
        $this->subtruck = $subtruck;
    }

    /**
     * @return FbPixelService|null
     */
    public function getFbPixel():? FbPixelService
    {
        return $this->fbPixel;
    }

    /**
     * @param FbPixelService|null $fbPixel
     */
    public function setFbPixel(?FbPixelService $fbPixel): void
    {
        $this->fbPixel = $fbPixel;
    }

    abstract public function getPrices(array $pricesIds = []): array;

    abstract public function createCustomer(array $data): Customer;

    abstract public function getCustomers(array $params): array;

    abstract public function getCustomer(string $customerId): Customer;

    abstract public function updateCustomer(string $customerId, array $data): Customer;

    public function createSubscription(array $data): Subscription
    {
        $response = $this->subscriptionCreationProcess($data);

        $subscription = $this->buildSubscriptionResource($response);

        if ($this->getSubtruck()) {
            $this->getSubtruck()->track($subscription);
        }

        if ($this->getFbPixel()) {
            $this->getFbPixel()->track($subscription);
        }

        return $subscription;
    }

    abstract public function subscriptionCreationProcess(array $data);

    abstract public function getSubscriptions(string $customerId): array;

    abstract public function cancelSubscription(string $subscriptionId): Subscription;

    abstract public function buildCustomerResource($providerResponse): Customer;

    abstract public function buildSubscriptionResource($providerResponse): Subscription;

    abstract public function loadProvider();
}