<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class HealthServiceTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'synlab.health_service_task';
    }

    public static function getDefaultInterval(): int
    {
        return 3600; // 60minutes
    }
}