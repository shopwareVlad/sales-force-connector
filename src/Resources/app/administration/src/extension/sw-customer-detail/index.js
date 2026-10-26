import template from './template.html.twig';

const { Component } = Shopware;

Component.override('sw-customer-detail', {
    template,

    inject: ['salesforceSyncLogApiService'],

    data() {
        return {
            sfBaseUrl: null,
        };
    },

    computed: {
        sfContactId() {
            return this.customer?.customFields?.salesforce_contact_id ?? null;
        },

        sfLink() {
            if (!this.sfContactId || !this.sfBaseUrl) return null;
            return `${this.sfBaseUrl}/lightning/r/Contact/${this.sfContactId}/view`;
        },
    },

    watch: {
        'customer.id': {
            immediate: true,
            async handler(customerId) {
                if (!customerId) return;
                try {
                    const result = await this.salesforceSyncLogApiService.getSyncLog(customerId, 1);
                    this.sfBaseUrl = result.sfBaseUrl ?? null;
                } catch {
                }
            },
        },
    },
});

