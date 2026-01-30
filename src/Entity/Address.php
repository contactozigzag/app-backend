<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\AddressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ApiResource]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Groups(['user:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:write'])]
    private ?string $streetAddress = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:write'])]
    private ?string $state = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:write'])]
    private ?string $country = null;

    #[ORM\Column(length: 10)]
    #[Groups(['user:write'])]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8)]
    #[Groups(['user:write'])]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8)]
    #[Groups(['user:write'])]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:write'])]
    private ?string $placeId = null;

    #[ORM\Column]
    #[Groups(['user:write'])]
    private ?bool $isDefault = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(string $streetAddress): static
    {
        $this->streetAddress = $streetAddress;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getPlaceId(): ?string
    {
        return $this->placeId;
    }

    public function setPlaceId(?string $placeId): static
    {
        $this->placeId = $placeId;

        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
