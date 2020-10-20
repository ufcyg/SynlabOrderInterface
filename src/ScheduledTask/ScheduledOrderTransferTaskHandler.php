<?php declare(strict_types=1);

namespace SynlabOrderInterface\ScheduledTask;

use DateTime;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use SynlabOrderInterface\Core\Api\OrderInterfaceController;

class ScheduledOrderTransferTaskHandler extends ScheduledTaskHandler
{

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository)
    {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [ ScheduledOrderTransferTaskHandler::class ];
    }

    public function run(): void
    {
        $this->tester();
        // /** @var EntityRepositoryInterface $productRepository */
        // $productRepository = $this->container->get('product.repository');
        // /** @var EntityRepositoryInterface $orderRepository */
        // $orderRepository = $this->container->get('product.repository');
        // /** @var EntityRepositoryInterface $orderDeliveryAddressRepository */                              
        // $orderDeliveryAddressRepository = $this->container->get('product.repository');
        // /** @var EntityRepositoryInterface $lineItemsRepository */
        // $lineItemsRepository = $this->container->get('product.repository');
        // /** @var EntityRepositoryInterface $productsRepository */                                
        // $productsRepository = $this->container->get('product.repository');
        
        // $interfaceController = new OrderInterfaceController($orderRepository, $orderDeliveryAddressRepository, $lineItemsRepository, $productsRepository);
        // $interfaceController->writeOrders(Context::createDefaultContext());
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