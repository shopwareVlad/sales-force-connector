<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class SyncOrderMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string  $orderId,
        public readonly Context $context,
    ) {
    }
}
