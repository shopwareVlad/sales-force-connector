import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-dashboard-index', {
    template,

    inject: ['salesforceStatsApiService'],

    data() {
        return {
            isLoading: false,
            stats: null,
            orderStats: null,
            error: null,
            activeLogTab: 'customers',
            retryingId: null,
            retryingOrderId: null,

            customerPage: 1,
            customerLimit: 25,
            customerTotal: 0,
            customerStatusFilter: '',

            orderPage: 1,
            orderLimit: 25,
            orderTotal: 0,
            orderStatusFilter: '',

            autoRefreshEnabled: false,
            autoRefreshSeconds: 30,
            _refreshTimer: null,

            errorModal: false,
            errorModalText: '',

            syncOrdersLoading: false,
            syncOrdersModal: false,
            syncOrdersError: null,
            syncOrdersTotal: null,
            syncCustomersLoading: false,
            syncCustomersModal: false,
            syncCustomersError: null,
            syncCustomersTotal: null,
        };
    },

    computed: {
        successToday()      { return this.stats?.customerSync?.successToday ?? '—'; },
        errorToday()        { return this.stats?.customerSync?.errorToday   ?? '—'; },
        totalAllTime()      { return this.stats?.customerSync?.totalAllTime  ?? '—'; },
        lastPollAt() {
            return this.stats?.lastPollAt
                ? new Date(this.stats.lastPollAt).toLocaleString()
                : 'Never';
        },
        recentLogs()        { return this.stats?.recentLogs      ?? []; },
        orderSuccessToday() { return this.orderStats?.orderSync?.successToday ?? '—'; },
        orderErrorTotal()   { return this.orderStats?.orderSync?.errorTotal   ?? '—'; },
        orderNeverSynced()  { return this.orderStats?.orderSync?.neverSynced  ?? '—'; },
        recentOrderLogs()   { return this.orderStats?.recentLogs              ?? []; },

        customerTotalPages() { return Math.max(1, Math.ceil(this.customerTotal / this.customerLimit)); },
        orderTotalPages()    { return Math.max(1, Math.ceil(this.orderTotal    / this.orderLimit));    },
    },

    watch: {
        customerStatusFilter() { this.customerPage = 1; this.loadCustomerData(); },
        customerPage()         { this.loadCustomerData(); },
        orderStatusFilter()    { this.orderPage = 1;    this.loadOrderData(); },
        orderPage()            { this.loadOrderData(); },
        autoRefreshEnabled(val) {
            if (val) {
                this._refreshTimer = setInterval(() => {
                    this.loadCustomerData();
                    this.loadOrderData();
                }, this.autoRefreshSeconds * 1000);
            } else {
                clearInterval(this._refreshTimer);
                this._refreshTimer = null;
            }
        },
    },

    created() {
        this.loadCustomerData();
        this.loadOrderData();
    },

    beforeUnmount() {
        clearInterval(this._refreshTimer);
    },

    methods: {
        async loadCustomerData() {
            this.isLoading = true;
            this.error = null;
            try {
                this.stats = await this.salesforceStatsApiService.getStats({
                    page:   this.customerPage,
                    limit:  this.customerLimit,
                    status: this.customerStatusFilter,
                });
                this.customerTotal = this.stats.logTotal ?? 0;
            } catch (e) {
                this.error = e?.response?.data?.errors?.[0]?.detail ?? e.message ?? 'Unknown error';
            } finally {
                this.isLoading = false;
            }
        },

        async loadOrderData() {
            try {
                this.orderStats = await this.salesforceStatsApiService.getOrderSyncStats({
                    page:   this.orderPage,
                    limit:  this.orderLimit,
                    status: this.orderStatusFilter,
                });
                this.orderTotal = this.orderStats.logTotal ?? 0;
            } catch (e) {
            }
        },

        loadStats() {
            this.loadCustomerData();
            this.loadOrderData();
        },

        customerPrevPage() { if (this.customerPage > 1) this.customerPage--; },
        customerNextPage() { if (this.customerPage < this.customerTotalPages) this.customerPage++; },
        orderPrevPage()    { if (this.orderPage > 1)    this.orderPage--;    },
        orderNextPage()    { if (this.orderPage < this.orderTotalPages)    this.orderPage++;    },

        showErrorModal(text) {
            this.errorModalText = text || '';
            this.errorModal = true;
        },

        onSyncAllOrders() {
            this.syncOrdersError = null;
            this.syncOrdersTotal = null;
            this.syncOrdersModal = true;
            this.syncOrdersLoading = true;
            this.salesforceStatsApiService.syncAllOrders()
                .then(data => { this.syncOrdersTotal = data.total ?? null; })
                .catch(e => { this.syncOrdersError = e?.message ?? 'Unknown error'; })
                .finally(() => { this.syncOrdersLoading = false; });
        },

        onSyncAllCustomers() {
            this.syncCustomersError = null;
            this.syncCustomersTotal = null;
            this.syncCustomersModal = true;
            this.syncCustomersLoading = true;
            this.salesforceStatsApiService.syncAllCustomers()
                .then(data => { this.syncCustomersTotal = data.total ?? null; })
                .catch(e => { this.syncCustomersError = e?.message ?? 'Unknown error'; })
                .finally(() => { this.syncCustomersLoading = false; });
        },

        async retrySync(customerId) {
            this.retryingId = customerId;
            try {
                await this.salesforceStatsApiService.retrySyncNow(customerId);
                Shopware.Utils.createNotificationSuccess({ message: 'Customer sync queued.' });
            } catch (e) {
                Shopware.Utils.createNotificationError({ message: 'Could not queue sync: ' + (e.message ?? 'Unknown error') });
            } finally {
                this.retryingId = null;
            }
        },

        async retryOrderSync(orderId) {
            this.retryingOrderId = orderId;
            try {
                await this.salesforceStatsApiService.retryOrderSyncNow(orderId);
                Shopware.Utils.createNotificationSuccess({ message: 'Order sync queued.' });
                await this.loadOrderData();
            } catch (e) {
                Shopware.Utils.createNotificationError({ message: 'Could not queue sync: ' + (e.message ?? 'Unknown error') });
            } finally {
                this.retryingOrderId = null;
            }
        },

        statusVariant(status) { return status === 'success' ? 'success' : 'error'; },
        formatDate(iso)       { return iso ? new Date(iso).toLocaleString() : '—'; },
        formatDuration(ms) {
            if (ms == null) return '—';
            return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
        },
    },
});
