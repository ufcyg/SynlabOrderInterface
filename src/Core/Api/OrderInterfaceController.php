<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;


use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
    // /** @var string */
    // private $todaysFolderPath;
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
        // $this->todaysFolderPath;
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
     * @Route("/api/v{version}/_action/synlab-order-interface/pullRMWA", name="api.custom.synlab_order_interface.pullRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function pullRMWA(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WA');

        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWA", name="api.custom.synlab_order_interface.checkRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkRMWA(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for($i = 2; $i < count($files)-1; $i++)
            {
                $filename = $files[$i];
                $filenameContents = explode('_',$filename);

                /** @var Criteria $criteria */
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('orderNumber', $filenameContents[3]));
                /** @var EntityRepositoryInterface $orderRepositoryContainer */
                $orderRepositoryContainer = $this->repositoryContainer->getOrderRepository();
                

                if($filenameContents[1] === 'STATUS') // status of order data procession
                {
                    /** @var EntitySearchResult $entities */
                    $orderEntitiy = $orderRepositoryContainer->search($criteria, $context);
                    /** @var OrderEntity $order */
                    $order = $orderEntitiy->first();

                    if($order == null)
                    {
                        //TODO major error (received answer for not existant order)
                        continue;
                    }
                    switch ($filenameContents[2])
                    {
                        case '003': // order cannot be changed (already packed, shipped, cancelled)
                            //TODO
                        break;
                        case '005': // cannot be cancelled because never created
                            //TODO
                        break;
                        case '006': // cannot be cancelled because already processed or cancelled
                            //TODO
                        break;
                        case '007': // order changed
                            //TODO
                        break;
                        case '009': // minor error in order
                            //TODO
                        break;
                        case '010': // order sucessfully imported to rieck LFS
                            if($this->oiOrderServiceUtils->orderStateIsProcessable($order))
                            {
                                $this->oiOrderServiceUtils->updateOrderStatus($order->getId(), 'process');
                            }
                        break;
                        case '040': // order packaging started, order cannot be changed anymore
                            if($this->oiOrderServiceUtils->orderStateIsCompletable($order))
                            {
                                $this->oiOrderServiceUtils->updateOrderStatus($order->getId(), 'complete');
                            }
                        break;
                        case '999': // major error (file doesn't meet the expectations, e.g. unfitting fieldlengths, fieldformats, missing necessary fields)
                            //TODO
                        break;
                        default:
                        break;
                    }
                    continue;
                }
                else if ($filenameContents[1] === 'WAK') // Packende / end of packing
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                }
                else if ($filenameContents[1] === 'STORNO') // cancellation(confirmation) by rieck
                {

                }
                else if ($filenameContents[1] === 'VLE') // packages loaded
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                }
                
            }
        } 
        return new Response('',Response::HTTP_NO_CONTENT);
    }







    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullRMWE", name="api.custom.synlab_order_interface.pullRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function pullRMWE(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WE');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullArticleError", name="api.custom.synlab_order_interface.pullArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function pullArticleError(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Artikel_Error');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Artikel_Error');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullBestand", name="api.custom.synlab_order_interface.pullBestand", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function pullBestand(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Bestand');
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/submitArticlebase", name="api.custom.synlab_order_interface.submitArticlebase", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function submitArticlebase(Context $context): Response
    { 
        $products = $this->oiUtils->getProducts($this->repositoryContainer->getProductsRepository(), $context);

        $csvString = '';
        foreach ($products as $product)
        {
            $csvString = $this->csvFactory->generateArticlebase($csvString, $product, $context);
        }
        $articlebasePath = $this->oiUtils->createTodaysFolderPath('Articlebase');
        $splitPath = explode('/',$articlebasePath);
        if (!file_exists($articlebasePath)) {
            mkdir($articlebasePath, 0777, true);
        }   
        $filename = $articlebasePath . '/' . $this->companyID . '.' . 'Artikelstamm-' . $splitPath[6] . '.csv';
        file_put_contents($filename, $csvString);

        $this->sendFile($filename, "/Artikel" . "/artikelstamm" . $this->oiUtils->createShortDateFromString('now') . ".csv");
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
        $folderPath = $this->oiUtils->createTodaysFolderPath('SubmittedOrders/');
        /** @var OrderEntity $order */
        foreach($entities as $orderID => $order)
        {
            if(!$this->oiOrderServiceUtils->orderStateIsProcessable($order))
            {
                continue;
            }
            
            
            // init exportVar
            $exportData = [];
            /** @var string $orderID */
            $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data

            $orderNumber = $order->getOrderNumber();
            $folderPath = $folderPath . '/' . $orderNumber . '/';
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }

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
            /** @var OrderLineItemEntity $product */
            foreach($orderedProducts as $product)
            {
                if ($product->getIdentifier() === "INTERNAL_DISCOUNT")
                {
                    continue;
                }
                array_push($exportData, $product);
                $orderContent = $this->csvFactory->generateDetails($exportData, $orderNumber, $i, $orderContent, $context);   
                $i++;
            }
            
            $fileContent = $this->csvFactory->generateHeader($exportData, $orderNumber, $fileContent, $orderCustomerEntity->getCustomerId(), $context);
            $fileContent = $fileContent . $orderContent;
            $filePath = $folderPath . $this->companyID . '-' . $orderNumber . '-order.csv';
            file_put_contents($filePath,$fileContent);
            
            $this->sendFile($filePath, "/WA" . "/waavis" . $orderNumber . ".csv");
        }
    }
    private function sendFile(string $filePath, string $destinationPath)
    {
        $this->sftpController->pushFile($filePath, $destinationPath);
    }
    
}