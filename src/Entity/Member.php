<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[UniqueEntity('email', message: 'Cette adresse est déjà utilisée.')]
class Member implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const GOAL_CHOICES = [15, 20, 25, 30];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'Comment tu t’appelles ?')]
    #[Assert\Length(max: 40)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Choice(choices: self::GOAL_CHOICES)]
    private int $weeklyGoal = 20;

    #[ORM\Column]
    private bool $notifyCleaningDay = true;

    #[ORM\Column]
    private bool $notifyOverdue = true;

    #[ORM\Column]
    private bool $notifyWeeklyReview = true;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct(Household $household, string $name, string $email, string $color, \DateTimeImmutable $joinedAt)
    {
        $this->household = $household;
        $this->name = $name;
        $this->email = mb_strtolower($email);
        $this->color = $color;
        $this->joinedAt = $joinedAt;
        $household->addMember($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInitial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getWeeklyGoal(): int
    {
        return $this->weeklyGoal;
    }

    public function setWeeklyGoal(int $weeklyGoal): void
    {
        $this->weeklyGoal = $weeklyGoal;
    }

    public function isNotifyCleaningDay(): bool
    {
        return $this->notifyCleaningDay;
    }

    public function setNotifyCleaningDay(bool $notify): void
    {
        $this->notifyCleaningDay = $notify;
    }

    public function isNotifyOverdue(): bool
    {
        return $this->notifyOverdue;
    }

    public function setNotifyOverdue(bool $notify): void
    {
        $this->notifyOverdue = $notify;
    }

    public function isNotifyWeeklyReview(): bool
    {
        return $this->notifyWeeklyReview;
    }

    public function setNotifyWeeklyReview(bool $notify): void
    {
        $this->notifyWeeklyReview = $notify;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function belongsTo(Household $household): bool
    {
        return $this->household === $household;
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        $data = (array) $this;
        // Only a hash of the password is kept in the session.
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
