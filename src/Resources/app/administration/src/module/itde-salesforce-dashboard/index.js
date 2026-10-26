import './page/itde-salesforce-dashboard-index';

const { Module } = Shopware;

Module.register('itde-salesforce-dashboard', {
    type: 'plugin',
    name: 'Salesforce Dashboard',
    title: 'Salesforce Sync Dashboard',
    description: 'Monitor customer sync health and recent activity',
    color: '#0070D2',
    icon: 'regular-chart-line-up',

    routes: {
        index: {
            component: 'itde-salesforce-dashboard-index',
            path: 'index',
        },
    },

    navigation: [{
        label: 'Salesforce Sync',
        color: '#0070D2',
        path: 'itde.salesforce.dashboard.index',
        icon: 'regular-chart-line-up',
        parent: 'sw-extension',
        position: 10,
    }],
});

