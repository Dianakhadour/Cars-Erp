<?php 

namespace App\DTO\Finance;

class ReportResult
{
    public function __construct(
        public string $reportKey,
        public string $title,
        public array $data,
        public array $metadata = [],
        public ?string $generatedAt = null,
    ) {
        $this->generatedAt = $generatedAt ?? now()->toISOString();
    }
}