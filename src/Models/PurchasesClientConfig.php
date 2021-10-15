<?php

namespace Wowmaking\WebPurchases\Models;

class PurchasesClientConfig
{
    /** @var string */
    private $client_type;

    /** @var string */
    private $secret_api_key;

    /** @var string */
    private $public_api_key;

    /** @var string|null */
    private $magnusToken;

    /** @var string|null */
    private $idfm;

    /**
     * @param string $client_type
     * @param string $secret_api_key
     * @param string $public_api_key
     * @param string|null $magnus_token
     * @param string|null $idfm
     * @return static
     */
    public static function create(string $client_type, string $secret_api_key, string $public_api_key, ?string $magnus_token = null, ?string $idfm = null): self
    {
        $config = new self();

        $config->setClientType($client_type);
        $config->setPublicApiKey($public_api_key);
        $config->setSecretApiKey($secret_api_key);
        $config->setMagnusToken($magnus_token);
        $config->setIdfm($idfm);

        return $config;
    }

    /**
     * @return string
     */
    public function getClientType(): string
    {
        return $this->client_type;
    }

    /**
     * @param string $client_type
     */
    public function setClientType(string $client_type): void
    {
        $this->client_type = $client_type;
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

    /**
     * @return string|null
     */
    public function getMagnusToken(): ?string
    {
        return $this->magnusToken;
    }

    /**
     * @param string|null $magnusToken
     */
    public function setMagnusToken(?string $magnusToken = null): void
    {
        $this->magnusToken = $magnusToken;
    }

    /**
     * @return string|null
     */
    public function getIdfm(): ?string
    {
        return $this->idfm;
    }

    /**
     * @param string|null $idfm
     */
    public function setIdfm(?string $idfm = null): void
    {
        $this->idfm = $idfm;
    }
}