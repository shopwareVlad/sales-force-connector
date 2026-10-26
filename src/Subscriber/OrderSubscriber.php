<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Subscriber;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use ItDe\SalesforceConnector\Service\ConfigProvider;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class OrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ConfigProvider      $configProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT             => 'onOrderWritten',
            'state_enter.order.state.completed'          => 'onStateChange',
            'state_enter.order.state.cancelled'          => 'onStateChange',
            'state_enter.order.state.in_progress'        => 'onStateChange',
            'state_enter.order_delivery.state.shipped'   => 'onDeliveryStateChange',
            'state_enter.order_delivery.state.returned'  => 'onDeliveryStateChange',
        ];
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->hasState('salesforce-sync-update')) {
            return;
        }

        foreach ($event->getWriteResults() as $result) {
            $id = $result->getPrimaryKey();
            if (!is_string($id) || !Uuid::isValid($id)) {
                continue;
            }

            $payload = $result->getPayload();
            if (isset($payload['customFields']) && $this->isOnlySalesforceFields($payload['customFields'])) {
                continue;
            }

            $this->bus->dispatch(new SyncOrderMessage($id, $event->getContext()));
        }
    }

    public function onStateChange(OrderStateMachineStateChangeEvent $event): void
    {
        $stateName = $event->getOrder()->getStateMachineState()?->getTechnicalName();
        $allowed   = $this->configProvider->getOrderSyncStatuses();

        if (!empty($allowed) && $stateName !== null && !\in_array($stateName, $allowed, true)) {
            return;
        }

        $this->bus->dispatch(new SyncOrderMessage(
            $event->getOrder()->getId(),
            $event->getContext()
        ));
    }

    public function onDeliveryStateChange(OrderStateMachineStateChangeEvent $event): void
    {
        $stateName = $event->getOrder()->getStateMachineState()?->getTechnicalName();
        $allowed   = $this->configProvider->getOrderSyncStatuses();

        if (!empty($allowed) && $stateName !== null && !\in_array($stateName, $allowed, true)) {
            return;
        }

        $this->bus->dispatch(new SyncOrderMessage($event->getOrder()->getId(), $event->getContext()));
    }

    private function isOnlySalesforceFields(array $customFields): bool
    {
        foreach (array_keys($customFields) as $key) {
            if (!str_starts_with((string) $key, 'salesforce_order_') && !str_starts_with((string) $key, 'salesforce_opportunity_')) {
                return false;
            }
        }
        return true;
    }
}
