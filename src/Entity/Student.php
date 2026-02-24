<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use App\Enum\EducationalLevel;
use App\Enum\Gender;
use App\Enum\Grade;
use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ApiResource]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{8,10}$/', message: 'Identification number must be 8 to 10 digits.')]
    private ?string $identificationNumber = null;

    #[ORM\Column(nullable: true, enumType: Gender::class)]
    private ?Gender $gender = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $birthday = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $medicalHistory = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $emergencyContactNumber = null;

    #[ORM\Column(nullable: true, enumType: EducationalLevel::class)]
    private ?EducationalLevel $educationalLevel = null;

    #[ORM\Column(nullable: true, enumType: Grade::class)]
    private ?Grade $grade = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'students')]
    #[ORM\JoinTable(name: 'student_parent')]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    private Collection $parents;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getIdentificationNumber(): ?string
    {
        return $this->identificationNumber;
    }

    public function setIdentificationNumber(string $identificationNumber): static
    {
        $this->identificationNumber = $identificationNumber;

        return $this;
    }

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(?Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getBirthday(): ?DateTimeImmutable
    {
        return $this->birthday;
    }

    public function setBirthday(?DateTimeImmutable $birthday): static
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getMedicalHistory(): ?string
    {
        return $this->medicalHistory;
    }

    public function setMedicalHistory(?string $medicalHistory): static
    {
        $this->medicalHistory = $medicalHistory;

        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): static
    {
        $this->additionalInfo = $additionalInfo;

        return $this;
    }

    public function getEmergencyContact(): ?string
    {
        return $this->emergencyContact;
    }

    public function setEmergencyContact(?string $emergencyContact): static
    {
        $this->emergencyContact = $emergencyContact;

        return $this;
    }

    public function getEmergencyContactNumber(): ?string
    {
        return $this->emergencyContactNumber;
    }

    public function setEmergencyContactNumber(?string $emergencyContactNumber): static
    {
        $this->emergencyContactNumber = $emergencyContactNumber;

        return $this;
    }

    public function getEducationalLevel(): ?EducationalLevel
    {
        return $this->educationalLevel;
    }

    public function setEducationalLevel(?EducationalLevel $educationalLevel): static
    {
        $this->educationalLevel = $educationalLevel;

        return $this;
    }

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    #[Assert\Callback]
    public function validateEducationalLevelAndGrade(ExecutionContextInterface $context): void
    {
        if (! $this->educationalLevel instanceof EducationalLevel || ! $this->grade instanceof Grade) {
            return;
        }

        $allowedGrades = match ($this->educationalLevel) {
            EducationalLevel::Kindergarten => [Grade::One, Grade::Two, Grade::Three, Grade::Four, Grade::Five],
            EducationalLevel::ElementarySchool => [Grade::One, Grade::Two, Grade::Three, Grade::Four, Grade::Five, Grade::Six],
            EducationalLevel::HighSchool => [Grade::One, Grade::Two, Grade::Three, Grade::Four, Grade::Five],
        };

        if (! in_array($this->grade, $allowedGrades, true)) {
            $context->buildViolation('Grade does not match the selected educational level.')
                ->atPath('grade')
                ->addViolation();
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function getParents(): Collection
    {
        return $this->parents;
    }

    public function addParent(User $parent): static
    {
        if (! $this->parents->contains($parent)) {
            $this->parents->add($parent);
        }

        return $this;
    }

    public function removeParent(User $parent): static
    {
        $this->parents->removeElement($parent);

        return $this;
    }
}
