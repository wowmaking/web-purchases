<?php

namespace Wowmaking\WebPurchases\Resources\Lists;

use Wowmaking\WebPurchases\Interfaces\ResourcesList;
use Wowmaking\WebPurchases\Resources\Entities\Price;

class Prices implements ResourcesList
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