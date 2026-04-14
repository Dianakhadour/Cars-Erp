<?php
 

namespace App\DTO\Finance;

final class AccessScope
{
    public function __construct(
        public string $tenantId,
        public array $allowedBranchIds,
        public string $userId,
    ) {}
}