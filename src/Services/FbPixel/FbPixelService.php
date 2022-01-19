<?php

namespace Wowmaking\WebPurchases\Services\FbPixel;

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\ActionSource;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;
use \FacebookAds\Object\ServerSide\EventResponse;

class FbPixelService
{
    /** @var self */
    private static $service;

    /** @var string */
    private $accessToken;

    /** @var int */
    private $pixelId;

    /** @var string */
    private $domain;

    /** @var string */
    private $ip;

    /** @var string */
    private $userAgent;

    /** @var string */
    private $fbc;

    /** @var string */
    private $fbp;

    /**
     * @param string $accessToken
     * @param int $pixelId
     * @param string $domain
     * @param string $ip
     * @param string $userAgent
     * @param string $fbc
     * @param string $fbp
     * @return FbPixelService
     */
    public static function service(
        string $accessToken,
        int $pixelId,
        string $domain,
        string $ip,
        string $userAgent,
        string $fbc,
        string $fbp
    ): FbPixelService
    {
        if (!self::$service instanceof self) {
            self::$service = new self($accessToken, $pixelId, $domain, $ip, $userAgent, $fbc, $fbp);
        }

        return self::$service;
    }

    /**
     * FbPixelService constructor.
     * @param string $accessToken
     * @param int $pixelId
     * @param string $domain
     * @param string $ip
     * @param string $userAgent
     * @param string $fbc
     * @param string $fbp
     */
    public function __construct(
        string $accessToken,
        int $pixelId,
        string $domain,
        string $ip,
        string $userAgent,
        string $fbc,
        string $fbp
    )
    {
        $this->setAccessToken($accessToken);
        $this->setPixelId($pixelId);
        $this->setDomain($domain);
        $this->setIp($ip);
        $this->setUserAgent($userAgent);
        $this->setFbc($fbc);
        $this->setFbp($fbp);
    }

    /**
     * @return int
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return int
     */
    public function getPixelId(): int
    {
        return $this->pixelId;
    }

    /**
     * @param int $pixelId
     */
    public function setPixelId(int $pixelId): void
    {
        $this->pixelId = $pixelId;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     */
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     */
    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return string
     */
    public function getFbc(): string
    {
        return $this->fbc;
    }

    /**
     * @param string $fbc
     */
    public function setFbc(string $fbc): void
    {
        $this->fbc = $fbc;
    }

    /**
     * @return string
     */
    public function getFbp(): string
    {
        return $this->fbp;
    }

    /**
     * @param string $fbp
     */
    public function setFbp(string $fbp): void
    {
        $this->fbp = $fbp;
    }

    /**
     * @param Subscription $subscription
     * @return bool
     */
    public function checkSubscription(Subscription $subscription): bool
    {
        if (
            !isset($subscription->transaction_id) ||
            !isset($subscription->currency) ||
            !isset($subscription->amount) ||
            !isset($subscription->email)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param Subscription $subscriptions
     * @return EventResponse|null
     */
    public function track(Subscription $subscriptions):? EventResponse
    {
        if (!$this->checkSubscription($subscriptions)) {
            return null;
        }

        $event = $this->createEvent(
            $this->createUserData($subscriptions),
            $this->createCustomData($subscriptions)
        );

        $request = new EventRequest($this->getPixelId(), [
            'events' => [
                $event
            ]
        ]);

        Api::init(null, null, $this->getAccessToken());

        return $request->execute();
    }

    /**
     * @param Subscription $subscriptions
     * @return UserData
     */
    private function createUserData(Subscription $subscriptions): UserData
    {
        return new UserData([
            'client_ip_address' => $this->getIp(),
            'client_user_agent' => $this->getUserAgent(),
            'fbc' => $this->getFbc(),
            'fbp' => $this->getFbp(),
            'email' => $subscriptions->getEmail(),
            'subscription_id' => $subscriptions->getTransactionId(),
        ]);
    }

    /**
     * @param Subscription $subscriptions
     * @return CustomData
     */
    private function createCustomData(Subscription $subscriptions): CustomData
    {
        return new CustomData([
            'value' => $subscriptions->getAmount(),
            'currency' => $subscriptions->getCurrency(),
            'order_id' => $subscriptions->getTransactionId(),
            'event_id' => $subscriptions->getTransactionId(),
        ]);
    }

    /**
     * @param UserData $userData
     * @param CustomData $customData
     * @return Event
     */
    private function createEvent(UserData $userData, CustomData $customData): Event
    {
        return (new Event([
            'event_name' => 'custom_purchase',
            'event_time' => time(),
            'event_source_url' => $this->getDomain(),
            'user_data' => $userData,
            'custom_data' => $customData,
            'action_source' => ActionSource::WEBSITE,
        ]));
    }
}