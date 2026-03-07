# Address Geocoding in EasyAdmin for School Resource

## Overview

This feature enables automatic address geocoding when creating or editing schools through the EasyAdmin dashboard. Users enter a plain text address via a Google Places autocomplete widget, and the system automatically converts it to a structured `Address` entity with complete geocoded data.

## Architecture

### Components

1. **GoogleMapsService** (`src/Service/GoogleMapsService.php`)
   - Enhanced with `parseAddressComponents()` method
   - Calls Google Geocoding API
   - Parses response into structured address components
   - Returns: street address, city, state, country, postal code, coordinates, place ID

2. **AddressGeocoder** (`src/Service/AddressGeocoder.php`)
   - Dedicated service for converting plain text → Address entity
   - Uses GoogleMapsService internally
   - Returns populated Address object or null on failure
   - Provides `getAddressComponents()` for preview/validation

3. **SchoolCrudController** (`src/Controller/Admin/SchoolCrudController.php`)
   - Configures EasyAdmin fields for school management
   - Implements `persistEntity()` and `updateEntity()` hooks
   - Handles geocoding logic before saving
   - Shows user-friendly error messages on geocoding failure

4. **Stimulus Controller** (`assets/controllers/google-address-autocomplete_controller.js`)
   - Attached directly to the `addressInput` form field via `data-controller` attribute
   - Dynamically loads the Google Maps JavaScript API
   - Replaces the text input with a `PlaceAutocompleteElement` web component
   - Keeps the original input as `type="hidden"` for form submission
   - Syncs the selected formatted address back to the hidden input on `gmp-select`

### Data Flow

```
User types in PlaceAutocompleteElement widget
        ↓
gmp-select event fires on place selection
        ↓
place.fetchFields() → gets formattedAddress
        ↓
Hidden <input> value set to formattedAddress
        ↓
Form submitted → SchoolCrudController::persistEntity()
        ↓
AddressGeocoder::createFromPlainText()
        ↓
GoogleMapsService::parseAddressComponents()
        ↓
Google Geocoding API
        ↓
Address Entity (with all fields populated)
        ↓
Database Persistence
```

## Usage

### Creating a School

1. Navigate to Admin Dashboard → Schools → Create
2. Enter school name
3. Select address language (English or Spanish)
4. Start typing in the "Address" field — Google Places suggestions appear
5. Select a suggestion from the dropdown (or type a full address manually)
6. Click "Create"
7. System geocodes and saves the address

### Editing a School

1. Navigate to Admin Dashboard → Schools → Edit
2. Type a new address into the autocomplete widget and select a suggestion
3. Click "Save changes"
4. System geocodes the new address and updates the record

### Error Handling

- If geocoding fails, a flash message is shown: "Could not geocode the address: '{address}'. Please verify the address and try again."
- The form is not submitted, allowing the user to correct the address
- No partial data is saved

## Google Places Autocomplete Setup

### How It Works

The `addressInput` field has `data-controller="google-address-autocomplete"` and `data-google-address-autocomplete-api-key-value` set directly via `setFormTypeOption('attr', ...)`. When the Stimulus controller connects:

1. It dynamically loads the Google Maps JS API (`libraries=places`)
2. Creates a `google.maps.places.PlaceAutocompleteElement` web component
3. Inserts it before the original `<input>`, which is then set to `type="hidden"`
4. On `gmp-select`, calls `place.fetchFields()` and writes `formattedAddress` into the hidden input

If the user types an address without selecting a suggestion, the text in the `PlaceAutocompleteElement`'s internal input is synced to the hidden field on form submit as a fallback (the backend geocodes any plain text address anyway).

### Loading Stimulus in EasyAdmin

EasyAdmin has its own layout that does not include the main application's importmap. Three files wire this together:

**`assets/admin.js`** — dedicated entrypoint for admin:
```js
import './stimulus_bootstrap.js';
```

**`importmap.php`** — registered as a second entrypoint:
```php
'admin' => [
    'path' => './assets/admin.js',
    'entrypoint' => true,
],
```

**`templates/bundles/EasyAdminBundle/layout.html.twig`** — injects the admin importmap into EasyAdmin's layout:
```twig
{% extends '@!EasyAdmin/layout.html.twig' %}
{% block importmap %}
    {{ parent() }}
    {{ importmap('admin') }}
{% endblock %}
```

### Field Configuration

```php
TextField::new('addressInput', 'Address')
    ->setRequired(true)
    ->setHelp('Enter the full address. It will be automatically geocoded.')
    ->setFormTypeOption('mapped', false)
    ->onlyOnForms()
    ->setFormTypeOption('attr', [
        'placeholder' => 'e.g., 123 Main Street, New York, NY 10001',
        'data-controller' => 'google-address-autocomplete',
        'data-google-address-autocomplete-api-key-value' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? '',
    ]),
```

