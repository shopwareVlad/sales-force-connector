const { ApiService } = Shopware.Classes;

class SalesforceTestApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, '_action/itde-salesforce');
        this.name = 'salesforceTestApiService';
    }

    testConnection() {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/test-connection`,
                {},
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}

export default SalesforceTestApiService;

