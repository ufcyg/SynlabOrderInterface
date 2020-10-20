<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use DateTime;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
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
    
    public function __construct(EntityRepositoryInterface $scheduledTaskRepository,
                                EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [ ScheduledOrderTransferTask::class ];
    }

    public function run(): void
    {
        // $this->tester();
        $interfaceController = new OrderInterfaceController($this->orderRepository, $this->orderDeliveryAddressRepository, $this->lineItemsRepository, $this->productsRepository);
        $interfaceController->writeOrders(Context::createDefaultContext());
    }

    private function tester()
    {
        $timeStamp = new DateTime();
        $timeStamp = $timeStamp->format('d-m-Y');
        $this->todaysFolderPath = '../custom/plugins/SynlabOrderInterface/SubmittedOrders/' . $timeStamp;
        if (!file_exists($this->todaysFolderPath)) {
            mkdir($this->todaysFolderPath, 0777, true);
        }
    }
}