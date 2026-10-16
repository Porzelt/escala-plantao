<?php

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * Uma vaga de plantão: um turno numa data, com N posições a preencher.
 */
final readonly class Slot
{
    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    public function __construct(
        public string $date,          // Y-m-d
        public int $shiftTypeId,
        string $startsAt,             // H:i
        int $durationHours,
        public float $weight = 1.0,
        public int $positions = 1,
    ) {
        $this->start = new DateTimeImmutable("{$date} {$startsAt}");
        $this->end = $this->start->modify("+{$durationHours} hours");
    }
}
