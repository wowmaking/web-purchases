<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\Dto;

final class TrackDataDto
{
    /**
     * @var string
     */
    private $internalUid;

    public function __construct(string $internalUid)
    {
        $this->internalUid = $internalUid;
    }

    public function getInternalUid(): string
    {
        return $this->internalUid;
    }
}
