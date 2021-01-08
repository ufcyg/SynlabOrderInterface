<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SynlabOrderInterface\Core\Api\Utilities\CSVFactory;
use SynlabOrderInterface\Core\Api\Utilities\SFTPController;
use SynlabOrderInterface\Core\Api\Utilities\OIOrderServiceUtils;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceRepositoryContainer;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceUtils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceMailServiceHelper;

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
    /** @var OrderInterfaceMailServiceHelper $oimailserviceHelper */
    private $oimailserviceHelper;
    public function __construct(SystemConfigService $systemConfigService,
                                OrderInterfaceRepositoryContainer $repositoryContainer,
                                OrderInterfaceUtils $oiUtils,
                                OIOrderServiceUtils $oiOrderServiceUtils,
                                OrderInterfaceMailServiceHelper $oimailserviceHelper)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repositoryContainer = $repositoryContainer;
        $this->oiUtils = $oiUtils;
        $this->oiOrderServiceUtils = $oiOrderServiceUtils;
        $this->oimailserviceHelper = $oimailserviceHelper;

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
     * @Route("/api/v{version}/_action/synlab-order-interface/submitOrders", name="api.custom.synlab_order_interface.submitOrders", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function submitOrders(Context $context): Response
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context); //TODO set to true 

        if(count($entities) === 0){
            return new Response('',Response::HTTP_NO_CONTENT);
        }
        /** @var OrderEntity $order */
        foreach($entities as $orderID => $order)
        {
            if(strcmp($$order->getStateMachineState()->getTechnicalName(),'open') != 0)
            {
                continue;
            }
            $orderNumber = $order->getOrderNumber();
            $fileContent = $this->generateFileContent($order, $orderNumber, $context);

            $filePath = $this->writeFile($orderNumber, $fileContent);
            
            $this->sendFile($filePath, "/WA" . "/waavis" . $orderNumber . ".csv");
        }
        
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    private function generateFileContent(OrderEntity $order, string $orderNumber, $context): string
    {
        // init exportVar
        $exportData = [];
        /** @var string $orderID */
        $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data

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
        return $fileContent;
    }
    private function writeFile(string $orderNumber, $fileContent): string
    {
        $folderPath = $this->oiUtils->createTodaysFolderPath('SubmittedOrders/');
        $folderPath = $folderPath . '/' . $orderNumber . '/';
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
        $filePath = $folderPath . $this->companyID . '-' . $orderNumber . '-order.csv';
        file_put_contents($filePath,$fileContent);
        return $filePath;
    }
    private function sendFile(string $filePath, string $destinationPath)
    {
        $this->sftpController->pushFile($filePath, $destinationPath);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullRMWA", name="api.custom.synlab_order_interface.pullRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function pullRMWA(Context $context):Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        
        $this->sftpController->pullFile($path,'RM_WA', $this, $context, $response, 'checkRMWA');
        return $response;
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWA", name="api.custom.synlab_order_interface.checkRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkRMWA(Context $context): Response
    {
        $deleteFilesWhenFinished = $this->systemConfigService->get('SynlabOrderInterface.config.deleteFilesAfterEvaluation');
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for($i = 2; $i < count($files); $i++)
            {
                $filename = $files[$i];
                $filenameContents = explode('_',$filename);

                if($filenameContents[1] === 'STATUS') // status of order data procession
                {
                    /** @var OrderEntity $order */
                    $order = $this->getOrder('orderNumber', $filenameContents[3],$context);

                    if($order == null)
                    {
                        $deleteFilesWhenFinished = false;
                        $this->sendErrorNotification('MAJOR ERROR','Major error occured. Received reply for non existant order. Received filename: ' . $filename);
                        continue;
                    }
                    switch ($filenameContents[2])
                    {
                        case '003': // order cannot be changed (already packed, shipped, cancelled)
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 003','Status 003 for order ' . $order->getOrderNumber());
                            //TODO
                        break;
                        case '005': // cannot be cancelled because never created
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 005','Status 005 for order ' . $order->getOrderNumber());
                        break;
                        case '006': // cannot be cancelled because already processed or cancelled
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 006','Status 006 for order ' . $order->getOrderNumber());
                        break;
                        case '007': // order changed
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 007','Status 007 for order ' . $order->getOrderNumber());
                        break;
                        case '009': // minor error in order
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 009','Status 009 for order ' . $order->getOrderNumber());
                        break;
                        case '010': // order sucessfully imported to rieck LFS
                            $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'process');
                        break;
                        case '040': // order packaging started, order cannot be changed anymore
                            $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'complete');
                        break;
                        case '999': // major error (file doesn't meet the expectations, e.g. unfitting fieldlengths, fieldformats, missing necessary fields)
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('MAJOR ERROR','Major error occured. One or more submitted files did not meet expectations and been rejected. Received filename: ' . $filename);
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
                    /** @var OrderEntity $order */
                    $order = $this->getOrder('orderNumber', $filenameContents[2],$context);

                    /** @var string $orderDelivery */
                    $orderDeliveryID = $this->oiUtils->getDeliveryEntityID($order->getId(),$context);
                    
                    /** @var Criteria $criteria */
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('id', $orderDeliveryID));
                    /** @var EntityRepositoryInterface $orderRepositoryContainer */
                    $orderDeliveryRepositoryContainer = $this->repositoryContainer->getOrderDeliveryRepository();
                    /** @var EntitySearchResult $entities */
                    $orderDeliveryEntities = $orderDeliveryRepositoryContainer->search($criteria, $context);
                    /** @var OrderDeliveryEntity $orderDelivery */
                    $orderDelivery = $orderDeliveryEntities->first();
                    
                    
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                    $trackingnumbers = array();
                    for ($j = 1; $j < count($fileContentsByLine)-1; $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);

                        if(!$this->oiUtils->trackingnumberAtPositionExistsCk($lineContents[2], $lineContents[9], $context))
                        {
                            $trackingData[] = [
                                'id' => Uuid::randomHex(),
                                'orderId' => strval($order->getId()),
                                'service' => $headContents[4],
                                'position' => $lineContents[2],
                                'trackingNumber' => $lineContents[9]
                            ];
                            array_push($trackingnumbers,$lineContents[9]);
                        }
                    }
                    $stateChanged = $this->oiOrderServiceUtils->updateOrderDeliveryStatus($orderDelivery, $orderDeliveryID, 'ship');
                    if($stateChanged)
                    {
                        $this->repositoryContainer->getParcelTracking()->create($trackingData, $context);

                        $this->oiUtils->updateTrackingNumbers($orderDeliveryID, $trackingnumbers, $context);
                    }
                }
            }
        } 
        if ($deleteFilesWhenFinished)
        {
            $dir = $path;
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it,
                 RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) 
            {
                if ($file->isDir())
                {
                    rmdir($file->getRealPath());
                }
                else 
                {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    private function getOrder($identifier, $filenameContents, $context): OrderEntity
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $filenameContents));
        /** @var EntityRepositoryInterface $orderRepositoryContainer */
        $orderRepositoryContainer = $this->repositoryContainer->getOrderRepository();
        /** @var EntitySearchResult $entities */
        $orderEntities = $orderRepositoryContainer->search($criteria, $context);
        /** @var OrderEntity $order */
        $order = $orderEntities->first();
        return $order;
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
        $this->sftpController->pullFile($path,'RM_WE', $this, $context, $response, 'checkRMWE');
        return $this->checkRMWE($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWE", name="api.custom.synlab_order_interface.checkRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkRMWE(Context $context): Response
    {
        $deleteFilesWhenFinished = true;
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for ($i = 2; $i < count($files); $i++) {
                $filename = $files[$i];
                $filenameContents = explode('_',$filename);

                if($filenameContents[1] == 'STATUS')
                {
                    switch($filenameContents[2])
                    {
                        case '001': //WEAvis couldn't be created
                            $this->sendErrorNotification('RM_WE Status 001','Status 001 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '005': //WEAvis cannot be cancelled due to being not existant, already processed or cancelled
                            $this->sendErrorNotification('RM_WE Status 005','Status 005 "WEAvis cannot be cancelled due to being not existant, already processed or cancelled" for order ' . $filenameContents[3]);
                        break;
                        case '007': //WEAvis change processed
                            // $this->sendErrorNotification('RM_WE Status 007','Status 007 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '009': //WEAvis could not be processed, errors inside WEAvis
                            $this->sendErrorNotification('RM_WE Status 009','Status 009 "WEAvis could not be processed, errors inside WEAvis" for order ' . $filenameContents[3]);
                        break;
                        case '010': //WEAvis processed
                            // $this->sendErrorNotification('RM_WE Status 010','Status 010 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '999': //WEAvis message could not be processed, out of specification
                            $this->sendErrorNotification('RM_WE Status 999','Status 999 "WEAvis message could not be processed, out of specification" for order ' . $filenameContents[3]);
                        break;
                        default:
                            $this->sendErrorNotification('RM_WE Status 999','Status 999 "WEAvis message could not be processed, out of specification" for order ' . $filenameContents[3]);
                        break;
                    }
                }
                else if($filenameContents[1] == 'ERROR')
                {
                    
                }
                else if($filenameContents[1] == 'WKE')
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                    for ($j = 1; $j < count($fileContentsByLine)-1; $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);
                        $articleNumber = $lineContents[5];
                        $amount = $lineContents[6];
                        $amountAvailable = $lineContents[7];
                        $amountDamaged = $lineContents[8];
                        $amountClarification = $lineContents[9];
                        $amountPostProcessing = $lineContents[10];
                        $amountOther = $lineContents[11];
                        
                        $this->updateProduct($articleNumber, $amount, $amountAvailable);

                        if($amount != $amountAvailable)
                        {
                            $this->sendErrorNotification('RM_WE WKE','Damaged or otherwise unusable products for file ' . $filename . ' check back with logistics partner to keep stock information up to date.');
                        }
                    }
                }
                
            }
        }

        return new Response('',Response::HTTP_NO_CONTENT);
    }
    private function getProduct(string $articleNumber): ProductEntity
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));

        $searchResult = $productRepository->search($criteria,Context::createDefaultContext());
        return $searchResult->first();
    }
    private function updateProduct(string $articleNumber, $stockAddition, $availableStockAddition)
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');

        /** @var ProductEntity $productEntity */
        $productEntity = $this->getProduct($articleNumber);

        $currentStock = $productEntity->getStock();
        $currentStockAvailable = $productEntity->getAvailableStock();

        $newStockValue = $currentStock + intval($availableStockAddition);
        // $newAvailableStockValue = $currentStockAvailable + intval($availableStockAddition);

        $productRepository->update(
            [
                [ 'id' => $productEntity->getId(), 'stock' => $newStockValue ],
                // [ 'id' => $productEntity->getId(), 'availableStock' => $newAvailableStockValue ], //value is write protected
            ],
            Context::createDefaultContext()
        );
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
        $this->sftpController->pullFile($path,'Artikel_Error', $this, $context, $response, 'checkArticleError');
        return $this->checkArticleError($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkArticleError", name="api.custom.synlab_order_interface.checkArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkArticleError(Context $context): Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Artikel_Error') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            if (count($files) > 2)
            {
                $filecontents = '';
                for ($i = 2; $i < count($files); $i++) {
                    $filename = $files[$i];
                    $filecontents = $filename . '|||' . $filecontents . file_get_contents($path . $filename);
                }
                $this->sendErrorNotification('Error: Article base','Error reported by logistics partner, submitted article base contains errors check logfile for further informations. Logfile: ' . $filecontents);
            }
        }
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
        $this->sftpController->pullFile($path,'Bestand', $this, $context, $response, 'checkBestand');
        return $this->checkBestand($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkBestand", name="api.custom.synlab_order_interface.checkBestand", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkBestand(Context $context): Response
    {
        $deleteFilesWhenFinished = true;
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for ($i = 2; $i < count($files); $i++) {
                $filename = $files[$i];
                $filenameContents = explode('_', $filename);

                switch ($filenameContents[0])
                    {
                        case 'BESTAND': // Daily report of current available items
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents);       
                            foreach ($fileContentsByLine as $contentLine)
                            {
                                $lineContents = explode(';', $contentLine);
                                // $this->updateProduct($lineContents[1], $lineContents[4], $lineContents[5], $context);
                                //TODO COMPARE
                            }
                            
                            //TODO caclulate discrepancy, email notification admin
                            
                        break;
                        case 'BS+': // addition of currently available items (items lost but found, etc.)
                            //TODO
                        break;
                        case 'BS-': // subtraction of currently available items (lost, stolen, destroyed, etc.)
                            //TODO
                        break;
                        default:
                        break;
                    }
            }
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }



    //// helper

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/modifyOrdersState", name="api.custom.synlab_order_interface.modifyOrdersState", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function modifyOrdersState(Context $context)
    {//reopen,process,complete,cancel
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($this->repositoryContainer->getOrderRepository(), false, $context);

        if(count($entities) === 0){
            return;
        }
        /** @var OrderEntity $order */
        foreach ($entities as $orderID => $order) 
        {
            $this->oiOrderServiceUtils->updateOrderStatus($order, $orderID, 'complete');
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/orderInterfaceHelper", name="api.custom.synlab_order_interface.orderInterfaceHelper", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function orderInterfaceHelper(Context $context)
    {

        // $articleNumber = 'SCG204';
        // $stockAddition = 10000;
        // $availableStockAddition = 5000;

        // /** @var EntityRepositoryInterface $productRepository */
        // $productRepository = $this->container->get('product.repository');

        // /** @var ProductEntity $productEntity */
        // $productEntity = $this->getProduct($articleNumber);

        // $currentStock = $productEntity->getStock();
        // $currentStockAvailable = $productEntity->getAvailableStock();

        // $newStockValue = $currentStock + intval($stockAddition);
        // $newAvailableStockValue = $currentStockAvailable + intval($availableStockAddition);

        // $productRepository->update(
        //     [
        //         [ 'id' => $productEntity->getId(), 'stock' => $newStockValue ],
        //         // [ 'id' => $productEntity->getId(), 'availableStock' => $newAvailableStockValue ],
        //     ],
        //     Context::createDefaultContext()
        // );
        $this->sendErrorNotification('TestError','This is a test error message.');
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    private function sendErrorNotification(string $errorSubject, string $errorMessage)
    {
        $notificationSalesChannel = $this->systemConfigService->get('SynlabOrderInterface.config.fallbackSaleschannelNotification');

        $recipientList = $this->systemConfigService->get('SynlabOrderInterface.config.errorNotificationRecipients');
        $recipientData = explode(';', $recipientList);

        for ($i = 0; $i< count($recipientData); $i +=2 )
        {
            $recipientName = $recipientData[$i];
            $recipientAddress = $recipientData[$i+1];

            $mailCheck = explode('@', $recipientAddress);
            if(count($mailCheck) != 2)
            {
                continue;
            }
            $this->oimailserviceHelper->sendMyMail($recipientAddress, $recipientName, $notificationSalesChannel, $errorSubject, $errorMessage);
        }
    }
    private function deleteFiles($dir)
    {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
                RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) 
        {
            if ($file->isDir())
            {
                rmdir($file->getRealPath());
            }
            else 
            {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}