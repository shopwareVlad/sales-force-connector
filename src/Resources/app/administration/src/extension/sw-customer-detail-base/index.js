import template from './template.html.twig';

const { Component } = Shopware;

Component.override('sw-customer-detail-base', {
    template,
});

