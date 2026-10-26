import SalesforceTestApiService from './api/salesforce-test.api';
import SalesforceSyncLogApiService from './api/salesforce-sync-log.api';
import SalesforceOrderSyncApiService from './api/salesforce-order-sync.api';
import SalesforceStatsApiService from './api/salesforce-stats.api';
import './component/itde-salesforce-test-button';
import './component/itde-salesforce-customer-card';
import './component/itde-salesforce-order-card';
import './component/itde-salesforce-sync-orders-button';
import './component/itde-salesforce-field-mapping-editor';
import './extension/sw-extension-config';
import './extension/sw-customer-detail-base';
import './extension/sw-customer-detail';
import './extension/sw-order-detail-base';
import './module/itde-salesforce-dashboard';

const { Application } = Shopware;

Application.addServiceProvider('salesforceTestApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SalesforceTestApiService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('salesforceSyncLogApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SalesforceSyncLogApiService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('salesforceOrderSyncApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SalesforceOrderSyncApiService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('salesforceStatsApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SalesforceStatsApiService(initContainer.httpClient, container.loginService);
});

