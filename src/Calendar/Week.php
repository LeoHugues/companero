<?php

namespace App\Calendar;

/** A Monday-to-Sunday week, as a half-open interval [start, end). */
final readonly class Week
{
    private function __construct(
        public \DateTimeImmutable $start,
    ) {
    }

    public static function containing(\DateTimeImmutable $moment): self
    {
        return new self($moment->setTime(0, 0)->modify('monday this week'));
    }

    public function end(): \DateTimeImmutable
    {
        return $this->start->modify('+7 days');
    }

    public function contains(\DateTimeImmutable $moment): bool
    {
        return $moment >= $this->start && $moment < $this->end();
    }

    public function previous(): self
    {
        return new self($this->start->modify('-7 days'));
    }

    public function next(): self
    {
        return new self($this->start->modify('+7 days'));
    }

    public function number(): int
    {
        return (int) $this->start->format('W');
    }

    /** The date of the given ISO day (1 = Monday) within this week. */
    public function day(int $isoDay): \DateTimeImmutable
    {
        return $this->start->modify(\sprintf('+%d days', $isoDay - 1));
    }

    /** @return list<\DateTimeImmutable> */
    public function days(): array
    {
        return array_map($this->day(...), range(1, 7));
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start;
    }
}
