<?php

namespace Wowmaking\WebPurchases\Resources\Entities;

use Wowmaking\WebPurchases\Interfaces\ResourcesEntityInterface;

class Subscription implements ResourcesEntityInterface
{
    public $transaction_id;

    public $plan_name;

    public $email;

    public $currency;

    public $amount;

    public $customer_id;

    public $created_at;

    public $trial_start_at;

    public $trial_end_at;

    public $expire_at;

    public $canceled_at;

    public $state;

    public $is_active;

    public $provider;

    public $provider_response;

    /**
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        return $this->transaction_id;
    }

    /**
     * @param string|null $transaction_id
     */
    public function setTransactionId(?string $transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    /**
     * @return string|null
     */
    public function getPlanName(): ?string
    {
        return $this->plan_name;
    }

    /**
     * @param string|null $plan_name
     */
    public function setPlanName(?string $plan_name): void
    {
        $this->plan_name = $plan_name;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * @param string|null $currency
     */
    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->customer_id;
    }

    /**
     * @param string|null $customerId
     */
    public function setCustomerId(?string $customerId): void
    {
        $this->customer_id = $customerId;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    /**
     * @param string|null $created_at
     */
    public function setCreatedAt(?string $created_at): void
    {
        $this->created_at = $created_at;
    }

    /**
     * @return string|null
     */
    public function getTrialStartAt(): ?string
    {
        return $this->trial_start_at;
    }

    /**
     * @param string|null $trial_start_at
     */
    public function setTrialStartAt(?string $trial_start_at): void
    {
        $this->trial_start_at = $trial_start_at;
    }

    /**
     * @return string|null
     */
    public function getTrialEndAt(): ?string
    {
        return $this->trial_end_at;
    }

    /**
     * @param string|null $trial_end_at
     */
    public function setTrialEndAt(?string $trial_end_at): void
    {
        $this->trial_end_at = $trial_end_at;
    }

    /**
     * @return string|null
     */
    public function getExpireAt(): ?string
    {
        return $this->expire_at;
    }

    /**
     * @param string|null $expire_at
     */
    public function setExpireAt(?string $expire_at): void
    {
        $this->expire_at = $expire_at;
    }

    /**
     * @return string|null
     */
    public function getCanceledAt(): ?string
    {
        return $this->canceled_at;
    }

    /**
     * @param string|null $canceled_at
     */
    public function setCanceledAt(?string $canceled_at): void
    {
        $this->canceled_at = $canceled_at;
    }

    /**
     * @return string|null
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * @param string|null $state
     */
    public function setState(?string $state): void
    {
        $this->state = $state;
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
     * @return array|null
     */
    public function getProviderResponse(): ?array
    {
        return $this->provider_response;
    }

    /**
     * @param array|null $providerResponse
     */
    public function setProviderResponse(?array $providerResponse): void
    {
        $this->provider_response = $providerResponse;
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
}
