<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Customer::class, cascade: ['persist'])]
    private ?Customer $customer = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Admin::class, cascade: ['persist'])]
    private ?Admin $admin = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Staff::class, cascade: ['persist'])]
    private ?Staff $staff = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    /**
     * @var Collection<int, RentalRequest>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: RentalRequest::class)]
    private Collection $rentalRequests;

    /**
     * @var Collection<int, ActivityLog>
     */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'user')]
    private Collection $activityLogs;

    public function __construct()
    {
        $this->rentalRequests = new ArrayCollection();
        $this->action = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // To be removed in Symfony 8
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;
        if ($customer !== null && $customer->getUser() !== $this) {
            $customer->setUser($this);
        }
        return $this;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): static
    {
        $this->admin = $admin;
        if ($admin !== null && $admin->getUser() !== $this) {
            $admin->setUser($this);
        }
        return $this;
    }

    public function getStaff(): ?Staff
    {
        return $this->staff;
    }

    public function setStaff(?Staff $staff): static
    {
        $this->staff = $staff;
        if ($staff !== null && $staff->getUser() !== $this) {
            $staff->setUser($this);
        }
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    /**
     * @return Collection<int, RentalRequest>
     */
    public function getRentalRequests(): Collection
    {
        return $this->rentalRequests;
    }

    public function addRentalRequest(RentalRequest $rentalRequest): static
    {
        if (!$this->rentalRequests->contains($rentalRequest)) {
            $this->rentalRequests->add($rentalRequest);
            $rentalRequest->setCustomer($this);
        }
        return $this;
    }

    public function removeRentalRequest(RentalRequest $rentalRequest): static
    {
        if ($this->rentalRequests->removeElement($rentalRequest)) {
            if ($rentalRequest->getCustomer() === $this) {
                $rentalRequest->setCustomer(null);
            }
        }
        return $this;
    }

    public function getPrimaryRole(): string
    {
        if ($this->admin !== null) {
            return 'ROLE_ADMIN';
        }
        if ($this->staff !== null) {
            return 'ROLE_STAFF';
        }
        if ($this->customer !== null) {
            return 'ROLE_CUSTOMER';
        }
        return 'ROLE_USER';
    }

    public function getAssociatedEntity(): Admin|Staff|Customer|null
    {
        return $this->admin ?? $this->staff ?? $this->customer;
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getAction(): Collection
    {
        return $this->action;
    }

    public function addAction(ActivityLog $action): static
    {
        if (!$this->action->contains($action)) {
            $this->action->add($action);
            $action->setUser($this);
        }

        return $this;
    }

   /**
 * @return Collection<int, ActivityLog>
 */
public function getActivityLogs(): Collection
{
    return $this->activityLogs;
}

public function addActivityLog(ActivityLog $activityLog): static
{
    if (!$this->activityLogs->contains($activityLog)) {
        $this->activityLogs->add($activityLog);
        $activityLog->setUser($this);
    }

    return $this;
}

public function removeActivityLog(ActivityLog $activityLog): static
{
    if ($this->activityLogs->removeElement($activityLog)) {
        // set the owning side to null (unless already changed)
        if ($activityLog->getUser() === $this) {
            $activityLog->setUser(null);
        }
    }

    return $this;
}
}