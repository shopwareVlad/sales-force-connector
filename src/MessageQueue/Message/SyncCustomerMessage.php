<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Context;

final class SyncCustomerMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $customerId,
        public Context $context,
    ) {
    }
}