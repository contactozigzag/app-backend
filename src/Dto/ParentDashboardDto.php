<?php

namespace App\Dto;

class ParentDashboardDto
{
    public function __construct(
        public readonly array $children,
        public readonly array $activeRoutes,
        public readonly array $todayAttendance,
        public readonly array $upcomingRoutes,
    ) {
    }
}
