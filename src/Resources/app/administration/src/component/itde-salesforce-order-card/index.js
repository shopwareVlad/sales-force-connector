import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-order-card', {
    template,

    inject: ['salesforceOrderSyncApiService'],

    props: {
        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            status: null,
            syncedAt: null,
            errorMessage: null,
            sfOpportunityId: null,
            sfBaseUrl: null,
            isLoading: false,
            isSyncing: false,
            loadError: null,
        };
    },

    computed: {
        statusVariant() {
            if (!this.status) return 'neutral';
            return this.status === 'success' ? 'success' : 'critical';
        },

        sfLink() {
            if (!this.sfOpportunityId || !this.sfBaseUrl) return null;
            return `${this.sfBaseUrl}/lightning/r/Opportunity/${this.sfOpportunityId}/view`;
        },
    },

    created() {
        this.loadStatus();
    },

    methods: {
        async loadStatus() {
            if (!this.order?.id) return;

            this.isLoading = true;
            this.loadError = null;

            try {
                const result        = await this.salesforceOrderSyncApiService.getOrderSyncStatus(this.order.id);
                this.status         = result.status ?? null;
                this.syncedAt       = result.syncedAt ?? null;
                this.errorMessage   = result.errorMessage ?? null;
                this.sfOpportunityId = result.sfOpportunityId ?? null;
                this.sfBaseUrl      = result.sfBaseUrl ?? null;
            } catch (e) {
                this.loadError = e?.message ?? 'Failed to load sync status.';
            } finally {
                this.isLoading = false;
            }
        },

        async onSyncNow() {
            this.isSyncing = true;
            try {
                await this.salesforceOrderSyncApiService.syncOrderNow(this.order.id);
                setTimeout(() => this.loadStatus(), 2000);
            } catch (e) {
                this.loadError = e?.message ?? 'Failed to trigger sync.';
            } finally {
                this.isSyncing = false;
            }
        },

        formatDate(isoString) {
            if (!isoString) return '—';
            return new Date(isoString).toLocaleString();
        },
    },
});

