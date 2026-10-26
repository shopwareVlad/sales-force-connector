import template from './template.html.twig';

const { Component } = Shopware;

Component.register('itde-salesforce-field-mapping-editor', {
    template,

    props: {
        value: {
            type: String,
            default: '',
        },
    },

    emits: ['update:value'],

    data() {
        return {
            rows: [],
        };
    },

    computed: {
        shopwareFieldOptions() {
            return [
                { value: 'email',      label: 'Email' },
                { value: 'firstName',  label: 'First Name' },
                { value: 'lastName',   label: 'Last Name' },
                { value: 'phone',      label: 'Phone' },
                { value: 'street',     label: 'Street' },
                { value: 'zipcode',    label: 'Zip / Postal Code' },
                { value: 'city',       label: 'City' },
                { value: 'countryIso', label: 'Country (ISO code)' },
                { value: 'stateName',  label: 'State / Province' },
            ];
        },

        defaultRows() {
            return [
                { shopwareField: 'email',      sfField: 'Email' },
                { shopwareField: 'firstName',  sfField: 'FirstName' },
                { shopwareField: 'lastName',   sfField: 'LastName' },
                { shopwareField: 'phone',      sfField: 'Phone' },
                { shopwareField: 'street',     sfField: 'MailingStreet' },
                { shopwareField: 'zipcode',    sfField: 'MailingPostalCode' },
                { shopwareField: 'city',       sfField: 'MailingCity' },
                { shopwareField: 'countryIso', sfField: 'MailingCountryCode' },
                { shopwareField: 'stateName',  sfField: 'MailingState' },
            ];
        },
    },

    watch: {
        value: {
            immediate: true,
            handler(val) {
                this.rows = this.parseValue(val);
            },
        },
    },

    methods: {
        parseValue(val) {
            if (!val || val.trim() === '') {
                return this.defaultRows.map(r => ({ ...r }));
            }
            try {
                const obj = JSON.parse(val);
                if (typeof obj === 'object' && obj !== null) {
                    return Object.entries(obj).map(([shopwareField, sfField]) => ({
                        shopwareField,
                        sfField: String(sfField),
                    }));
                }
            } catch (_) {}
            return this.defaultRows.map(r => ({ ...r }));
        },

        emit() {
            const obj = {};
            for (const row of this.rows) {
                if (row.shopwareField && row.sfField) {
                    obj[row.shopwareField] = row.sfField;
                }
            }
            this.$emit('update:value', JSON.stringify(obj));
        },

        addRow() {
            this.rows.push({ shopwareField: '', sfField: '' });
            this.emit();
        },

        removeRow(index) {
            this.rows.splice(index, 1);
            this.emit();
        },

        onRowChange() {
            this.emit();
        },

        resetToDefaults() {
            this.rows = this.defaultRows.map(r => ({ ...r }));
            this.emit();
        },
    },
});

