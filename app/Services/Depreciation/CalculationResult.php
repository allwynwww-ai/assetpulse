<?php

namespace App\Services\Depreciation;

final class CalculationResult
{
    public float $amount = 0;

    public int $days = 0;

    public ?float $rate = null;

    public float $openingAccumulated = 0;

    public float $closingAccumulated = 0;

    public float $openingWdv = 0;

    public float $closingWdv = 0;

    public ?string $skipReason = null;

    public array $details = [];

    public function __construct(public string $method) {}

    public function skip(string $reason): self
    {
        $this->skipReason = $reason;
        $this->amount = 0;

        return $this;
    }

    public function finish(float $accBefore, float $cost, int $precision = 2): self
    {
        $this->amount = round($this->amount, $precision);
        $this->openingAccumulated = round($accBefore, 2);
        $this->closingAccumulated = round($accBefore + $this->amount, 2);
        $this->openingWdv = round($cost - $accBefore, 2);
        $this->closingWdv = round($cost - $this->closingAccumulated, 2);

        return $this;
    }
}
