<?php

namespace Wowmaking\WebPurchases\Resources\Entities;

use Wowmaking\WebPurchases\Interfaces\ResourcesEntityInterface;

class Price implements ResourcesEntityInterface
{
    public $id;

    public $amount;

    public $currency;

    public $period;

    public $trial_period_days;

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
    public function getTrialPeriodDays()
    {
        return $this->trial_period_days;
    }

    /**
     * @param mixed $trial_period_days
     */
    public function setTrialPeriodDays($trial_period_days): void
    {
        $this->trial_period_days = $trial_period_days;
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

    /**
     * @param int $length
     * @param string $unit
     * @return void
     */
    public function setPeriod(int $length, string $unit): void
    {
        $this->period = sprintf('P%d%s', $length, strtoupper($unit[0]));
    }
}
