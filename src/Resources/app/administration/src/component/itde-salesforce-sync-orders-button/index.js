import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-sync-orders-button', {
    template,

    inject: ['salesforceOrderSyncApiService'],

    data() {
        return {
            isLoading: false,
            showModal: false,
            error: null,
        };
    },

    methods: {
        onSyncAll() {
            this.error     = null;
            this.showModal = true; // open modal immediately

            this.isLoading = true;
            this.salesforceOrderSyncApiService.syncAllOrders()
                .catch((e) => {
                    this.error = e?.response?.data?.error ?? e?.message ?? 'Unknown error';
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onClose() {
            this.showModal = false;
        },
    },
});
