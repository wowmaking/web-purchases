<?php

namespace Wowmaking\WebPurchases\Resources\Entities;

use Wowmaking\WebPurchases\Interfaces\ResourcesEntityInterface;

class Customer implements ResourcesEntityInterface
{
    public $id;

    public $email;

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
