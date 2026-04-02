<?php

declare(strict_types=1);

namespace App\Service\Push;

use Dru1x\ExpoPush\ExpoPush;
use Dru1x\ExpoPush\PushMessage\PushMessageCollection;
use Dru1x\ExpoPush\PushReceipt\PushReceiptIdCollection;
use Dru1x\ExpoPush\Result\GetReceiptsResult;
use Dru1x\ExpoPush\Result\SendNotificationsResult;
use Saloon\Exceptions\InvalidPoolItemException;

class ExpoPushService
{
    private readonly ExpoPush $expo;

    public function __construct(
        private readonly string $expoAccessToken
    ) {
        $this->expo = new ExpoPush($this->expoAccessToken !== '' ? $this->expoAccessToken : null);
    }

    /**
     * @throws InvalidPoolItemException
     */
    public function send(PushMessageCollection $messages): SendNotificationsResult
    {
        return $this->expo->sendNotifications($messages);
    }

    /**
     * @throws InvalidPoolItemException
     */
    public function getReceipts(PushReceiptIdCollection $ids): GetReceiptsResult
    {
        return $this->expo->getReceipts($ids);
    }
}
