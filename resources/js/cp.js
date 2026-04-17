import GatewaySettings from './components/GatewaySettings.vue';

Statamic.booting(() => {
    Statamic.$components.register('gateway-settings', GatewaySettings);
});
