const { ApiService } = Shopware.Classes;

export default class SalesforceStatsApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action/itde-salesforce');
        this.name = 'salesforceStatsApiService';
    }

    getStats(params = {}) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/sync-stats`, {
                headers: this.getBasicHeaders(),
                params,
            })
            .then(response => response.data);
    }

    retrySyncNow(customerId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-now/${customerId}`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then(response => response.data);
    }

    syncAllOrders() {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-all-orders`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then(response => response.data);
    }

    syncAllCustomers() {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-all-customers`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then(response => response.data);
    }

    getOrderSyncStats(params = {}) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/order-sync-stats`, {
                headers: this.getBasicHeaders(),
                params,
            })
            .then(response => response.data);
    }

    retryOrderSyncNow(orderId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-order-now/${orderId}`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then(response => response.data);
    }
}
