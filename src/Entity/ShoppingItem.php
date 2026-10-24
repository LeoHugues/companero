<?php

namespace App\Entity;

use App\Repository\ShoppingItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Something the house runs out of: toilet paper, sponges, the light bulb that blew. One line per thing, kept
 * once bought: it is missing again in one tap, and what was bought lately is suggested first.
 */
#[ORM\Entity(repositoryClass: ShoppingItemRepository::class)]
class ShoppingItem
{
    public const NAME_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    #[Assert\NotBlank(message: 'Qu’est-ce qui manque ?')]
    #[Assert\Length(max: self::NAME_MAX_LENGTH)]
    private string $name;

    /** Since when it is missing; null while there is some. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $missingSince = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $noticedBy = null;

    /** None left at all (the toilet paper): at the top of the list. */
    #[ORM\Column(options: ['default' => false])]
    private bool $urgent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $boughtAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Member $boughtBy = null;

    public function __construct(Household $household, string $name)
    {
        $this->household = $household;
        $this->setName($name);
    }

    /** "  papier   toilette " is written "Papier toilette". */
    public static function tidy(string $name): string
    {
        return mb_ucfirst(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * What a name stands for, to tell that two are the same thing: "Éponges" is "éponge", "papiers toilette"
     * is "Papier toilette".
     */
    public static function keyOf(string $name): string
    {
        $name = str_replace(['œ', 'æ', '’'], ['oe', 'ae', "'"], mb_strtolower(self::tidy($name)));
        $name = (string) preg_replace('/\p{Mn}/u', '', (string) \Normalizer::normalize($name, \Normalizer::FORM_D));

        return (string) preg_replace('/(?<=\w{3})[sx]\b/u', '', $name);
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

    public function setName(string $name): void
    {
        $this->name = self::tidy($name);
    }

    public function isCalled(string $name): bool
    {
        return self::keyOf($this->name) === self::keyOf($name);
    }

    public function isMissing(): bool
    {
        return null !== $this->missingSince;
    }

    public function getMissingSince(): ?\DateTimeImmutable
    {
        return $this->missingSince;
    }

    public function getNoticedBy(): ?Member
    {
        return $this->noticedBy;
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    public function setUrgent(bool $urgent): void
    {
        $this->urgent = $urgent && $this->isMissing();
    }

    public function getBoughtAt(): ?\DateTimeImmutable
    {
        return $this->boughtAt;
    }

    public function getBoughtBy(): ?Member
    {
        return $this->boughtBy;
    }

    /**
     * "Il manque…": on the list from now on. Already there, it stays as it was noticed, and only becomes urgent if
     * it is said to be.
     *
     * @return bool false if it was already on the list
     */
    public function notice(Member $by, \DateTimeImmutable $at, bool $urgent = false): bool
    {
        if ($this->isMissing()) {
            $this->urgent = $this->urgent || $urgent;

            return false;
        }
        $this->missingSince = $at;
        $this->noticedBy = $by;
        $this->urgent = $urgent;

        return true;
    }

    public function buy(Member $by, \DateTimeImmutable $at): void
    {
        $this->boughtAt = $at;
        $this->boughtBy = $by;
        $this->forget();
    }

    /** Off the list without being bought: it was not needed after all. */
    public function forget(): void
    {
        $this->missingSince = null;
        $this->noticedBy = null;
        $this->urgent = false;
    }

    public function wasEverBought(): bool
    {
        return null !== $this->boughtAt;
    }
}
