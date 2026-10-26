<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Subscriber;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CustomerSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => 'onRegister',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onWritten',
        ];
    }

    public function onRegister(CustomerRegisterEvent $event): void
    {
        $this->bus->dispatch(new SyncCustomerMessage(
            $event->getCustomer()->getId(),
            $event->getContext()
        ));
    }

    public function onWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->hasState('salesforce-sync-update')) {
            return;
        }

        foreach ($event->getWriteResults() as $result) {
            $id = $result->getPrimaryKey();
            if (\is_string($id) && Uuid::isValid($id)) {
                $payload = $result->getPayload();
                if (isset($payload['customFields']) && $this->isOnlySalesforceFieldsUpdated($payload['customFields'])) {
                    continue;
                }

                $this->bus->dispatch(new SyncCustomerMessage(
                    $id,
                    $event->getContext()
                ));
            }
        }
    }

    private function isOnlySalesforceFieldsUpdated(array $customFields): bool
    {
        foreach (array_keys($customFields) as $key) {
            if (!str_starts_with($key, 'salesforce_')) {
                return false;
            }
        }
        return true;
    }
}