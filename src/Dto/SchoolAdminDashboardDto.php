<?php

namespace App\Dto;

class SchoolAdminDashboardDto
{
    public function __construct(
        public readonly array $statistics,
        public readonly array $activeRoutes,
        public readonly array $driverStatuses,
        public readonly array $recentAlerts,
        public readonly array $todayMetrics,
    ) {
    }
}
