<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use SynlabOrderInterface\Core\Api\OrderInterfaceController;


class ScheduledOrderProcessRMWATaskHandler extends ScheduledTaskHandler
{
    /** @var OrderInterfaceController $interfaceController */
    private $interfaceController;
    
    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, OrderInterfaceController $interfaceController)
    {
        $this->interfaceController = $interfaceController;
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [ ScheduledOrderProcessRMWATask::class ];
    }

    public function run(): void
    {
        // $this->interfaceController->pullRMWA(Context::createDefaultContext());
    }    
}