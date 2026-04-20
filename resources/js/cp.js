import GoogleMapsFieldtype from './components/fieldtypes/GoogleMapsFieldtype.vue';
import GoogleMapsIndex from './components/fieldtypes/GoogleMapsIndex.vue';
import GoogleMapsSettings from './components/GoogleMapsSettings.vue';

Statamic.booting(() => {
    Statamic.$components.register('google_maps-fieldtype', GoogleMapsFieldtype);
    Statamic.$components.register('google_maps-fieldtype-index', GoogleMapsIndex);
    Statamic.$components.register('google-maps-settings', GoogleMapsSettings);
});
