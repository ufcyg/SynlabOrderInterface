<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderTransferTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'synlab.scheduled_order_transfer_task';
    }

    public static function getDefaultInterval(): int
    {
        return 10; // daily
    }
}