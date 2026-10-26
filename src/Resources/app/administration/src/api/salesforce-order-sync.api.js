const { ApiService } = Shopware.Classes;

class SalesforceOrderSyncApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action/itde-salesforce');
        this.name = 'salesforceOrderSyncApiService';
    }

    getOrderSyncStatus(orderId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/order-sync-status/${orderId}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    syncOrderNow(orderId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-order-now/${orderId}`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    syncAllOrders() {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-all-orders`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

export default SalesforceOrderSyncApiService;
