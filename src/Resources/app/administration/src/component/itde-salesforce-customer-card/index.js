import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-customer-card', {
    template,

    inject: ['salesforceSyncLogApiService'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            logEntries: [],
            sfBaseUrl: null,
            isLoading: false,
            isSyncing: false,
            isPulling: false,
            loadError: null,
        };
    },

    computed: {
        lastEntry() {
            return this.logEntries[0] ?? null;
        },

        statusVariant() {
            if (!this.lastEntry) return 'neutral';
            return this.lastEntry.status === 'success' ? 'success' : 'critical';
        },

        sfContactId() {
            return this.customer?.customFields?.salesforce_contact_id ?? null;
        },

        sfLink() {
            if (!this.sfContactId || !this.sfBaseUrl) return null;
            return `${this.sfBaseUrl}/lightning/r/Contact/${this.sfContactId}/view`;
        },
    },

    created() {
        this.loadLog();
    },

    methods: {
        async loadLog() {
            if (!this.customer?.id) return;

            this.isLoading  = true;
            this.loadError  = null;

            try {
                const result    = await this.salesforceSyncLogApiService.getSyncLog(this.customer.id, 10);
                this.logEntries = result.entries ?? [];
                this.sfBaseUrl  = result.sfBaseUrl ?? null;
            } catch (e) {
                this.loadError = e?.message ?? 'Failed to load sync log.';
            } finally {
                this.isLoading = false;
            }
        },

        async onSyncNow() {
            this.isSyncing = true;
            try {
                await this.salesforceSyncLogApiService.syncNow(this.customer.id);
                setTimeout(() => this.loadLog(), 2000);
            } catch (e) {
                this.loadError = e?.message ?? 'Failed to trigger sync.';
            } finally {
                this.isSyncing = false;
            }
        },

        async onPullFromSalesforce() {
            this.isPulling = true;
            try {
                await this.salesforceSyncLogApiService.pullFromSalesforce(this.customer.id);
                setTimeout(() => this.loadLog(), 2000);
            } catch (e) {
                this.loadError = e?.message ?? 'Failed to pull from Salesforce.';
            } finally {
                this.isPulling = false;
            }
        },

        formatDate(isoString) {
            if (!isoString) return '—';
            return new Date(isoString).toLocaleString();
        },
    },
});

