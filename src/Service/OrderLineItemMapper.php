<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemMapper
{
    public function toProduct2Payload(OrderLineItemEntity $lineItem): array
    {
        $productNumber = $lineItem->getPayload()['productNumber'] ?? null;

        return array_filter([
            'Name'        => $lineItem->getLabel(),
            'ProductCode' => $productNumber,
            'Description' => sprintf(
                'Shopware product: %s (SKU: %s)',
                $lineItem->getLabel(),
                $productNumber ?? '—'
            ),
            'IsActive'    => true,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public function toOpportunityLineItemPayload(
        OrderLineItemEntity $lineItem,
        string              $pricebookEntryId
    ): array {
        return array_filter([
            'PricebookEntryId'         => $pricebookEntryId,
            'Quantity'                 => (float) $lineItem->getQuantity(),
            'UnitPrice'                => round($lineItem->getUnitPrice() ?? 0.0, 2),
            'Description'              => $lineItem->getLabel(),
            'Shopware_Line_Item_ID__c' => $lineItem->getId(),
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
