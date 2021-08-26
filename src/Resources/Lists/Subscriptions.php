<?php

namespace Wowmaking\WebPurchases\Resources\Lists;

use Wowmaking\WebPurchases\Interfaces\ResourcesList;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class Subscriptions implements ResourcesList
{
    /** @var Subscription[] */
    protected $list = [];

    /**
     * @return Subscription[]
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