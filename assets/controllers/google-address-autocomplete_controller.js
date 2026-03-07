import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        apiKey: String
    }

    connect() {
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
            this.loadGoogleMapsAPI();
        } else {
            this.initializeAutocomplete();
        }
    }

    loadGoogleMapsAPI() {
        const apiKey = this.apiKeyValue;

        if (!apiKey) {
            console.error('Google Maps API key is required for address autocomplete');
            return;
        }

        if (window.googleMapsLoading) {
            window.googleMapsLoading.then(() => this.initializeAutocomplete());
            return;
        }

        window.googleMapsLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
            script.async = true;

            script.onload = () => {
                resolve();
                this.initializeAutocomplete();
            };

            script.onerror = () => {
                reject(new Error('Failed to load Google Maps API'));
            };

            document.head.appendChild(script);
        });
    }

    initializeAutocomplete() {
        const hiddenInput = this.element;
        const form = hiddenInput.closest('form');

        // Hidden input to carry pre-geocoded JSON to the backend, avoiding a server-side API call
        const geocodedDataInput = document.createElement('input');
        geocodedDataInput.type = 'hidden';
        geocodedDataInput.name = hiddenInput.name.replace('addressInput', 'addressGeocodedData');
        hiddenInput.after(geocodedDataInput);

        const placeAutocomplete = new google.maps.places.PlaceAutocompleteElement({
            types: ['address'],
        });

        placeAutocomplete.style.width = '100%';
        placeAutocomplete.style.display = 'block';

        hiddenInput.parentElement.insertBefore(placeAutocomplete, hiddenInput);
        hiddenInput.type = 'hidden';

        // Fallback: sync typed text on submit when no place was selected from the dropdown
        if (form) {
            form.addEventListener('submit', () => {
                if (!hiddenInput.value) {
                    const internalInput = placeAutocomplete.querySelector('input');
                    if (internalInput) {
                        hiddenInput.value = internalInput.value;
                    }
                }
            });
        }

        placeAutocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
            const place = placePrediction.toPlace();

            try {
                await place.fetchFields({
                    fields: ['formattedAddress', 'addressComponents', 'location', 'id'],
                });

                hiddenInput.value = place.formattedAddress;
                geocodedDataInput.value = JSON.stringify(this.extractGeocodedData(place, form));
                this.showAddressConfirmation(place.formattedAddress, placeAutocomplete);
            } catch (err) {
                console.error('Failed to fetch place details:', err);
                this.showAddressError(placeAutocomplete);
            }
        });
    }

    extractGeocodedData(place, form) {
        const get = (types) => {
            const comp = (place.addressComponents ?? []).find(
                (c) => c.types.some((t) => types.includes(t))
            );
            return comp ? { long: comp.longText, short: comp.shortText } : null;
        };

        const streetNumber = get(['street_number'])?.long ?? '';
        const route       = get(['route'])?.long ?? '';

        // City: prefer locality, fall back to sublocality or county
        const city =
            get(['locality'])?.long ??
            get(['sublocality_level_1', 'sublocality'])?.long ??
            get(['administrative_area_level_2'])?.long ??
            '';

        const state      = get(['administrative_area_level_1'])?.long ?? '';
        const country    = get(['country'])?.long ?? '';
        const postalCode = get(['postal_code'])?.long ?? '';

        // Format street address matching the selected language
        const languageSelect = form ? form.querySelector('[id$="_addressLanguage"]') : null;
        const isSpanish = languageSelect?.value === 'es';
        const streetAddress = isSpanish
            ? `${route} ${streetNumber}`.trim()
            : `${streetNumber} ${route}`.trim();

        // place.location may be undefined on some API responses; handle both
        // LatLng instances (.lat() method) and plain literals (.lat property)
        const loc = place.location;
        const lat = loc ? (typeof loc.lat === 'function' ? loc.lat() : loc.lat) : null;
        const lng = loc ? (typeof loc.lng === 'function' ? loc.lng() : loc.lng) : null;

        return {
            streetAddress,
            city,
            state,
            country,
            postalCode,
            lat,
            lng,
            placeId: place.id ?? '',
        };
    }

    showAddressError(referenceElement) {
        let confirmDiv = referenceElement.parentElement.querySelector('.address-confirmation');

        if (!confirmDiv) {
            confirmDiv = document.createElement('div');
            referenceElement.after(confirmDiv);
        }

        confirmDiv.className = 'address-confirmation alert alert-danger mt-2';
        confirmDiv.textContent = 'Could not load address details. Please try selecting again.';
    }

    showAddressConfirmation(formattedAddress, referenceElement) {
        let confirmDiv = referenceElement.parentElement.querySelector('.address-confirmation');

        if (!confirmDiv) {
            confirmDiv = document.createElement('div');
            confirmDiv.className = 'address-confirmation alert alert-success mt-2';
            referenceElement.after(confirmDiv);
        }

        const strong = document.createElement('strong');
        strong.textContent = '\u2713 Address selected: ';
        const small = document.createElement('small');
        small.textContent = formattedAddress;

        confirmDiv.replaceChildren(strong, small);
    }
}
