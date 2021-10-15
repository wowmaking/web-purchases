<?php

namespace Wowmaking\WebPurchases\Interfaces;

interface ResourcesListInterface
{
    public function getList();

    public function setList(array $list);
}