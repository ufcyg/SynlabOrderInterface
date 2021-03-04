<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledOrderProcessArticleErrorTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'synlab.scheduled_order_process_article_error';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5minutes
    }
}