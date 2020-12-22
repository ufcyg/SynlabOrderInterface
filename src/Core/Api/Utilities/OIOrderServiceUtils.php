<?php declare(strict_types=1);
/** 
 * 
 * This function needs refactoring since it currently uses a fair amount of hard coded values that will break when another language is implemented and used
 * 
*/
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
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName)
        {
            case "Open":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'process') == 0))
                {
                    return false;
                }
            break;
            case "Offen":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'process') == 0))
                {
                    return false;
                }
            break;

            case "In progress":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'complete') == 0))
                {
                    return false;
                }
            break;
            case "In Bearbeitung":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'complete') == 0))
                {
                    return false;
                }
            break;

            case "Done": 
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;
            case "Abgeschlossen": 
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;

            case "Cancelled":
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;
            case "Abgebrochen":
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
        switch ($orderDelivery->getStateMachineState()->getName())
        {
            case "Open":
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'ship') == 0 || strcmp($transition,'ship_partially') == 0))
                {
                    return false;
                }
            break;
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
            case "Versandt":
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
            case "Teilweise versandt": 
                if(!(strcmp($transition,'cancel') == 0 || strcmp($transition,'retour') == 0 || strcmp($transition,'retour_partially') == 0 || strcmp($transition,'ship') == 0))
                {
                    return false;
                }
            break;

            case "Returned (partially)":
                return false;
            break;
            case "Teilretour":
                return false;
            break;

            case "Returned":
                return false;
            break;
            case "Retour":
                return false;
            break;

            case "Cancelled":
                if(!(strcmp($transition,'reopen') == 0))
                {
                    return false;
                }
            break;
            case "Abgebrochen":
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