> **Why `attr` and not `setTemplatePath()`?**
> `setTemplatePath()` only applies to read-only rendering (index/detail pages). Since `addressInput` uses `onlyOnForms()`, the template would never be rendered. Stimulus data attributes must be placed on the input element itself via `setFormTypeOption('attr', ...)`.

### Address Display Fields (read-only, index/detail only)

```php
TextField::new('address.streetAddress', 'Street Address')->hideOnForm()
TextField::new('address.city', 'City')->hideOnForm()
TextField::new('address.state', 'State')->hideOnForm()
TextField::new('address.country', 'Country')->hideOnForm()
TextField::new('address.postalCode', 'Postal Code')->hideOnForm()
TextField::new('address.latitude', 'Latitude')->hideOnForm()
TextField::new('address.longitude', 'Longitude')->hideOnForm()
```

## Testing

### Unit Tests
**File**: `tests/Unit/Service/AddressGeocoderTest.php`

Covers:
- ✅ Successful address geocoding
- ✅ Failed geocoding (invalid address)
- ✅ Empty address components handling
- ✅ Float to string coordinate conversion
- ✅ Preview/validation functionality

Run:
```bash
make test tests/Unit/Service/AddressGeocoderTest.php
```

### Functional Tests
**File**: `tests/Functional/Controller/Admin/SchoolCrudControllerTest.php`

Covers:
- ✅ Creating school with valid address
- ✅ Error handling for invalid address
- ✅ Displaying school list with address details
- ✅ Updating school with new address
- ✅ Authorization (admin role required)

Run:
```bash
make test tests/Functional/Controller/Admin/SchoolCrudControllerTest.php
```

### E2E Tests
**File**: `tests/E2E/Admin/SchoolAddressAutocompleteTest.php`

Requires Chrome/Chromium, a valid `GOOGLE_MAPS_API_KEY`, and network access.

Covers:
- ✅ `PlaceAutocompleteElement` is rendered and functional
- ✅ Hidden input has correct Stimulus data attributes
- ✅ Address confirmation appears after place selection
- ✅ Spanish addresses are supported
- ✅ Graceful degradation when API is unavailable

Run:
```bash
make test tests/E2E/Admin/SchoolAddressAutocompleteTest.php
```

## Configuration

### Environment Variables

Required:
- `GOOGLE_MAPS_API_KEY`: Google Maps API key with Geocoding API and Places API (New) enabled

### Google Maps API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable APIs:
   - Geocoding API
   - Places API (New) — required for `PlaceAutocompleteElement`
4. Create credentials → API Key
5. Restrict API key:
   - Application restrictions: HTTP referrers (websites)
   - API restrictions: Geocoding API, Places API
6. Add key to `.env.local`:
```bash
GOOGLE_MAPS_API_KEY=your_api_key_here
```

## Troubleshooting

### "Could not geocode the address"
- Verify address format is complete and correct
- Check Google Maps API key is valid and has Geocoding API enabled
- Check API quota limits haven't been exceeded
- Try a more specific address (include city, state, postal code)

### Autocomplete widget not appearing
- Verify `GOOGLE_MAPS_API_KEY` is set in the environment
- Check browser console for JavaScript errors
- Ensure Places API (New) is enabled in Google Cloud Console
- Confirm `templates/bundles/EasyAdminBundle/layout.html.twig` exists and overrides the `importmap` block
- Confirm `admin` is registered as an entrypoint in `importmap.php`

### Stimulus controller not connecting
- Run `php bin/console debug:asset-map` and verify `admin.js` and the controller file appear
- Run `php bin/console assets:install` then check that `public/assets/` contains the controller JS
- Ensure `assets/admin.js` imports `./stimulus_bootstrap.js`

## Code Quality

All code passes:
- ✅ PHPStan level 9 (strict static analysis)
- ✅ ECS (Easy Coding Standard - PSR-12)
- ✅ Rector (PHP 8.5 compatibility)
- ✅ PHPUnit 12 (100% test coverage for service layer)

## References

- [Google Places Migration Guide](https://developers.google.com/maps/documentation/javascript/places-migration-overview)
- [PlaceAutocompleteElement docs](https://developers.google.com/maps/documentation/javascript/places-migration-autocomplete)
- [Google Geocoding API Documentation](https://developers.google.com/maps/documentation/geocoding)
- [EasyAdmin Documentation](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
- [Stimulus Documentation](https://stimulus.hotwired.dev/)
- [Symfony Asset Mapper](https://symfony.com/doc/current/frontend/asset_mapper.html)
