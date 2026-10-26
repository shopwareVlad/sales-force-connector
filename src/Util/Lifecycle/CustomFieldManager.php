<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Util\Lifecycle;

use ItDe\SalesforceConnector\Util\Lifecycle\Traits\CustomFieldSetStorageTrait;
use ItDe\SalesforceConnector\Util\Lifecycle\Traits\CustomFieldStorageTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldManager
{
    use CustomFieldSetStorageTrait;
    use CustomFieldStorageTrait;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Context $context
    ) {}

    public function create(): void
    {
        $this->createCustomFieldSets();
        $this->createCustomFields();
    }

    public function remove(): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', array_keys($this->customFieldSets)));
        $result = $customFieldSetRepository?->search($criteria, $this->context)->getIds();
        $result = array_map(static function (string $id): array {
            return ['id' => $id];
        }, array_values($result));
        if (!$result) {
            return;
        }
        $customFieldSetRepository?->delete($result, $this->context);
    }

    public function createCustomFieldSets(): void
    {
        $this->validateCustomFieldSets();
        if (!$this->customFieldSets) {
            return;
        }

        $entityRepository = $this->container->get('custom_field_set.repository');
        $entityRepository->upsert(array_values($this->customFieldSets), $this->context);

    }


    protected function validateCustomFieldSets(): void
    {
        $customFieldSetNames = array_keys($this->customFieldSets);

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('name', $customFieldSetNames)
        );

        $customFieldSetEntities = $customFieldSetRepository?->search($criteria, $this->context);

        $customFieldSetEntities->filter(function (CustomFieldSetEntity $customFieldSetEntity) {
            $customFieldSetIndex = array_key_exists($customFieldSetEntity->getName(), $this->customFieldSets) ? $customFieldSetEntity->getName() : "";
            if ($customFieldSetIndex) {
                unset($this->customFieldSets[$customFieldSetIndex]);
            }
        });
    }

    private function createCustomFields(): void
    {
        $this->validateCustomFields();
        $allCustomFields = array_merge(...array_values($this->customFields));

        $customFieldRepository = $this->container->get('custom_field.repository');
        $customFieldRepository->upsert($allCustomFields, $this->context);
    }

    private function validateCustomFields(): void
    {
        $customSetCriteria = new Criteria();
        $customSetCriteria->addFilter(new EqualsAnyFilter('name', array_keys($this->customFields)));

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $customFieldSetEntities = $customFieldSetRepository->search($customSetCriteria, $this->context);

        $customFieldSetEntities->filter(function (CustomFieldSetEntity $customFieldSetEntity) {
            if (!array_key_exists($customFieldSetEntity->getName(), $this->customFields)) {
                return;
            }
            foreach ($this->customFields[$customFieldSetEntity->getName()] as $index => $customField) {
                $this->customFields[$customFieldSetEntity->getName()][$index]['customFieldSetId'] =
                    $customFieldSetEntity->getId();
            }
        });

        $customFieldCriteria = new Criteria();
        $customFieldCriteria
            ->addAssociation('customFieldSet')
            ->addFilter(new EqualsAnyFilter('customFieldSetId', array_keys($customFieldSetEntities->getIds())));

        $customFieldEntities = $this->container->get('custom_field.repository')?->search($customFieldCriteria,
            $this->context)->getEntities();

        $customFieldEntities->filter(function (CustomFieldEntity $customFieldEntity) {
            foreach ($this->customFields[$customFieldEntity->getCustomFieldSet()?->getName()] as $key => $customField) {
                if ($customField['name'] === $customFieldEntity->getName()) {
                    unset($this->customFields[$customFieldEntity->getCustomFieldSet()?->getName()][$key]);
                }
            }
        });
    }
}