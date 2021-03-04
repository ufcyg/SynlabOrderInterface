<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderProcessRMWATask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'synlab.scheduled_order_process_rmwa';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5minutes
    }
}