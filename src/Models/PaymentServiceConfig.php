<?php

namespace Wowmaking\WebPurchases\Models;

class PaymentServiceConfig
{
    /** @var string */
    protected $secret_api_key;

    /** @var string */
    protected $public_api_key;

    /**
     * @param string $secret_api_key
     * @param string $public_api_key
     * @return PaymentServiceConfig
     */
    public static function create(string $secret_api_key, string $public_api_key): PaymentServiceConfig
    {
        $config = new PaymentServiceConfig();

        $config->setPublicApiKey($public_api_key);
        $config->setSecretApiKey($secret_api_key);

        return $config;
    }

    /**
     * @return string
     */
    public function getSecretApiKey(): string
    {
        return $this->secret_api_key;
    }

    /**
     * @param string $secret_api_key
     */
    public function setSecretApiKey(string $secret_api_key): void
    {
        $this->secret_api_key = $secret_api_key;
    }

    /**
     * @return string
     */
    public function getPublicApiKey(): string
    {
        return $this->public_api_key;
    }

    /**
     * @param string $public_api_key
     */
    public function setPublicApiKey(string $public_api_key): void
    {
        $this->public_api_key = $public_api_key;
    }
}