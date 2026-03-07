<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;

/**
 * Service to geocode plain text addresses into Address entities
 */
class AddressGeocoder
{
    /**
     * Spanish-speaking countries that use street-first address format
     */
    private const array STREET_FIRST_COUNTRIES = [
        'AR', // Argentina
        'ES', // Spain
        'MX', // Mexico
        'CL', // Chile
        'CO', // Colombia
        'PE', // Peru
        'VE', // Venezuela
        'UY', // Uruguay
        'PY', // Paraguay
        'BO', // Bolivia
        'EC', // Ecuador
        'GT', // Guatemala
        'CU', // Cuba
        'DO', // Dominican Republic
        'HN', // Honduras
        'NI', // Nicaragua
        'SV', // El Salvador
        'CR', // Costa Rica
        'PA', // Panama
    ];

    public function __construct(
        private readonly GoogleMapsService $googleMapsService
    ) {
    }

    /**
     * Create an Address entity from a plain text address string
     *
     * @param string $plainTextAddress The address to geocode
     * @param string|null $language Language code (e.g., 'en', 'es') for geocoding
     * @return Address|null Returns null if geocoding fails
     */
    public function createFromPlainText(string $plainTextAddress, ?string $language = null): ?Address
    {
        // Determine address format based on language or auto-detect from geocoding result
        $addressFormat = $language === 'es' ? 'street_first' : 'number_first';

        $components = $this->googleMapsService->parseAddressComponents(
            $plainTextAddress,
            $language,
            $addressFormat
        );

        if ($components === null) {
            return null;
        }

        // Auto-detect format based on country code if language wasn't specified
        $countryCodeValue = $components['country_code'];
        if ($language === null && $countryCodeValue !== '') {
            $countryCode = strtoupper($countryCodeValue);
            if (in_array($countryCode, self::STREET_FIRST_COUNTRIES, true)) {
                // Re-geocode with street_first format
                $components = $this->googleMapsService->parseAddressComponents(
                    $plainTextAddress,
                    'es',
                    'street_first'
                );

                if ($components === null) {
                    return null;
                }
            }
        }

        $address = new Address();
        $address->setStreetAddress($components['street_address']);
        $address->setCity($components['city']);
        $address->setState($components['state']);
        $address->setCountry($components['country']);
        $address->setPostalCode($components['postal_code']);
        $address->setLatitude((string) $components['lat']);
        $address->setLongitude((string) $components['lng']);
        $address->setPlaceId($components['place_id']);

        return $address;
    }
}
