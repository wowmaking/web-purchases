<?php

declare(strict_types=1);

namespace Wowmaking\WebPurchases\Factories;

use Wowmaking\WebPurchases\PurchasesClients\PurchasesClient;
use Wowmaking\WebPurchases\Services\TrackParametersProviders\BaseTrackParametersProvider;
use Wowmaking\WebPurchases\Services\TrackParametersProviders\SolidgateTrackParametersProvider;
use Wowmaking\WebPurchases\Services\TrackParametersProviders\TrackParametersProviderInterface;

final class TrackParametersProviderFactory
{
    public function createBySystem(string $system): TrackParametersProviderInterface
    {
        if ($system === PurchasesClient::PAYMENT_SERVICE_SOLIDGATE) {
            return new SolidgateTrackParametersProvider();
        }

        return new BaseTrackParametersProvider();
    }
}
