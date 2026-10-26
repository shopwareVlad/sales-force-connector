const { ApiService } = Shopware.Classes;

class SalesforceSyncLogApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action/itde-salesforce');
        this.name = 'salesforceSyncLogApiService';
    }

    getSyncLog(customerId, limit = 25) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/sync-log/${customerId}?limit=${limit}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    syncNow(customerId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/sync-now/${customerId}`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    pullFromSalesforce(customerId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/pull-from-salesforce/${customerId}`, {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

export default SalesforceSyncLogApiService;

