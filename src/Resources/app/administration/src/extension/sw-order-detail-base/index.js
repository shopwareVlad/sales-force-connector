import template from './template.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail-general', {
    template,
});

