<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;


use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SynlabOrderInterface\Core\Api\Utilities\CSVFactory;
use SynlabOrderInterface\Core\Api\Utilities\SFTPController;
use SynlabOrderInterface\Core\Api\Utilities\OIOrderServiceUtils;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceRepositoryContainer;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceUtils;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderInterfaceController extends AbstractController
{
    /** @var SFTPController $sftpController */
    private $sftpController;
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
        $this->csvFactory = new CSVFactory($this->companyID, $this->repositoryContainer, $this->oiUtils);
        $this->todaysFolderPath;
        $ipAddress = $this->systemConfigService->get('SynlabOrderInterface.config.ipAddress');
        $port = $this->systemConfigService->get('SynlabOrderInterface.config.port');
        $username = $this->systemConfigService->get('SynlabOrderInterface.config.ftpUserName');
        $password = $this->systemConfigService->get('SynlabOrderInterface.config.ftpPassword');
        $homeDirectory = $this->systemConfigService->get('SynlabOrderInterface.config.homeDirectory');

        $this->sftpController = new SFTPController($ipAddress, $port, $username, $password, $homeDirectory);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/submitOrders", name="api.custom.synlab_order_interface.submitOrders", methods={"POST"})
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
        $this->oiUtils->createDateFolder();
        $this->todaysFolderPath = $this->oiUtils->createTodaysFolderPath();        
        $products = $this->oiUtils->getProducts($this->repositoryContainer->getProductsRepository(), $context);

        $csvString = '';
        foreach ($products as $product)
        {
            $csvString = $this->csvFactory->generateArticlebase($csvString, $product, $context);
        }
        $splitPath = explode('/',$this->todaysFolderPath);
        $articlebasePath = $splitPath[0] . '/' . $splitPath[1] . '/' . $splitPath[2] . '/' . $splitPath[3] .  '/' . 'Articlebase/';
        if (!file_exists($articlebasePath)) {
            mkdir($articlebasePath, 0777, true);
        }   
        $filename = $articlebasePath . $this->companyID . '.' . 'Artikelstamm-' . $splitPath[5] . '.csv';
        file_put_contents($filename, $csvString);

        $this->sendFile($filename, "/Artikel" . "/artikelstamm" . $this->oiUtils->createDateFromString('now') . ".csv");
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
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context);

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
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context);

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
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context);

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
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context);

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
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context); //TODO set to true 

        if(count($entities) === 0){
            return;
        }
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

            $folderPath = '';
            $this->oiUtils->createOrderFolder($orderNumber,$folderPath);

            //customer eMail
            /** @var OrderCustomerEntity $orderCustomerEntity */
            $orderCustomerEntity = $order->getOrderCustomer();
            $eMailAddress = $orderCustomerEntity->getEmail();
            // deliveryaddress
            $exportData = $this->oiUtils->getDeliveryAddress($this->repositoryContainer->getOrderDeliveryAddressRepository(), $orderID, $eMailAddress, $context);// ordered products
            $orderedProducts = $this->oiUtils->getOrderedProducts($this->repositoryContainer->getLineItemsRepository(), $orderID, $context);
            
            $fileContent = '';
            $orderContent = '';
            $i = 0;
            foreach($orderedProducts as $product)
            {
                array_push($exportData, $product);
                $orderContent = $this->csvFactory->generateDetails($exportData, $orderNumber, $i, $orderContent, $context);   
                $i++;
            }
            
            $fileContent = $this->csvFactory->generateHeader($exportData, $orderNumber, $fileContent, $orderCustomerEntity->getCustomerId(), $context);
            $fileContent = $fileContent . $orderContent;
            $filePath = $folderPath . '/' . $orderNumber . '/' . $this->companyID . '-' . $orderNumber . '-order.csv';
            file_put_contents($filePath,$fileContent);
            
            $this->sendFile($filePath, "/WA" . "/waavis" . $orderNumber . ".csv");
        }
    }
    private function sendFile(string $filePath, string $destinationPath)
    {
        $this->sftpController->pushFile($filePath, $destinationPath);
    }
    
}