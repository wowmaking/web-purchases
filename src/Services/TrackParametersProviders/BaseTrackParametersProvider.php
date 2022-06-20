<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\Services\TrackParametersProviders;

use Wowmaking\WebPurchases\Dto\TrackDataDto;

final class BaseTrackParametersProvider implements TrackParametersProviderInterface
{
    public function provide(TrackDataDto $dataDto): array
    {
        return [];
    }
}
