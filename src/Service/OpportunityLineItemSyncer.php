<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;

class OpportunityLineItemSyncer
{
    private ?string $standardPricebookId = null;

    public function __construct(
        private readonly SalesforceApi       $api,
        private readonly OrderLineItemMapper  $mapper,
        private readonly LoggerInterface      $logger,
    ) {
    }

    public function sync(string $sfOpportunityId, OrderEntity $order, ?string $salesChannelId): void
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null || $lineItems->count() === 0) {
            return;
        }

        try {
            $pricebookId = $this->resolveStandardPricebookId($salesChannelId);
        } catch (\Throwable $e) {
            $this->logger->warning('OpportunityLineItem sync: could not resolve Standard Pricebook', [
                'orderId' => $order->getId(),
                'error'   => $e->getMessage(),
            ]);
            return;
        }

        $this->deleteExistingLineItems($sfOpportunityId, $salesChannelId);

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $productId = $lineItem->getProductId() ?? $lineItem->getId();

            try {
                $this->api->upsertByExternalId(
                    'Product2',
                    'Shopware_Product_ID__c',
                    $productId,
                    $this->mapper->toProduct2Payload($lineItem),
                    $salesChannelId
                );

                $pricebookEntryId = $this->getOrCreatePricebookEntry(
                    $productId,
                    $pricebookId,
                    round($lineItem->getUnitPrice() ?? 0.0, 2),
                    $salesChannelId
                );

                $this->api->createRecord(
                    'OpportunityLineItem',
                    array_merge(
                        ['OpportunityId' => $sfOpportunityId],
                        $this->mapper->toOpportunityLineItemPayload($lineItem, $pricebookEntryId)
                    ),
                    $salesChannelId
                );

            } catch (\Throwable $e) {
                $this->logger->warning('OpportunityLineItem sync: failed for line item', [
                    'orderId'    => $order->getId(),
                    'lineItemId' => $lineItem->getId(),
                    'label'      => $lineItem->getLabel(),
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveStandardPricebookId(?string $salesChannelId): string
    {
        if ($this->standardPricebookId !== null) {
            return $this->standardPricebookId;
        }

        $records = $this->api->queryAll(
            'SELECT Id FROM Pricebook2 WHERE IsStandard = true LIMIT 1',
            $salesChannelId
        );

        $id = $records[0]['Id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Could not find Standard Pricebook2 in Salesforce.');
        }

        $this->standardPricebookId = $id;
        return $id;
    }

    private function deleteExistingLineItems(string $sfOpportunityId, ?string $salesChannelId): void
    {
        try {
            $existing = $this->api->queryAll(
                sprintf(
                    "SELECT Id FROM OpportunityLineItem WHERE OpportunityId = '%s'",
                    addslashes($sfOpportunityId)
                ),
                $salesChannelId
            );

            foreach ($existing as $record) {
                if (!empty($record['Id'])) {
                    $this->api->deleteRecord('OpportunityLineItem', $record['Id'], $salesChannelId);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('OpportunityLineItem sync: could not delete existing line items', [
                'sfOpportunityId' => $sfOpportunityId,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function getOrCreatePricebookEntry(
        string  $shopwareProductId,
        string  $pricebookId,
        float   $unitPrice,
        ?string $salesChannelId
    ): string {
        try {
            $product = $this->api->getByExternalId(
                'Product2',
                'Shopware_Product_ID__c',
                $shopwareProductId,
                ['Id'],
                $salesChannelId
            );
            $product2Id = $product['Id'] ?? null;
        } catch (\Throwable) {
            $product2Id = null;
        }

        if (!$product2Id) {
            throw new \RuntimeException(sprintf(
                'Could not find Product2 with Shopware_Product_ID__c = %s',
                $shopwareProductId
            ));
        }

        $entries = $this->api->queryAll(
            sprintf(
                "SELECT Id FROM PricebookEntry WHERE Pricebook2Id = '%s' AND Product2Id = '%s' LIMIT 1",
                addslashes($pricebookId),
                addslashes($product2Id)
            ),
            $salesChannelId
        );

        if (!empty($entries[0]['Id'])) {
            return $entries[0]['Id'];
        }

        $result = $this->api->createRecord('PricebookEntry', [
            'Pricebook2Id' => $pricebookId,
            'Product2Id'   => $product2Id,
            'UnitPrice'    => $unitPrice,
            'IsActive'     => true,
        ], $salesChannelId);

        $id = $result['id'] ?? $result['Id'] ?? null;
        if (!$id) {
            throw new \RuntimeException('Failed to create PricebookEntry — no Id returned by Salesforce.');
        }

        return $id;
    }
}
