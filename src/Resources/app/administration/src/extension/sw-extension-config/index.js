import template from './template.html.twig';

const { Component } = Shopware;

Component.override('sw-extension-config', {
    template,

    data() {
        return {
            fieldMappingJson: '',
        };
    },

    mounted() {
        this.$nextTick(() => this.loadFieldMapping());
    },

    methods: {
        loadFieldMapping() {
            if (this.namespace !== 'ItDeSalesforceConnector') {
                return;
            }
            const config = this.$refs.systemConfig;
            if (!config) {
                return;
            }
            const val = config.getConfigValue('ItDeSalesforceConnector.config.fieldMapping');
            this.fieldMappingJson = val || '';
        },

        onFieldMappingChange(json) {
            this.fieldMappingJson = json;
            const config = this.$refs.systemConfig;
            if (config) {
                config.setConfigValue('ItDeSalesforceConnector.config.fieldMapping', json);
            }
        },
    },
});

