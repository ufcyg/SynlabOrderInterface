<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use Shopware\Core\Framework\Context;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use SynlabOrderInterface\Core\Api\Utilities\OIOrderServiceUtils;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceRepositoryContainer;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceUtils;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderInterfaceController extends AbstractController
{
    
    /** @var string */
    private $todaysFolderPath;
    /** @var CSVFactory $csvFactory */
    private $csvFactory;
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    /** @var OrderInterfaceRepositoryContainer $repositoryContainer */
    private $repositoryContainer;
    /** @var OrderInterfaceUtils $oiUtils */
    private $oiUtils;
    /** @var string $companyID */
    private $companyID;
    /** @var OIOrderServiceUtils $oiOrderServiceUtils */
    private $oiOrderServiceUtils;
    public function __construct(SystemConfigService $systemConfigService,
                                OrderInterfaceRepositoryContainer $repositoryContainer,
                                OrderInterfaceUtils $oiUtils,
                                OIOrderServiceUtils $oiOrderServiceUtils)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repositoryContainer = $repositoryContainer;
        $this->oiUtils = $oiUtils;
        $this->oiOrderServiceUtils = $oiOrderServiceUtils;
        $this->companyID = $this->systemConfigService->get('SynlabOrderInterface.config.logisticsCustomerID');
        $this->csvFactory = new CSVFactory($this->companyID);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/submitOrders", name="api.custom.synlab_order_interface.chill", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function submitOrders(Context $context): Response
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
        $products = $this->oiUtils->getProducts();

        foreach ($products as $product)
        {
            $articleBase = $this->csvFactory->generateArticlebase();
            file_put_contents($this->todaysFolderPath . '/' . $this->companyID . '-' . 'synlabArticlebase.csv', $articleBase);
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/reopenOrders", name="api.custom.synlab_order_interface.reopenOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function reopenOrders(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->oiOrderServiceUtils->orderStateIsReopenable($order))
            {
                $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'reopen');
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
        $entities = $this->oiUtils->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->oiOrderServiceUtils->orderStateIsProcessable($order))
            {
                $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'process');
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
        $entities = $this->oiUtils->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->oiOrderServiceUtils->orderStateIsCompletable($order))
            {
                $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'complete');
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
        $entities = $this->oiUtils->getOrderEntities($context, false);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            if($this->oiOrderServiceUtils->orderStateIsCancelable($order))
            {
                $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'cancel');
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    
    private function writeFile(Context $context)
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($context, true);

        if(count($entities) === 0){
            return;
        }
        $this->oiUtils->createDateFolder();
        $exportData = [];

        /** @var OrderEntity $order */
        foreach($entities as $orderID => $order)
        {
            if(!$this->oiOrderServiceUtils->orderStateIsProcessable($order))
            {
                continue;
            }
            $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'process');
            $this->oiOrderServiceUtils->updateOrderStatus($orderID, 'complete');
            
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
            $exportData = $this->oiUtils->getDeliveryAddress($orderID, $eMailAddress);// ordered products
            $orderedProducts = $this->oiUtils->getOrderedProducts($orderID);
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
}