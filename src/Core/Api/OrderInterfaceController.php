<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderInterfaceController extends AbstractController
{
    /** @var EntityRepositoryInterface $orderRepository */
    private $orderRepository;
    /** @var EntityRepositoryInterface $orderDeliveryAddressRepository */
    private $orderDeliveryAddressRepository;
    /** @var EntityRepositoryInterface $lineItems */
    private $lineItemsRepository;
    /** @var PropertyAccess $propertyAccessor */
    private $properyAccessor;

    public function __construct(EntityRepositoryInterface $orderRepository, 
                                EntityRepositoryInterface $orderDeliveryAddressRepository, 
                                EntityRepositoryInterface $lineItemsRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->properyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/chill", name="api.custom.synlab_order_interface.chill", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function chill(Context $context): Response
    {
        $this->writeFile($context,$this->orderRepository);
        
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    
    private function writeFile(Context $context)
    {
        $orderCriteria = new Criteria(); // date definition missing TODO
        /**
         * @var EntitySearchResult $entities
         */
        $entities = $this->orderRepository->search($orderCriteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return;
        }

        $exportData = [];
        /**
         * @var OrderEntity $order
         */
        foreach($entities as $order){

            /** @var string $orderID */
            $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data
            
            // deliveryaddress
            $deliveryAddressArray = $this->getDeliveryAddress($orderID);
            $orderedProducts = $this->getOrderedProducts($orderID);

            $exportData[] = [
                'Date' => $order->getOrderDateTime()->date,
                'Customer name' => $this->properyAccessor->getValue($deliveryAddressArray, '[firstName]') . ' ' . $this->properyAccessor->getValue($deliveryAddressArray, '[lastName]'),
                'DeliveryAddress' => $this->properyAccessor->getValue($deliveryAddressArray, '[zipCode]') . ' ' . $this->properyAccessor->getValue($deliveryAddressArray, '[city]') . ' ' . $this->properyAccessor->getValue($deliveryAddressArray, '[street]')
            ];
            $i = 0;
            foreach($orderedProducts as $product)
            {
                $exportData[] = $product;
            }

        }

        $fileContent = $this->toCSVString($exportData);
        file_put_contents('testfileOrders.csv',$fileContent);
    }

    private function getOrderedProducts(string $orderID): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId',$orderID));
        /** @var EntitySearchResult $lineItemEntity */
        $lineItemEntity = $this->lineItemsRepository->search($criteria,Context::createDefaultContext());

        $lineItemArray = [];
        // for($i = 0; $i < count($lineItemEntity); $i++)
        // {
        //     $lineItemArray[$i] = 
        // }
        $i = 0;
        foreach($lineItemEntity as $lineItem)
        {
            $lineItemArray[$i] = $lineItem;
            $i++;
        }

        return $lineItemArray;
    }

    private function getDeliveryAddress(string $orderID): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId',$orderID));
        /** @var EntitySearchResult $addressEntity */
        $addressEntity = $this->orderDeliveryAddressRepository->search($criteria,Context::createDefaultContext());

        /** @var OrderAddressEntity $deliverAddressEntity */
        $deliverAddressEntity;
        if (count($addressEntity) === 1)
        {
            $deliverAddressEntity = $addressEntity->first();
        }
        else
        {
            $deliverAddressEntity = $addressEntity->last();
        }
        $addressArray = array(
            'firstName' => $deliverAddressEntity->getFirstName(),
            'lastName' => $deliverAddressEntity->getLastName(),
            'zipCode' => $deliverAddressEntity->getZipcode(),
            'city' => $deliverAddressEntity->getCity(),
            'street' => $deliverAddressEntity->getStreet()
        );
        return $addressArray;
    }

    /**
    * Expects an array of associative arrays
    * Takes the first entry and creates a header string
    * Iterates through every entry and creates a column for it in the string
    * @var array $associativeArray
    * @return string $csvString
    */
    private function toCSVString(array $associativeArray): string
    {
        $csvString = '';
        $csvString .= implode(';', array_keys($associativeArray[0])) . "\n";

        foreach($associativeArray as $entry){
            $csvString .= implode(';', $entry) . "\n";
        }
        return $csvString;
    }
}