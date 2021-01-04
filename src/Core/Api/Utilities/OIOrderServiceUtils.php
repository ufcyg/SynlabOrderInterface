<?php declare(strict_types=1);
namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\ParameterBag;

class OIOrderServiceUtils
{
    /** @var OrderService $orderService */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    //process, complete, cancel, reopen
    public function updateOrderStatus(OrderEntity $order, string $entityID, $transition)
    {   
        $stateName = $order->getStateMachineState()->getTechnicalName();
        switch ($stateName)
        {
            case "open":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'process') == 0))
                {
                    return false;
                }
            break;

            case "in_progress":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'complete') == 0))
                {
                    return false;
                }
            break;

            case "completed": 
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;

            case "cancelled":
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;
        }
        $this->orderService->orderStateTransition($entityID, $transition, new ParameterBag([]),Context::createDefaultContext());
        return true;
    }

    //ship, ship_partially, retour, retour_partially, cancel, reopen
    public function updateOrderDeliveryStatus(OrderDeliveryEntity $orderDelivery,string $entityID, string $transition): bool
    {
        switch ($orderDelivery->getStateMachineState()->getTechnicalName())
        {
            case "open":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'ship') == 0 || strcmp($transition,'ship_partially') == 0))
                {
                    return false;
                }
            break;

            case "shipped":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'retour') == 0 || strcmp($transition,'retour_partially') == 0))
                {
                    return false;
                }
            break;

            case "shipped_partially": 
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'retour') == 0 || strcmp($transition,'retour_partially') == 0 || strcmp($transition,'ship') == 0))
                {
                    return false;
                }
            break;

            case "refunded_partially":
                return false;
            break;

            case "returned":
                return false;
            break;

            case "cancelled":
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;
        }
        $this->orderService->orderDeliveryStateTransition($entityID, $transition, new ParameterBag([]),Context::createDefaultContext());
        return true;
    }
}