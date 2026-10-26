import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-test-button', {
    template,

    inject: ['salesforceTestApiService'],

    data() {
        return {
            isLoading: false,
            showModal: false,
            testResult: null,
        };
    },

    methods: {
        async onTest() {
            this.isLoading = true;
            this.showModal = true;
            this.testResult = null;

            try {
                this.testResult = await this.salesforceTestApiService.testConnection();
            } catch (error) {
                this.testResult = {
                    success: false,
                    steps: [{
                        name: 'Request failed',
                        passed: false,
                        message: error?.response?.data?.message ?? error.message ?? 'Unknown error',
                    }],
                };
            } finally {
                this.isLoading = false;
            }
        },

        onClose() {
            this.showModal = false;
        },
    },
});

