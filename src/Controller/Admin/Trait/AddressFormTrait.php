<?php

declare(strict_types=1);

namespace App\Controller\Admin\Trait;

use App\Contract\HasAddressInterface;
use App\Entity\Address;
use App\Service\AddressGeocoder;

/**
 * Provides reusable address geocoding logic for EasyAdmin CRUD controllers.
 *
 * Classes using this trait must inject AddressGeocoder via constructor and
 * expose it as $this->addressGeocoder. The entity being processed must
 * implement setAddress(Address $address).
 *
 * Usage:
 *   applyAddressFromRequest($entity, 'School')  // form key = 'School'
 *   applyAddressFromRequest($entity, 'User')    // form key = 'User'
 */
trait AddressFormTrait
{
    private readonly AddressGeocoder $addressGeocoder;

    /**
     * Resolves the address from the request and sets it on the entity via setAddress().
     * Returns false if geocoding failed — caller should abort persistence and flash an error.
     *
     * @param HasAddressInterface $entity   The entity to apply the address to
     * @param string              $formName The top-level form key in the request (e.g. 'School', 'User')
     */
    private function applyAddressFromRequest(HasAddressInterface $entity, string $formName): bool
    {
        $request = $this->getContext()?->getRequest();
        $formData = $request?->request->all($formName);

        if (! is_array($formData)) {
            return true;
        }

        $addressInput = isset($formData['addressInput']) && is_string($formData['addressInput'])
            ? $formData['addressInput']
            : '';

        if ($addressInput === '') {
            return true;
        }

        // When the user picked a place from the autocomplete widget, all geocoded data
        // is sent pre-parsed from the frontend — no server-side API call needed.
        $geocodedJson = isset($formData['addressGeocodedData']) && is_string($formData['addressGeocodedData'])
            ? $formData['addressGeocodedData']
            : '';

        if ($geocodedJson !== '') {
            $data = json_decode($geocodedJson, true);

            if (is_array($data) && isset($data['placeId']) && is_string($data['placeId']) && $data['placeId'] !== '') {
                $address = new Address();
                $address->setStreetAddress(is_string($data['streetAddress'] ?? null) ? $data['streetAddress'] : '');
                $address->setCity(is_string($data['city'] ?? null) ? $data['city'] : '');
                $address->setState(is_string($data['state'] ?? null) ? $data['state'] : '');
                $address->setCountry(is_string($data['country'] ?? null) ? $data['country'] : '');
                $address->setPostalCode(is_string($data['postalCode'] ?? null) ? $data['postalCode'] : '');
                $address->setLatitude(is_numeric($data['lat'] ?? null) ? (string) $data['lat'] : '');
                $address->setLongitude(is_numeric($data['lng'] ?? null) ? (string) $data['lng'] : '');
                $address->setPlaceId($data['placeId']);
                $entity->setAddress($address);

                return true;
            }
        }

        // Fallback: user typed an address manually without selecting a suggestion — geocode it.
        $addressLanguage = isset($formData['addressLanguage']) && is_string($formData['addressLanguage'])
            ? $formData['addressLanguage']
            : null;
        $language = in_array($addressLanguage, ['en', 'es'], true) ? $addressLanguage : null;

        $address = $this->addressGeocoder->createFromPlainText($addressInput, $language);

        if (! $address instanceof Address) {
            $this->addFlash(
                'danger',
                sprintf('Could not geocode the address: "%s". Please verify the address and try again.', $addressInput),
            );

            return false;
        }

        $entity->setAddress($address);

        return true;
    }
}
