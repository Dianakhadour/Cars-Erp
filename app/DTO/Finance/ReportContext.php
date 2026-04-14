<?php
    
namespace App\DTO\Finance;

class ReportContext
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $asOf = null,
        public array $branchIds = [],
        public array $parameters = [],
    ) {}
}