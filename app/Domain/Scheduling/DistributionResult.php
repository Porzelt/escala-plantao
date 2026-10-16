<?php

namespace App\Domain\Scheduling;

final readonly class DistributionResult
{
    /**
     * @param  list<array{date: string, shift_type_id: int, user_id: int}>  $assignments
     * @param  list<array{date: string, shift_type_id: int}>  $unfilled
     * @param  array<int, float>  $loads  carga ponderada final por membro
     */
    public function __construct(
        public array $assignments,
        public array $unfilled,
        public array $loads,
    ) {}

    /** Diferença entre o membro mais e o menos carregado — métrica de justiça. */
    public function spread(): float
    {
        return $this->loads === [] ? 0.0 : max($this->loads) - min($this->loads);
    }
}
