# ItDe Salesforce Connector

Bidirectional sync between **Shopware 6** and **Salesforce** via the Salesforce REST API using JWT Bearer authentication — no user password required.

---

## Installation

```bash
# 1. Copy/clone the plugin into the plugin directory
#    (it should already be at custom/plugins/ItDeSalesforceConnector)

# 2. Register and activate
bin/console plugin:refresh
bin/console plugin:install --activate ItDeSalesforceConnector

# 3. Clear the cache
bin/console cache:clear
```

On activation, the plugin automatically:
- Creates the `itde_salesforce_sync_log` database table (via migrations).
- Registers all required Shopware custom fields on `customer` and `order` entities.

---

## Message Queue (Required)

**The sync runs asynchronously via the Symfony Messenger.** Without a running worker, no syncing will happen.

Start a worker:
```bash
bin/console messenger:consume async --time-limit=3600
```

For production, run this as a supervised daemon (via systemd, Supervisor, etc.). Each sync message (customer or order) is handled by a dedicated handler and takes less than a second under normal conditions.

---

## Plugin Configuration

Navigate to **Admin → Extensions → My Extensions → ItDe Salesforce Connector → Configure**.

| Setting                   | Description |
|---------------------------|-------------|
| **Login URL**             | Your Salesforce My Domain URL, e.g. `https://yourorg.my.salesforce.com` |
| **Client ID**             | Connected App Consumer Key |
| **Username**              | Salesforce API user email |
| **JWT Private Key (PEM)** | Private key for signing JWT assertions — paste full PEM including `-----BEGIN RSA PRIVATE KEY-----` header/footer |
| **API Version**           | Salesforce API version, e.g. `v60.0` |
| **Conflict Resolution**   | `shopware_wins` — always overwrite SF · `salesforce_wins` — skip if Contact already exists |
| **Order Sync Enabled**    | Toggle automatic scheduled order sync |
| **Order Sync Statuses**   | Optional comma-separated state filter (leave empty to sync all orders) |
| **Inbound Poll Enabled**  | Toggle scheduled Salesforce → Shopware polling |
| **Field Mapping (JSON)**  | Optional override of the default customer → Contact field mapping (editable via the visual table UI) |

> ⚠️ The JWT private key is sensitive. Never commit it to version control. Use Shopware system config (stored in DB) or environment-specific config overrides.

---

## Salesforce Connected App Setup

1. **Setup → App Manager → New Connected App**
2. Enable **OAuth Settings**
3. Enable **Use Digital Signatures** — upload your **public certificate** (.crt)
4. Add OAuth scopes: `api` + `refresh_token, offline_access`
5. Under **Manage Connected Apps** → set **IP Relaxation** to "Relax IP restrictions"
6. Pre-authorise the API user: **Manage Connected Apps → [App] → Manage Profiles** — add the API user's profile
7. Copy the **Consumer Key** → paste as "Client ID" in plugin settings
8. Paste the matching **private key PEM** into "JWT Private Key"

---

## Required Salesforce Custom Fields

Create these fields in Salesforce **before** running the first sync.
Path: **Setup → Object Manager → [Object] → Fields & Relationships → New**

### Contact

| Field Label          | API Name                  | Type  | Length | External ID | Unique |
|----------------------|---------------------------|-------|--------|-------------|--------|
| Shopware Customer ID | `Shopware_Customer_ID__c` | Text  | 36     | ✅           | ✅      |

### Opportunity

| Field Label       | API Name               | Type  | Length | External ID | Unique |
|-------------------|------------------------|-------|--------|-------------|--------|
| Shopware Order ID | `Shopware_Order_ID__c` | Text  | 36     | ✅           | ✅      |
| Order Number      | `Order_Number__c`      | Text  | 64     | ❌           | ❌      |
| Customer Email    | `Customer_Email__c`    | Email | —      | ❌           | ❌      |
| Customer Name     | `Customer_Name__c`     | Text  | 255    | ❌           | ❌      |

### Product2

| Field Label         | API Name                 | Type | Length | External ID | Unique |
|---------------------|--------------------------|------|--------|-------------|--------|
| Shopware Product ID | `Shopware_Product_ID__c` | Text | 36     | ✅           | ✅      |

### OpportunityLineItem

| Field Label           | API Name                   | Type | Length | External ID | Unique |
|-----------------------|----------------------------|------|--------|-------------|--------|
| Shopware Line Item ID | `Shopware_Line_Item_ID__c` | Text | 36     | ❌           | ✅      |

### State & Country Picklists (recommended)

If customers have country/state addresses, enable picklists in Salesforce:
**Setup → "State and Country" → State and Country/Territory Picklists → Enable**

Without this, address sync still works but country/state fields are silently skipped as a fallback.

### Standard Pricebook

The connector requires an active Standard Pricebook (present by default in every Salesforce org) for syncing order line items. No action needed unless your org has explicitly disabled it.

---

## Verify the Connection

```bash
bin/console itde:salesforce:test
```

Or use the **Test Connection** button on the plugin config page. It checks:
1. JWT authentication succeeds
2. The Contact object is accessible via the API
3. The `Shopware_Customer_ID__c` field exists on Contact

---

## How It Works — Architecture

```
Shopware event / scheduled task
        │
        ▼
  Message dispatched to queue (async)
        │
        ▼
  Symfony Messenger worker picks it up
        │
        ▼
  Handler calls SalesforceApi → SalesforceHttpClient → PATCH/POST to SF REST API
        │
        ▼
  Result written to customer/order custom fields + itde_salesforce_sync_log table
```

**Key components:**

