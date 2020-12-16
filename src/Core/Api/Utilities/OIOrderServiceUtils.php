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
    public function updateOrderStatus(string $entityID, $transition)
    {   
        

        $this->orderService->orderStateTransition($entityID, $transition, new ParameterBag([]),Context::createDefaultContext());
    }
    ///state transitions
    //process, complete, cancel, reopen
    public function orderStateIsReopenable(OrderEntity $order): bool
    {
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName) {
            case 'Open':
                return false;
            case 'In progress':
                return false;
        }
        return true;
    }
    public function orderStateIsProcessable(OrderEntity $order): bool
    {
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName) {
            case 'In progress':
                return false;
            case 'Done':
                return false;
            case 'Cancelled':
                return false;
        }
        return true;
    }
    public function orderStateIsCompletable(OrderEntity $order): bool
    {
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName) {
            case 'Open':
                return false;
            case 'Done':
                return false;
            case 'Cancelled':
                return false;
        }
        return true;
    }
    public function orderStateIsCancelable(OrderEntity $order): bool
    {
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName) {
            case 'Done':
                return false;
        }
        return true;
    }

    //ship, ship_partially, retour, retour_partially, cancel, reopen
    public function updateOrderDeliveryStatus(OrderDeliveryEntity $orderDelivery,string $entityID, string $transition): bool
    {
        switch ($orderDelivery->getStateMachineState()->getName())
        {
            case "Open":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'ship') == 0 || strcmp($transition,'ship_partially') == 0))
                {
                    return false;
                }
            break;
            case "Shipped":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'retour') == 0 || strcmp($transition,'retour_partially') == 0))
                {
                    return false;
                }
            break;
            case "Shipped (partially)": 
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'retour') == 0 || strcmp($transition,'retour_partially') == 0 || strcmp($transition,'ship') == 0))
                {
                    return false;
                }
            break;
            case "Returned (partially)":
                return false;
            break;
            case "Returned":
                return false;
            break;
            case "Cancelled":
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