<?php

namespace Wowmaking\WebPurchases\PurchasesClients;

use LogicException;
use Wowmaking\WebPurchases\Dto\TrackDataDto;
use Wowmaking\WebPurchases\Factories\TrackParametersProviderFactory;
use Wowmaking\WebPurchases\Interfaces\PurchasesClientInterface;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use Wowmaking\WebPurchases\Services\Subtruck\SubtruckService;

abstract class PurchasesClient implements PurchasesClientInterface
{
    public const PAYMENT_SERVICE_STRIPE = 'stripe';
    public const PAYMENT_SERVICE_RECURLY = 'recurly';
    public const PAYMENT_SERVICE_PAYPAL = 'paypal';
    public const PAYMENT_SERVICE_SOLIDGATE = 'solidgate';
    public const PAYMENT_SERVICE_PADDLE = 'paddle';
    public const PAYMENT_SERVICE_TRUEGATE = 'truegate';

    protected $provider;

    /** @var string */
    protected $secretKey;

    /**
     * @var TrackParametersProviderFactory
     */
    protected $trackParametersProviderFactory;

    /** @var SubtruckService|null */
    private $subtruck;

    abstract protected function getPurchaseClientType(): string;

    /**
     * @return string[]
     */
    public static function getPurchasesClientsTypes(): array
    {
        return [
            self::PAYMENT_SERVICE_RECURLY,
            self::PAYMENT_SERVICE_STRIPE,
            self::PAYMENT_SERVICE_PAYPAL,
            self::PAYMENT_SERVICE_SOLIDGATE,
            self::PAYMENT_SERVICE_PADDLE,
            self::PAYMENT_SERVICE_TRUEGATE,

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

        $this->trackParametersProviderFactory = new TrackParametersProviderFactory();
    }

    public function isSupportsPrices(): bool
    {
        return true;
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
    public function getSubtruck(): ?SubtruckService
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

    public function createSubscription(array $data, TrackDataDto $trackDataDto = null): Subscription
    {
        $response = $this->subscriptionCreationProcess($data);

        $subscription = $this->buildSubscriptionResource($response);

        $trackParams = $trackDataDto
            ? $this->trackParametersProviderFactory->createBySystem($this->getPurchaseClientType())->provide($trackDataDto)
            : [];

        if ($credentialsId = $this->getCredentialsId()) {
            $trackParams['credentials_id'] = $credentialsId;
        }

        $tracks = [];

        if ($subscription->getProvider() !== 'solidgate') {
            if ($this->getSubtruck()) {
                $tracks['subtruck'] = $this->getSubtruck()->track($subscription, $trackParams);
            }
        }

        $subscription->setTracks($tracks);

        return $subscription;
    }

    public function getPaymentFormData(array $attributes): array
    {
        return [];
    }

    protected function getCredentialsId(): ?string
    {
        return null;
    }

    /**
     * @throws LogicException
     */
    protected function throwNoRealization(string $methodName): void
    {
        throw new LogicException(sprintf('"%s" method is not realized yet.', $methodName));
    }

    abstract function getSubscription(string $subscriptionId);


    abstract function reactivate(string $subscriptionId): bool;


    abstract function changePlan(string $subscriptionId, string $planCode): bool;

}