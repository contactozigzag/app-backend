<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Address;
use App\Service\AddressGeocoder;
use App\Service\GoogleMapsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddressGeocoderTest extends TestCase
{
    private MockObject $googleMapsService;

    private AddressGeocoder $addressGeocoder;

    protected function setUp(): void
    {
        $this->googleMapsService = $this->createMock(GoogleMapsService::class);
        $this->addressGeocoder = new AddressGeocoder($this->googleMapsService);
    }

    #[Test]
    public function it_creates_address_entity_from_plain_text(): void
    {
        $plainTextAddress = '123 Main Street, New York, NY 10001, USA';

        $mockComponents = [
            'street_address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'New York',
            'country' => 'United States',
            'country_code' => 'US',
            'postal_code' => '10001',
            'lat' => 40.7484405,
            'lng' => -73.9856644,
            'place_id' => 'ChIJAbCdEFBZwokRmVu5vBQNzgk',
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, null, 'number_first')
            ->willReturn($mockComponents);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress);

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('123 Main Street', $result->getStreetAddress());
        $this->assertSame('New York', $result->getCity());
        $this->assertSame('New York', $result->getState());
        $this->assertSame('United States', $result->getCountry());
        $this->assertSame('10001', $result->getPostalCode());
        $this->assertSame('40.7484405', $result->getLatitude());
        $this->assertSame('-73.9856644', $result->getLongitude());
        $this->assertSame('ChIJAbCdEFBZwokRmVu5vBQNzgk', $result->getPlaceId());
    }

    #[Test]
    public function it_returns_null_when_geocoding_fails(): void
    {
        $plainTextAddress = 'Invalid Address XYZ123';

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, null, 'number_first')
            ->willReturn(null);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress);

        $this->assertNotInstanceOf(Address::class, $result);
    }

    #[Test]
    public function it_handles_empty_address_components_gracefully(): void
    {
        $plainTextAddress = 'Partial Address';

        $mockComponents = [
            'street_address' => '',
            'city' => 'Unknown City',
            'state' => '',
            'country' => 'Unknown Country',
            'country_code' => '',
            'postal_code' => '',
            'lat' => 0.0,
            'lng' => 0.0,
            'place_id' => 'some_place_id',
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, null, 'number_first')
            ->willReturn($mockComponents);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress);

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('', $result->getStreetAddress());
        $this->assertSame('Unknown City', $result->getCity());
        $this->assertSame('', $result->getState());
        $this->assertSame('Unknown Country', $result->getCountry());
        $this->assertSame('', $result->getPostalCode());
        $this->assertSame('0', $result->getLatitude());
        $this->assertSame('0', $result->getLongitude());
    }

    #[Test]
    public function it_converts_float_coordinates_to_string(): void
    {
        $plainTextAddress = 'Test Address';

        $mockComponents = [
            'street_address' => '456 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'country_code' => 'US',
            'postal_code' => '12345',
            'lat' => 51.5074,
            'lng' => -0.1278,
            'place_id' => 'test_place_id',
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, null, 'number_first')
            ->willReturn($mockComponents);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress);

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('51.5074', $result->getLatitude());
        $this->assertSame('-0.1278', $result->getLongitude());
    }

    #[Test]
    public function it_creates_spanish_address_with_street_first_format(): void
    {
        $plainTextAddress = 'Avenida Benavídez 1632, Buenos Aires, Argentina';

        $mockComponents = [
            'street_address' => 'Avenida Benavídez 1632',
            'city' => 'Buenos Aires',
            'state' => 'Buenos Aires',
            'country' => 'Argentina',
            'country_code' => 'AR',
            'postal_code' => 'B1630',
            'lat' => -34.6037,
            'lng' => -58.3816,
            'place_id' => 'ChIJtest',
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, 'es', 'street_first')
            ->willReturn($mockComponents);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress, 'es');

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('Avenida Benavídez 1632', $result->getStreetAddress());
        $this->assertSame('Buenos Aires', $result->getCity());
        $this->assertSame('Argentina', $result->getCountry());
    }

    #[Test]
    public function it_auto_detects_spanish_speaking_country_and_reformats_address(): void
    {
        $plainTextAddress = 'Calle Principal 456, Madrid, Spain';

        // First call with default format
        $mockComponentsFirstCall = [
            'street_address' => '456 Calle Principal',
            'city' => 'Madrid',
            'state' => 'Madrid',
            'country' => 'Spain',
            'country_code' => 'ES',
            'postal_code' => '28001',
            'lat' => 40.4168,
            'lng' => -3.7038,
            'place_id' => 'ChIJtest',
        ];

        // Second call with street_first format
        $mockComponentsSecondCall = [
            'street_address' => 'Calle Principal 456',
            'city' => 'Madrid',
            'state' => 'Madrid',
            'country' => 'Spain',
            'country_code' => 'ES',
            'postal_code' => '28001',
            'lat' => 40.4168,
            'lng' => -3.7038,
            'place_id' => 'ChIJtest',
        ];

        $this->googleMapsService
            ->expects($this->exactly(2))
            ->method('parseAddressComponents')
            ->willReturnCallback(function (string $address, ?string $language, string $format) use ($mockComponentsFirstCall, $mockComponentsSecondCall): ?array {
                if ($language === null && $format === 'number_first') {
                    return $mockComponentsFirstCall;
                }

                if ($language === 'es' && $format === 'street_first') {
                    return $mockComponentsSecondCall;
                }

                return null;
            });

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress);

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('Calle Principal 456', $result->getStreetAddress());
    }

    #[Test]
    public function it_uses_english_format_for_non_spanish_countries(): void
    {
        $plainTextAddress = '10 Downing Street, London, UK';

        $mockComponents = [
            'street_address' => '10 Downing Street',
            'city' => 'London',
            'state' => 'England',
            'country' => 'United Kingdom',
            'country_code' => 'GB',
            'postal_code' => 'SW1A 2AA',
            'lat' => 51.5034,
            'lng' => -0.1276,
            'place_id' => 'ChIJtest',
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('parseAddressComponents')
            ->with($plainTextAddress, 'en', 'number_first')
            ->willReturn($mockComponents);

        $result = $this->addressGeocoder->createFromPlainText($plainTextAddress, 'en');

        $this->assertInstanceOf(Address::class, $result);
        $this->assertSame('10 Downing Street', $result->getStreetAddress());
        $this->assertSame('London', $result->getCity());
    }
}
