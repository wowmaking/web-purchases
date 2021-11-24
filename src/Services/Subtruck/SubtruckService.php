<?php

namespace Wowmaking\WebPurchases\Services\Subtruck;

use GuzzleHttp\Client;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class SubtruckService
{
    /** @var self */
    private static $service;

    /** @var string */
    private $token;

    /** @var string */
    private $idfm;

    /**
     * @param string $token
     * @param string $idfm
     * @return static
     */
    public static function service(string $token, string $idfm): self
    {
        if (!self::$service instanceof self) {
            self::$service = new self($token, $idfm);
        }

        return self::$service;
    }

    /**
     * SubtruckService constructor.
     * @param string $token
     * @param string $idfm
     */
    protected function __construct(string $token, string $idfm)
    {
        $this->setToken($token);
        $this->setIdfm($idfm);
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getIdfm(): string
    {
        return $this->idfm;
    }

    /**
     * @param string $idfm
     */
    public function setIdfm(string $idfm): void
    {
        $this->idfm = $idfm;
    }

    /**
     * @param Subscription $subscription
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function track(Subscription $subscription)
    {
        $response = (new Client())->request('POST', 'https://subtruck.magnus.ms/api/v2/transaction/', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => [
                'idfm' => $this->getIdfm(),
                'token' => $this->getToken(),
                'transaction' => json_encode($subscription->getProviderResponse()),
            ]
        ]);
    }
}