| Layer                | Class / File |
|----------------------|--------------|
| Auth                 | `SalesforceJwtAuthService` + `SalesforceTokenProvider` (token cached 10 min) |
| HTTP transport       | `SalesforceHttpClient` |
| REST API calls       | `SalesforceApi` |
| Customer mapping     | `CustomerContactMapper` |
| Order mapping        | `OrderOpportunityMapper` + `OrderLineItemMapper` |
| Line item sync       | `OpportunityLineItemSyncer` |
| Inbound sync         | `InboundSyncContactMessageHandler` |
| Config               | `ConfigProvider` |
| Outbound triggers    | `CustomerSubscriber`, `OrderSubscriber` |
| Scheduled tasks      | `SyncAllCustomersTaskHandler`, `SyncAllOrdersTaskHandler`, `RetryFailedSyncsTaskHandler`, `RetryFailedOrderSyncsTaskHandler`, `PollSalesforceChangesTaskHandler`, `CleanupSyncLogTaskHandler` |
| Admin API            | `SyncLogController` |
| Admin UI             | `itde-salesforce-dashboard`, customer/order detail cards |

---

## Code Structure

```
src/
├── Command/                    # CLI: bin/console itde:salesforce:sync-customers
├── Controller/Api/             # REST endpoints used by the admin UI
├── Entity/SyncLog/             # DAL entity for the sync log table
├── MessageQueue/
│   ├── Handler/                # Async message handlers (actual sync logic)
│   └── Message/                # Message DTOs
├── Migration/                  # DB migrations (auto-run on plugin install)
├── Resources/
│   ├── app/administration/src/ # Shopware admin Vue.js components
│   └── config/
│       ├── config.xml          # Plugin settings definition
│       ├── routes.xml          # API route registration
│       └── services.xml        # DI container wiring
├── ScheduledTask/              # Cron-like background tasks
├── Service/                    # Core business logic
├── Subscriber/                 # Shopware event listeners (trigger syncs)
└── Util/Lifecycle/             # Custom field setup on install/uninstall
```

---

## Shopware Custom Fields (auto-registered on install)

### On `customer` entity

| Custom field key                  | Type     | Purpose |
|-----------------------------------|----------|---------|
| `salesforce_contact_id`           | Text     | Linked SF Contact ID |
| `salesforce_last_sync_at`         | DateTime | Timestamp of last sync |
| `salesforce_last_sync_status`     | Text     | `success` or `error` |
| `salesforce_last_sync_error`      | Text     | Last error message |
| `salesforce_retry_count`          | Int      | Number of retry attempts |

### On `order` entity

| Custom field key                      | Type     | Purpose |
|---------------------------------------|----------|---------|
| `salesforce_opportunity_id`           | Text     | Linked SF Opportunity ID |
| `salesforce_order_sync_at`            | DateTime | Timestamp of last sync |
| `salesforce_order_sync_status`        | Text     | `success` or `error` |
| `salesforce_order_sync_error`         | Text     | Last error message |
| `salesforce_order_retry_count`        | Int      | Number of retry attempts |

---

## Automatic Sync Schedule

| Task                        | Default interval                  |
|-----------------------------|-----------------------------------|
| Sync all customers          | Daily                             |
| Sync all orders             | Daily                             |
| Retry failed customers      | Every hour (max 5 retries)        |
| Retry failed orders         | Every hour (max 5 retries)        |
| Poll SF → SW changes        | Every 15 minutes                  |
| Clean up old sync logs      | Weekly (retains last 90 days)     |

Manage intervals: **Admin → Settings → System → Scheduled Tasks**

---

## Sync Behaviour Notes

- **Guest orders**: if the customer has no Shopware account, the handler looks up a Salesforce Contact by email. If none is found, a new Contact is created automatically.
- **Duplicate detection**: if Salesforce returns a `DUPLICATES_DETECTED` error, the connector tries to extract the existing Contact ID from the error payload and links to it instead of failing.
- **Country/state picklist errors**: if the SF picklist does not include the customer's country/state, the sync retries without address region fields as a fallback (logged as a warning).
- **Conflict resolution `salesforce_wins`**: if a Contact already exists in SF for this customer, the outbound sync is skipped — only inbound polling updates Shopware.
- **Token caching**: the JWT access token is cached for 10 minutes. If you rotate credentials, clear the Symfony cache or wait for the TTL to expire.

---

## Default Field Mapping (Customer → SF Contact)

| Shopware field     | Salesforce Contact field |
|--------------------|--------------------------|
| email              | Email                    |
| firstName          | FirstName                |
| lastName           | LastName                 |
| phone (billing)    | Phone                    |
| street             | MailingStreet            |
| zipcode            | MailingPostalCode        |
| city               | MailingCity              |
| countryIso         | MailingCountryCode       |
| state short code   | MailingState             |

Override via the **Field Mapping** visual editor on the plugin config page (saved as JSON in system config).

---

## Order → Opportunity Stage Mapping

| Shopware order state | Salesforce Opportunity stage |
|----------------------|------------------------------|
| open                 | Prospecting                  |
| in_progress          | Value Proposition            |
| completed            | Closed Won                   |
| cancelled            | Closed Lost                  |

---

## Useful Console Commands

```bash
# Test the Salesforce connection
bin/console itde:salesforce:test

# Bulk-sync all customers (dispatches to queue)
bin/console itde:salesforce:sync-customers

# Bulk-sync with limit (useful for testing)
bin/console itde:salesforce:sync-customers --limit=10

# Run the message queue worker
bin/console messenger:consume async --time-limit=3600

# View scheduled tasks
bin/console scheduled-task:list
```
