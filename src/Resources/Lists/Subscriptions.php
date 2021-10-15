<?php

namespace Wowmaking\WebPurchases\Resources\Lists;

use Wowmaking\WebPurchases\Interfaces\ResourcesListInterface;
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

class Subscriptions implements ResourcesListInterface
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