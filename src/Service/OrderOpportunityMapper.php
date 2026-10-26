<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Shopware\Core\Checkout\Order\OrderEntity;

class OrderOpportunityMapper
{
    private const DEFAULT_STAGE_MAPPING = [
        'open'        => 'Prospecting',
        'in_progress' => 'Value Proposition',
        'completed'   => 'Closed Won',
        'cancelled'   => 'Closed Lost',
    ];

    public function toPayload(OrderEntity $order): array
    {
        $stateName    = $order->getStateMachineState()?->getTechnicalName() ?? 'open';
        $stageName    = self::DEFAULT_STAGE_MAPPING[$stateName] ?? 'Prospecting';
        $customer     = $order->getOrderCustomer();
        $closeDate    = ($order->getOrderDateTime() ?? new \DateTimeImmutable())->format('Y-m-d');
        $currency     = $order->getCurrency()?->getIsoCode() ?? 'EUR';
        $orderNumber  = $order->getOrderNumber() ?? $order->getId();
        $customerName = $customer ? trim($customer->getFirstName() . ' ' . $customer->getLastName()) : null;
        $customerEmail = $customer?->getEmail();

        $description = sprintf(
            'Shopware Order #%s | Customer: %s (%s) | Items: %d | Currency: %s',
            $orderNumber,
            $customerName ?? '—',
            $customerEmail ?? '—',
            $order->getLineItems()?->count() ?? 0,
            $currency
        );

        return array_filter([
            'Name'               => sprintf('Order #%s', $orderNumber),
            'Amount'             => $order->getAmountTotal(),
            'CloseDate'          => $closeDate,
            'StageName'          => $stageName,
            'Description'        => $description,
            'Order_Number__c'    => $orderNumber,
            'Customer_Email__c'  => $customerEmail,
            'Customer_Name__c'   => $customerName,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
