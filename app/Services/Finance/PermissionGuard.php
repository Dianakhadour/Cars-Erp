<?php
 

namespace App\Services\Finance;

use App\DTO\Finance\AccessScope;

class PermissionGuard
{
    public function canViewReport(AccessScope $scope, string $reportKey): bool
    {
        // For now, allow all reports - implement RBAC later
        return true;
    }

    public function requireViewReport(AccessScope $scope, string $reportKey): void
    {
        if (!$this->canViewReport($scope, $reportKey)) {
            throw new \DomainException('Access denied to report: ' . $reportKey);
        }
    }

    public function intersectBranches(array $allowed, array $requested): array
    {
        return empty($requested) ? $allowed : array_values(array_intersect($allowed, $requested));
    }
}