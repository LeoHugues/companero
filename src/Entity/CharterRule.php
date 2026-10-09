<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A rule of the household's charter: how we live well together, nothing to do with the app
 * ("je fais ma vaisselle quand j'ai fini"). Everyone may write them; each member agrees to them once.
 */
#[ORM\Entity]
class CharterRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'charterRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Household $household;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Quelle est la règle ?')]
    #[Assert\Length(max: 200)]
    private string $text;

    /** Why it matters, unfolded on demand. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $why = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    public function __construct(Household $household, string $text, ?string $why = null)
    {
        $this->household = $household;
        $this->text = $text;
        $this->setWhy($why);
        $household->addCharterRule($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getWhy(): ?string
    {
        return $this->why;
    }

    public function setWhy(?string $why): void
    {
        $this->why = null === $why || '' === trim($why) ? null : $why;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
