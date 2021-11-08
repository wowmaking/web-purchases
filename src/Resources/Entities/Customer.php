<?php

namespace Wowmaking\WebPurchases\Resources\Entities;

use Wowmaking\WebPurchases\Interfaces\ResourcesEntityInterface;

class Customer implements ResourcesEntityInterface
{
    public $id;

    public $email;

    public $provider;

    public $is_active = true;

    public $provider_response;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     */
    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * @return bool
     */
    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    /**
     * @param mixed $is_active
     */
    public function setIsActive(bool $is_active): void
    {
        $this->is_active = $is_active;
    }

    /**
     * @return mixed
     */
    public function getProviderResponse()
    {
        return $this->provider_response;
    }

    /**
     * @param mixed $provider_response
     */
    public function setProviderResponse($provider_response): void
    {
        $this->provider_response = $provider_response;
    }

}
