<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Message;

use Shopware\Core\Framework\Context;

final class InboundSyncContactMessage
{
    public function __construct(
        public readonly string  $salesforceContactId,
        public readonly Context $context,
    ) {
    }
}
