<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Entity\SyncLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SyncLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $status;

    protected ?string $salesforceContactId = null;

    protected ?string $errorMessage = null;

    protected \DateTimeImmutable $syncedAt;

    protected ?int $durationMs = null;

    public function getCustomerId(): string { return $this->customerId; }
    public function setCustomerId(string $customerId): void { $this->customerId = $customerId; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function getSalesforceContactId(): ?string { return $this->salesforceContactId; }
    public function setSalesforceContactId(?string $id): void { $this->salesforceContactId = $id; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $msg): void { $this->errorMessage = $msg; }

    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
    public function setSyncedAt(\DateTimeImmutable $dt): void { $this->syncedAt = $dt; }

    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $ms): void { $this->durationMs = $ms; }
}

