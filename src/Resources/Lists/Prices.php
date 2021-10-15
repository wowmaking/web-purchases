<?php

namespace Wowmaking\WebPurchases\Resources\Lists;

use Wowmaking\WebPurchases\Interfaces\ResourcesListInterface;
use Wowmaking\WebPurchases\Resources\Entities\Price;

class Prices implements ResourcesListInterface
{
    /** @var Price[] */
    protected $list = [];

    /**
     * @return Price[]
     */
    public function getList(): array
    {
        return $this->list;
    }

    /**
     * @param array $list
     */
    public function setList(array $list): void
    {
        $this->list = $list;
    }
}