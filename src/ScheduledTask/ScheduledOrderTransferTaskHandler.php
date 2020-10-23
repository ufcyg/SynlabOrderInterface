<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SynlabOrderInterface\Core\Api\OrderInterfaceController;


class ScheduledOrderTransferTaskHandler extends ScheduledTaskHandler
{
    /** @var EntityRepositoryInterface $productRepository */
    private $productRepository;
    /** @var EntityRepositoryInterface $orderRepository */
    private $orderRepository;
    /** @var EntityRepositoryInterface $orderDeliveryAddressRepository */                              
    private $orderDeliveryAddressRepository;
    /** @var EntityRepositoryInterface $lineItemsRepository */
    private $lineItemsRepository;
    /** @var EntityRepositoryInterface $productsRepository */                                
    private $productsRepository;
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var OrderInterfaceController $interfaceController */
    private $interfaceController;
    
    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, OrderInterfaceController $interfaceController)
    {
        $this->interfaceController = $interfaceController;
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [ ScheduledOrderTransferTask::class ];
    }

    public function run(): void
    {
        $this->interfaceController->submitOrders(Context::createDefaultContext());
    }    
}