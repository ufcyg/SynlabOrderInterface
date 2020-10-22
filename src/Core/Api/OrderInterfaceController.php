<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use DateInterval;
use DateTime;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Symfony\Component\HttpFoundation\ParameterBag;

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
    /** @var EntityRepositoryInterface $productsRepository */
    private $productsRepository;
    /** @var EntityRepositoryInterface $orderDeliveryRepository */
    private $orderDeliveryRepository;
    /** @var string */
    private $todaysFolderPath;
    /** @var CSVFactory $csvFactory */
    private $csvFactory;
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var string $companyID */
    private $companyID;
    /** @var OrderService $orderService */
    private $orderService;
    public function __construct(SystemConfigService $systemConfigService,
                                EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository,
                                EntityRepositoryInterface $orderDeliveryRepository,
                                OrderService $orderService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->orderService = $orderService;
        $this->companyID = $this->systemConfigService->get('SynlabOrderInterface.config.logisticsCustomerID');
        $this->csvFactory = new CSVFactory($this->companyID);
    }
    private function createDateFolder()
    {
        if($this->newOrdersCk())
        {
            $timeStamp = new DateTime();
            $timeStamp = $timeStamp->format('d-m-Y');
            $this->todaysFolderPath = '../custom/plugins/SynlabOrderInterface/SubmittedOrders/' . $timeStamp;
            if (!file_exists($this->todaysFolderPath)) {
                mkdir($this->todaysFolderPath, 0777, true);
            }
        }        
    }
    public function newOrdersCk(): bool
    {
        $criteria = new Criteria();
        $this->addCriteriaFilterDate($criteria);

        /** @var EntitySearchResult $entities */
        $entities = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return false;
        }
        return true;
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/writeOrders", name="api.custom.synlab_order_interface.chill", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function writeOrders(Context $context): Response
    {
        $this->writeFile($context);
        
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/submitArticlebase", name="api.custom.synlab_order_interface.submitArticlebase", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function submitArticlebase(Context $context): Response
    {
        $products = $this->getProducts();

        foreach ($products as $product)
        {
            $articleBase = $this->csvFactory->generateArticlebase();
            file_put_contents($this->todaysFolderPath . '/' . $this->companyID . '-' . 'synlabArticlebase.csv', $articleBase);
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    private function getProducts(): EntitySearchResult
    {
        $criteria = new Criteria();
        /**
         * @var EntitySearchResult
         */
        return $this->productsRepository->search($criteria, Context::createDefaultContext());
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/reopenOrders", name="api.custom.synlab_order_interface.reopenOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function reopenOrders(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->orderStateIsReopenable($order))
            {
                $this->updateOrderStatus($orderID, 'reopen');
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/processOrders", name="api.custom.synlab_order_interface.processOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function processOrders(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->orderStateIsProcessable($order))
            {
                $this->updateOrderStatus($orderID, 'process');
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/completeOrders", name="api.custom.synlab_order_interface.completeOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function completeOrders(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->orderStateIsCompletable($order))
            {
                $this->updateOrderStatus($orderID, 'complete');
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/cancelOrders", name="api.custom.synlab_order_interface.cancelOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function cancelOrders(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->orderStateIsCancelable($order))
            {
                $this->updateOrderStatus($orderID, 'cancel');
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    //process, complete, cancel, reopen
    private function orderStateIsReopenable(OrderEntity $order): bool
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
    private function orderStateIsProcessable(OrderEntity $order): bool
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
    private function orderStateIsCompletable(OrderEntity $order): bool
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
    private function orderStateIsCancelable(OrderEntity $order): bool
    {
        $stateName = $order->getStateMachineState()->getName();
        switch ($stateName) {
            case 'Done':
                return false;
        }
        return true;
    }

    private function getOrderEntities(Context $context, bool $applyFilter)
    {
        $criteria = $applyFilter ? $this->addCriteriaFilterDate(new Criteria()) : new Criteria();

        /** @var EntitySearchResult $entities */
        $entities = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return 0;
        }
        return $entities;
    }
    private function writeFile(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->getOrderEntities($context, true);

        if(count($entities) === 0){
            return;
        }
        $this->createDateFolder();
        $exportData = [];

        /** @var OrderEntity $order */
        foreach($entities as $orderID => $order)
        {
            if(!$this->orderStateIsProcessable($order))
            {
                continue;
            }
            $this->updateOrderStatus($orderID, 'process');
            $this->updateOrderStatus($orderID, 'complete');
            // $this->updateOrderDeliveryStatus($this->getDeliveryEntityID($orderID),'ship');
            // init exportVar
            $exportData = [];
            /** @var string $orderID */
            $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data
            $orderNumber = $order->getOrderNumber();

            if (!file_exists($this->todaysFolderPath . '/' . $orderNumber)) {
                mkdir($this->todaysFolderPath . '/' . $orderNumber, 0777, true);
            }

            //customer eMail
            /** @var OrderCustomerEntity $customerEntity */
            $customerEntity = $order->getOrderCustomer();
            $eMailAddress = $customerEntity->getEmail();
            // deliveryaddress
            $exportData = $this->getDeliveryAddress($orderID, $eMailAddress);// ordered products
            $orderedProducts = $this->getOrderedProducts($orderID);
            $i = 0;
            foreach($orderedProducts as $product)
            {
                array_push($exportData, $product);
                $fileContent = $this->csvFactory->generateDetails($exportData, $orderNumber, $i);
                file_put_contents($this->todaysFolderPath . '/' . $orderNumber . '/' . $this->companyID . '-' . $orderNumber . '-' . $product->getPosition() . '-details.csv',$fileContent);    
                $i++;
            }
            $fileContent = $this->csvFactory->generateHeader($exportData, $orderNumber);
            file_put_contents($this->todaysFolderPath . '/' . $orderNumber . '/' . $this->companyID . '-' . $orderNumber . '-header.csv',$fileContent);
        }
    }

    public function addCriteriaFilterDate(Criteria $criteria): Criteria
    {
        //comment this for all orders
        $yesterday = $this->createDateFromString('yesterday');

        $now = $this->createDateFromString('now');

        $criteria->addFilter(new RangeFilter('orderDate', [
            RangeFilter::GTE => $yesterday,
            RangeFilter::LTE => $now
        ]));
        //comment this for all orders
        return $criteria;
    }

    private function createDateFromString(string $daytime): string
    {
        $timeStamp = new DateTime();
        $timeStamp->add(DateInterval::createFromDateString($daytime));
        $timeStamp = $timeStamp->format('Y-m-d H:i:s.u');
        $timeStamp = substr($timeStamp, 0, strlen($timeStamp) - 3);

        return $timeStamp;
    }

    private function getOrderedProducts(string $orderID): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId',$orderID));
        /** @var EntitySearchResult $lineItemEntity */
        $lineItemEntity = $this->lineItemsRepository->search($criteria,Context::createDefaultContext());

        $lineItemArray = [];
        $i = 0;
        foreach($lineItemEntity as $lineItem)
        {
            $lineItemArray[$i] = $lineItem;
            $i++;
        }

        return $lineItemArray;
    }

    private function getDeliveryAddress(string $orderID, string $eMailAddress): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId',$orderID));
        /** @var EntitySearchResult $addressEntity */
        $addressEntity = $this->orderDeliveryAddressRepository->search($criteria,Context::createDefaultContext());

        /** @var OrderAddressEntity $deliverAddressEntity */
        $deliverAddressEntity;
        /** @var OrderAddressEntity $customerAddressEntity */
        $customerAddressEntity;
        if (count($addressEntity) === 1)
        {
            $customerAddressEntity = $addressEntity->first();
            $deliverAddressEntity = $addressEntity->first();
        }
        else
        {// if array is 2 long, the first entry is customer, 2nd entry is delivery address, i always want the delivery address to be the frist entry
            $customerAddressEntity = $addressEntity->first();
            $deliverAddressEntity = $addressEntity->last();
        }

        return $addressArray = array(
            'eMail' => $eMailAddress,
            'firstNameCustomer' => $customerAddressEntity->getFirstName(),
            'lastNameCustomer' => $customerAddressEntity->getLastName(),
            'zipCodeCustomer' => $customerAddressEntity->getZipcode(),
            'cityCustomer' => $customerAddressEntity->getCity(),
            'streetCustomer' => $customerAddressEntity->getStreet(),
            'firstNameDelivery' => $deliverAddressEntity->getFirstName(),
            'lastNameDelivery' => $deliverAddressEntity->getLastName(),
            'zipCodeDelivery' => $deliverAddressEntity->getZipcode(),
            'cityDelivery' => $deliverAddressEntity->getCity(),
            'streetDelivery' => $deliverAddressEntity->getStreet()
        );
    }
    //process, complete, cancel, reopen
    private function updateOrderStatus(string $entityID, $transition)
    {
        $this->orderService->orderStateTransition($entityID, $transition, new ParameterBag([]),Context::createDefaultContext());
    }

    private function getDeliveryEntityID(string $orderEntityID): string
    {
        $criteria = new Criteria();
        $entities = $this->orderDeliveryRepository->search($criteria, Context::createDefaultContext());

        /** @var OrderDeliveryEntity $orderDelivery */
        foreach($entities as $id => $orderDelivery)
        {
            if ($orderDelivery->getOrderId() === $orderEntityID)
            {
                return $orderDelivery->getId();
            }
        }
    }
    //ship, ship_partially, retour, retour_partially, cancel, reopen
    private function updateOrderDeliveryStatus(string $entityID, string $transition)
    {
        $this->orderService->orderDeliveryStateTransition($entityID, $transition, new ParameterBag([]),Context::createDefaultContext());
    }
}