<?php

namespace Wowmaking\WebPurchases\Resources\Entities;

use Wowmaking\WebPurchases\Interfaces\ResourcesEntityInterface;

class PriceCurrency implements ResourcesEntityInterface
{

    public $id;

    public $amount;

    public $currency;

    public $country;

    public $trial_price_amount;


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
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     */
    public function setCurrency($currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return mixed
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param mixed $currency
     */
    public function setCountry($country): void
    {
        $this->country = $country;
    }

    /**
     * @return mixed
     */
    public function getTrialPriceAmount()
    {
        return $this->trial_price_amount;
    }

    /**
     * @param mixed $trial_price_amount
     */
    public function setTrialPriceAmount($trial_price_amount): void
    {
        $this->trial_price_amount = $trial_price_amount;
    }


}
