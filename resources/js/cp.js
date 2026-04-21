import GoogleMapsFieldtype from "./components/fieldtypes/GoogleMapsFieldtype.vue";
import GoogleMapsIndex from "./components/fieldtypes/GoogleMapsIndex.vue";

Statamic.booting(() => {
    Statamic.$components.register("google_maps-fieldtype", GoogleMapsFieldtype);
    Statamic.$components.register(
        "google_maps-fieldtype-index",
        GoogleMapsIndex,
    );
});
