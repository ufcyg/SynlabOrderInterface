<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api;

use ASControllingReport\Core\Api\ASControllingReportController;
use ASDispositionControl\Core\Content\DispoControlData\DispoControlDataEntity;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SynlabOrderInterface\Core\Api\Utilities\CSVFactory;
use SynlabOrderInterface\Core\Api\Utilities\SFTPController;
use SynlabOrderInterface\Core\Api\Utilities\OIOrderServiceUtils;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceRepositoryContainer;
use SynlabOrderInterface\Core\Api\Utilities\OrderInterfaceUtils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use ASMailService\Core\MailServiceHelper;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use SynlabOrderInterface\Core\Content\StockQS\OrderInterfaceStockQSEntity;

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
    /** @var MailServiceHelper $mailserviceHelper */
    private $mailserviceHelper;
    /** @var string $senderName */
    private $senderName;
    /** @var ASControllingReportController $controllingReportController */
    private $controllingReportController;
    public function __construct(SystemConfigService $systemConfigService,
                                OrderInterfaceRepositoryContainer $repositoryContainer,
                                OrderInterfaceUtils $oiUtils,
                                OIOrderServiceUtils $oiOrderServiceUtils,
                                MailServiceHelper $mailserviceHelper,
                                ASControllingReportController $controllingReportController)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repositoryContainer = $repositoryContainer;
        $this->oiUtils = $oiUtils;
        $oiUtils->setContainer($this->container);
        $this->oiOrderServiceUtils = $oiOrderServiceUtils;
        $this->mailserviceHelper = $mailserviceHelper;
        $this->senderName = 'Order Interface';
        $this->controllingReportController = $controllingReportController;

        $this->companyID = $this->systemConfigService->get('SynlabOrderInterface.config.logisticsCustomerID');
        $this->csvFactory = new CSVFactory($this->companyID, $this->repositoryContainer, $this->oiUtils);
        $ipAddress = $this->systemConfigService->get('SynlabOrderInterface.config.ipAddress');
        $port = $this->systemConfigService->get('SynlabOrderInterface.config.port');
        $username = $this->systemConfigService->get('SynlabOrderInterface.config.ftpUserName');
        $password = $this->systemConfigService->get('SynlabOrderInterface.config.ftpPassword');
        $homeDirectory = $this->systemConfigService->get('SynlabOrderInterface.config.homeDirectory');

        $this->sftpController = new SFTPController($ipAddress, $port, $username, $password, $homeDirectory);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/dummyRoute", name="api.custom.synlab_order_interface.dummyRoute", methods={"POST"})
     * @param Context $context;
     * @return Response
     * just my debug function called by insomnia, change what you want, it isn't used anywhere
     */
    public function dummyRoute(Context $context)
    {
        
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/processAnswers", name="api.custom.synlab_order_interface.processAnswers", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Checks for answers by logistics partner.
     */
    public function processAnswers(Context $context): ?Response
    {
        $response = $this->pullArticleError($context);
        $response = $this->pullRMWE($context);
        $response = $this->pullRMWA($context);
        $response = $this->pullBestand($context);
        return $response;
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/submitArticlebase", name="api.custom.synlab_order_interface.submitArticlebase", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Writes file with full articlebase local and transmits the file to the designated sFTP server.
     */
    public function submitArticlebase(Context $context): ?Response
    { 
        /** @var EntitySearchResult $products */
        $products = $this->oiUtils->getAllProducts($this->container->get('product.repository'), $context);

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
     * Checks for open orders and transmits them to the designated sFTP server
     */
    public function submitOrders(Context $context): ?Response
    {
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($this->container->get('order.repository'), false, $context); //TODO define which orders should be transmitted
        if($entities == null){
            return new Response('',Response::HTTP_NO_CONTENT);
        }
        if(count($entities) === 0){
            return new Response('',Response::HTTP_NO_CONTENT);
        }
        /** @var OrderEntity $order */
        foreach($entities as $orderID => $order)
        {
            if(strcmp($order->getStateMachineState()->getTechnicalName(),'open') == 0)
            {
                $orderNumber = $order->getOrderNumber();
                $fileContent = $this->generateFileContent($order, $orderNumber, false, $context);
    
                $filePath = $this->writeFile($orderNumber, $fileContent);
                
                $this->sendFile($filePath, "/WA" . "/waavis" . $orderNumber . ".csv");
            }
            else if (strcmp($order->getStateMachineState()->getTechnicalName(),'cancelled') == 0)
            {
                // get repository for confirmed cancelled orders
                $cancelledConfirmation = $this->container->get('as_cancelled_confirmation.repository');
                //check if order has already been confirmed, if it isn't existant create new entity
                if($this->oiUtils->OrderCancelConfirmationExistsCk($cancelledConfirmation, $order->getId(), $context))
                {
                    continue;
                }
                $orderNumber = $order->getOrderNumber();
                $fileContent = $this->generateFileContent($order, $orderNumber, true, $context);
    
                $filePath = $this->writeFile($orderNumber, $fileContent);
                
                $this->sendFile($filePath, "/WA" . "/waavis" . $orderNumber . ".csv");
            }
            
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /* Extracts all necessary data of the order for the logistics partner throught the CSVFactory */
    private function generateFileContent(OrderEntity $order, string $orderNumber, bool $orderCancelled, $context): string
    {
        // init exportData variable, this will contain the billing/delivery address aswell as every line item of the order
        $exportData = [];
        /** @var string $orderID */
        $orderID = $order->getId(); // orderID used to search inside other Repositories for corresponding data

        //customer eMail
        /** @var OrderCustomerEntity $orderCustomerEntity */
        $orderCustomerEntity = $order->getOrderCustomer();
        $eMailAddress = $orderCustomerEntity->getEmail();
        // deliveryaddress 
        $exportData = $this->oiUtils->getDeliveryAddress($this->container->get('order_address.repository'), $orderID, $eMailAddress, $context);
        $orderedProducts = $this->oiUtils->getOrderedProducts($this->container->get('order_line_item.repository'), $orderID, $context);
        
        $fileContent = '';
        $orderContent = '';
        $i = 0;
        /** @var OrderLineItemEntity $product */
        foreach($orderedProducts as $product)
        {//iterate through all products contained in this order
            if ($product->getIdentifier() === "INTERNAL_DISCOUNT")
            {//ignore the internal discount added if the ordering customer is an internal customer to avoid errors due to missing articlenumber etc.
                continue;
            }
            array_push($exportData, $product); // adding the lineitems to $exportData variable
            $orderContent = $this->csvFactory->generateDetails($exportData, $orderNumber, $i, $orderContent, $orderCancelled, $context); 
            $i++;
        }
        $fileContent = $this->csvFactory->generateHeader($exportData, $orderNumber, $fileContent, $orderCustomerEntity->getCustomerId(), $context);
        $fileContent = $fileContent . $orderContent; // concatenation of header, which has to be written after the contents, due to the order value being calculated after every line item has been processed
        return $fileContent;
    }

    /* Writes the current order to disc with a unique name depending on the orderID */
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

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullRMWA", name="api.custom.synlab_order_interface.pullRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls feedback about goods dispatchment from logistics partner
     */
    public function pullRMWA(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WA');
        return $this->checkRMWA($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWA", name="api.custom.synlab_order_interface.checkRMWA", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled feedback about goods dispatchment from logistics partner
     */
    public function checkRMWA(Context $context): ?Response
    {
        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('SynlabOrderInterface.config.deleteFilesAfterEvaluation');
        //create path for destination of transferred files
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WA') . '/';
        if (file_exists($path)) { // prevent exception if the folder couldn't be created due to missing rights
            $files = scandir($path); //get all files / folders 
            for($i = 2; $i < count($files); $i++) //iterate through every file in the folder
            {
                $filename = $files[$i]; // get the filename of current file
                $filenameContents = explode('_',$filename); // get the parts of the filename separated by a delimiter, in this case ('_')

                if($filenameContents[1] === 'STATUS') // status of order data procession
                {
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getOrder($this->container->get('order.repository'), 'orderNumber', $filenameContents[3],$context);
                   

                    if($order == null)
                    {// wrong kind of file has been read, notify administration and prevent deletion of files
                        $deleteFilesWhenFinished = false;
                        $this->sendErrorNotification('MAJOR ERROR','Major error occured.<br>Received reply for non existant order. Received filename:<br>' . $filename, [$path . $filename]);
                        continue;
                    }
                    switch ($filenameContents[2])
                    {
                        case '003': // order cannot be changed (already packed, shipped, cancelled)
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 003','Status 003 "order cannot be changed (already packed, shipped, cancelled)" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '005': // cannot be cancelled because never created
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 005','Status 005 "cannot be cancelled because already processed or cancelled" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '006': // cannot be cancelled because already processed or cancelled
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 006','Status 006 "cannot be cancelled because already processed or cancelled" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '007': // order changed
                            $this->sendErrorNotification('RM_WA Status 007','Status 007 "order successfully changed" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '009': // minor error in order
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WA Status 009','Status 009 "minor error in order" for order<br>' . $order->getOrderNumber(), [$path . $filename]);
                        break;
                        case '010': // order sucessfully imported to rieck LFS
                            $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'process');
                        break;
                        case '040': // order packaging started, order cannot be changed anymore
                            $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'complete');
                        break;
                        case '999': // major error (file doesn't meet the expectations, e.g. unfitting fieldlengths, fieldformats, missing necessary fields)
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('MAJOR ERROR','Major error occured.<br>One or more submitted files did not meet expectations and been rejected.', [$path . $filename]);
                        break;
                        default:
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('MAJOR ERROR','Major error occured.<br>File could not be recognized.', [$path . $filename]);
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
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getOrder($this->container->get('order.repository'), 'orderNumber', $filenameContents[2],$context);
                    
                    // get repository for confirmed cancelled orders
                    $cancelledConfirmation = $this->container->get('as_cancelled_confirmation.repository');
                    //check if order has already been confirmed, if it isn't existant create new entity
                    if(!$this->oiUtils->OrderCancelConfirmationExistsCk($cancelledConfirmation, $order->getId(), $context))
                    {
                        $cancelledConfirmation->create([
                            ['orderId' => $order->getId()],
                        ],
                        $context);
                    }
                    $result = $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'cancel');
                    if($result)
                    {
                        $this->sendErrorNotification('Order cancelled by logistics partner','Order<br>' . $order->getOrderNumber() . '<br>has been cancelled by logistics partner.<br>Communication needed.', [$path . $filename]);
                    }
                }
                else if ($filenameContents[1] === 'VLE') // packages loaded, we will have the tracking numbers and add them to the orderdelivery repository datafield
                {
                    /** @var EntityRepositoryInterface $orderRepositoryContainer */
                    $orderDeliveryRepository = $this->container->get('order_delivery.repository');
                    /** @var EntityRepositoryInterface $orderLineItemRepository */
                    $orderLineItemRepository = $this->container->get('order_line_item.repository');
                    /** @var OrderEntity $order */
                    $order = $this->oiUtils->getOrder($this->container->get('order.repository'), 'orderNumber', $filenameContents[2],$context);

                    /** @var string $orderDelivery */
                    $orderDeliveryID = $this->oiUtils->getDeliveryEntityID($orderDeliveryRepository, $order->getId(),$context);
                    
                    /** @var Criteria $criteria */
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('id', $orderDeliveryID));
                    /** @var EntitySearchResult $entities */
                    $orderDeliveryEntities = $orderDeliveryRepository->search($criteria, $context);
                    /** @var OrderDeliveryEntity $orderDelivery */
                    $orderDelivery = $orderDeliveryEntities->first();
                    
                    
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);

                    $condensedArray = array();      
                    $prevValue = 0;
                    for($x = 1; $x < count($fileContentsByLine); $x++)
                    {
                        $contentLine = explode(';', $fileContentsByLine[$x]);

                        if(count($contentLine) <= 1) // skip if line contains no information
                            continue;
                            
                        $this->controllingReportController->generateControllingEntityFromVLE($contentLine);

                        // $contentLineFields = explode(';',$contentLine);
                        $productNumber = $contentLine[5];
                        if($productNumber == "99999") // skip if line contains information about stored files, they always have the product ID 99999
                            continue;

                        $prevValue = array_key_exists($productNumber, $condensedArray) ? $condensedArray[$productNumber] : 0;
                        $condensedArray[$productNumber] = $prevValue + intval($contentLine[6]);
                    }

                    foreach($condensedArray as $productNumber => $reportedAmount)
                    {
                        $orderID = $order->getId();
                        $orderedProducts = $this->oiUtils->getOrderedProducts($orderLineItemRepository, $orderID, $context);

                        /** @var OrderLineItemEntity $orderLineItem */
                        foreach($orderedProducts as $orderLineItem)
                        {
                            $productToCompare = $this->oiUtils->getProduct($this->container->get('product.repository'),$productNumber,$context);
                            if($productToCompare->getId() == $orderLineItem->getProductId())
                            {
                                if($orderLineItem->getQuantity() != $reportedAmount)
                                {
                                    $deleteFilesWhenFinished = false;
                                    if($reportedAmount == 0)
                                    {
                                        $orderLineItemRepository->delete([
                                            ['id' => $orderLineItem->getId()],
                                        ],$context);
                                    }
                                    else
                                    {
                                        $unitPrice = $orderLineItem->getUnitPrice();
                                        $totalPrice = $unitPrice * $reportedAmount;
                                        /** @var QuantityPriceDefinition $orderLineItemPriceDefinition */
                                        $orderLineItemPriceDefinition = $orderLineItem->getPriceDefinition();
                                        $orderLineItemPriceDefinition->setQuantity($reportedAmount);

                                        /** @var Price $orderLineItemPrice */
                                        $orderLineItemPrice = $orderLineItem->getPrice();
                                        $orderLineItemRepository->update(
                                            [
                                                [ 'id' => $orderLineItem->getId(), 'quantity' => $reportedAmount],
                                            ],
                                            Context::createDefaultContext()
                                        );
                                    }
                                    $this->sendErrorNotification('Order deviation VLE', 'Rieck was not able to pack enough product.<br>EntryID: ' . $x, [$path . $filename]);
                                }
                            }
                        }
                    }

                    $headContents = explode(';',$fileContentsByLine[0]);
                    $trackingnumbers = array();
                    for ($j = 1; $j < count($fileContentsByLine)-1; $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);
                        array_push($trackingnumbers,$lineContents[9]);
                    }
                    $stateChanged = $this->oiOrderServiceUtils->updateOrderDeliveryStatus($orderDelivery, $orderDeliveryID, 'ship');
                    if($stateChanged) // unly update tracking numbers if the parcel hasn't been shipped yet
                    {
                        $this->oiUtils->updateTrackingNumbers($orderDeliveryRepository, $orderDeliveryID, array_unique($trackingnumbers), $context);
                    }
                }
            }
        } 
        $this->archiveFiles($path,$deleteFilesWhenFinished);
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullRMWE", name="api.custom.synlab_order_interface.pullRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls feedback about goods receipt from logistics partner
     */
    public function pullRMWE(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'RM_WE');
        return $this->checkRMWE($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWE", name="api.custom.synlab_order_interface.checkRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled feedback about goods receipt from logistics partner
     */
    public function checkRMWE(Context $context): ?Response
    {
        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('SynlabOrderInterface.config.deleteFilesAfterEvaluation');

        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/RM_WE') . '/'; // get path of pulled files
        if (file_exists($path)) { // prevent exception if the folder couldn't be created due to missing rights
            $files = scandir($path); //get all files / folders 
            for ($i = 2; $i < count($files); $i++) { // iterate through every file in the folder
                $filename = $files[$i]; 
                $filenameContents = explode('_',$filename);

                if($filenameContents[1] == 'STATUS')
                {
                    switch($filenameContents[2])
                    {
                        case '001': //WEAvis couldn't be created
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE Status 001','Status 001 "WEAvis could not be created" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '005': //WEAvis cannot be cancelled due to being not existant, already processed or cancelled
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE Status 005','Status 005 "WEAvis cannot be cancelled due to being not existant, already processed or cancelled" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '007': //WEAvis change processed
                            // $this->sendErrorNotification('RM_WE Status 007','Status 007 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '009': //WEAvis could not be processed, errors inside WEAvis
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE Status 009','Status 009 "WEAvis could not be processed, errors inside WEAvis" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        case '010': //WEAvis processed
                            // $this->sendErrorNotification('RM_WE Status 010','Status 010 "WEAvis could not be created" for order ' . $filenameContents[3]);
                        break;
                        case '999': //WEAvis message could not be processed, out of specification
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE Status 999','Status 999 "WEAvis message could not be processed, out of specification" for order<br>' . $filenameContents[3], [$path . $filename]);
                        break;
                        default:
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE Status unknown','unkown status reported', [$path . $filename]);
                        break;
                    }
                }
                else if($filenameContents[1] == 'ERROR')
                {
                    $this->sendErrorNotification('RM_WE Status ERROR','Unknown Error reported', [$path . $filename]);
                }
                else if($filenameContents[1] == 'WKE')
                {
                    $filecontents = file_get_contents($path . $filename);
                    $fileContentsByLine = explode(PHP_EOL,$filecontents);
                    $headContents = explode(';',$fileContentsByLine[0]);
                    for ($j = 1; $j < count($fileContentsByLine); $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);
                        if(count($lineContents) <= 1)
                            continue;

                        $articleNumber = $lineContents[5];
                        $amount = $lineContents[6];
                        $amountAvailable = $lineContents[7];
                        // $amountDamaged = $lineContents[8];
                        // $amountClarification = $lineContents[9];
                        // $amountPostProcessing = $lineContents[10];
                        // $amountOther = $lineContents[11];
                        
                        $this->updateProduct($articleNumber, $amount, $amountAvailable, $context);
                        $this->updateQSStock($lineContents, $articleNumber, $context);
                        $this->updateDispoControlData($articleNumber, intval($amount), $context);

                        if($amount != $amountAvailable)
                        {
                            $deleteFilesWhenFinished = false;
                            $this->sendErrorNotification('RM_WE WKE','Damaged or otherwise unusable products reported at position:<br> ' . $j . '<br><br>Check back with logistics partner to keep stock information up to date.', [$path . $filename]);
                        }
                    }
                }
            }
        }
        $this->archiveFiles($path,$deleteFilesWhenFinished);
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    /* Updates stock according to logistics partner response */
    private function updateProduct(string $articleNumber, $stockAddition, $availableStockAddition, $context)
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');

        /** @var ProductEntity $productEntity */
        $productEntity = $this->oiUtils->getProduct($this->container->get('product.repository'), $articleNumber, $context);

        $currentStock = $productEntity->getStock();
        $currentStockAvailable = $productEntity->getAvailableStock();

        $newStockValue = $currentStock + intval($availableStockAddition);
        // $newAvailableStockValue = $currentStockAvailable + intval($availableStockAddition);

        $productRepository->update(
            [
                [ 'id' => $productEntity->getId(), 'stock' => $newStockValue ],
                // [ 'id' => $productEntity->getId(), 'availableStock' => $newAvailableStockValue ], //value is write protected
            ],
            $context
        );
    }

    /* */
    private function updateQSStock(array $lineContents, string $articleNumber, Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');
        $product = $this->oiUtils->getProduct($productRepository, $articleNumber, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId',$product->getId()));

        
        /** @var EntitySearchResult $searchResult */
        $searchResult = null;
        
        $searchResult = $stockQSRepository->search($criteria,$context);
            
        if(count($searchResult) == 0)
        {
            // generate new entry
            $stockQSRepository->create([
                ['productId' => $product->getId(), 'faulty' => intval($lineContents[8]), 'clarification' => intval($lineContents[9]), 'postprocessing' => intval($lineContents[10]), 'other' => intval($lineContents[11])],
            ],
                $context
            );
            return;
        }
        else
        {
            // update entry
            $entry = $searchResult->first();

            /** @var OrderInterfaceStockQSEntity $stockQSEntity */
            $stockQSEntity = $searchResult->first();
            $faulty = $stockQSEntity->getFaulty();
            $clarification = $stockQSEntity->getClarification();
            $postprocessing = $stockQSEntity->getPostprocessing();
            $other = $stockQSEntity->getOther();
            
            $stockQSRepository->update([
                ['id' => $entry->getId(), 'productId' => $product->getId(), 'faulty' => intval($lineContents[8]) + $faulty, 'clarification' => intval($lineContents[9]) + $clarification, 'postprocessing' => intval($lineContents[10]) + $postprocessing, 'other' => intval($lineContents[11]) + $other],
            ],
                $context
            );
        }
    }

    private function updateDispoControlData(string $articleNumber, $amount, $context)
    {
        $entity = null;
        /** @var EntityRepositoryInterface $asDispoDataRepository */
        $asDispoDataRepository = $this->get('as_dispo_control_data.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $articleNumber));

        /** @var DispoControlDataEntity $entity*/
        $entity = $asDispoDataRepository->search($criteria,$context)->first();

        if($entity == null || $amount == 0)
            return;

        $asDispoDataRepository->update([
            ['id' => $entity->getId(), 'incoming' => $entity->getIncoming()-$amount],
        ], $context);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullArticleError", name="api.custom.synlab_order_interface.pullArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls the article error response from remote sFTP server
     */
    public function pullArticleError(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Artikel_Error');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Artikel_Error');
        return $this->checkArticleError($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkArticleError", name="api.custom.synlab_order_interface.checkArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Checks pulled article error response, iterates through them and send a notification eMail to administration
     */
    public function checkArticleError(Context $context): ?Response
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
                $this->sendErrorNotification('Error: Article base','Error reported by logistics partner, submitted article base contains errors check logfile for further informations.', [$path . $filename]);
            }
        }
        $this->archiveFiles($path,false);
        return new Response('',Response::HTTP_NO_CONTENT);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/pullBestand", name="api.custom.synlab_order_interface.pullBestand", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Pulls the stock report from remote sFTP server
     */
    public function pullBestand(Context $context): ?Response
    {
        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
        $this->sftpController->pullFile($path,'Bestand');
        return $this->checkBestand($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkBestand", name="api.custom.synlab_order_interface.checkBestand", methods={"POST"})
     * @param Context $context;
     * @return Response
     * Processes pulled stock report
     * QS Marks
     * faulty - DF
     * postprocessing - NB
     * other - SO
     * clarification - KL
     */
    public function checkBestand(Context $context): ?Response
    {
        $deleteFilesWhenFinished = false; // since a false bool value is null for shopware we predefine the false value... 
        //get flag for deleting files when finished
        $deleteFilesWhenFinished = $this->systemConfigService->get('SynlabOrderInterface.config.deleteFilesAfterEvaluation');

        $path = $this->oiUtils->createTodaysFolderPath('ReceivedStatusReply/Bestand') . '/';
        if (file_exists($path)) {
            $files = scandir($path);
            for ($i = 2; $i < count($files); $i++) {
                $filename = $files[$i];
                $filenameContents = explode('_', $filename);

                switch ($filenameContents[0])
                    {
                        case 'QSK':
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents);       
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }
                                $deleteFilesWhenFinished = false;
                                $this->sendErrorNotification('QSK', 'Change in quality status. EntryID: ' . $entryID, [$path . $filename]);
                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->processQSK($lineContents[1],0,-intval($lineContents[5]),intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->processQSK($lineContents[1],0,-intval($lineContents[5]),0,intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->processQSK($lineContents[1],intval($lineContents[5]),-intval($lineContents[5]),0,0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                            $this->processQSK($lineContents[1],0,-intval($lineContents[5]),0,0,intval($lineContents[5]),$context);
                                            break;
                                        }
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        switch($lineContents[9])
                                        {
                                            case 'KL': // to clarification
                                                $this->processQSK($lineContents[1],0,intval($lineContents[5]),-intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->processQSK($lineContents[1],0,0,-intval($lineContents[5]),intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->processQSK($lineContents[1],intval($lineContents[5]),0,-intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->processQSK($lineContents[1],0,0,-intval($lineContents[5]),0,intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    case 'SO': // Sonstige / otherwise
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->processQSK($lineContents[1],0,0,intval($lineContents[5]),-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'KL': // to clarification
                                                $this->processQSK($lineContents[1],0,intval($lineContents[5]),0,-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'DF': // to faulty
                                                $this->processQSK($lineContents[1],intval($lineContents[5]),0,0,-intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->processQSK($lineContents[1],0,0,0,-intval($lineContents[5]),intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    case 'DF': // Defekt // faulty
                                        switch($lineContents[9])
                                        {
                                            case 'NB': // to postprocessing
                                                $this->processQSK($lineContents[1],-intval($lineContents[5]),0,intval($lineContents[5]),0,0,$context); // intval($lineContents[5])
                                            break;
                                            case 'SO': // to other
                                                $this->processQSK($lineContents[1],-intval($lineContents[5]),0,0,intval($lineContents[5]),0,$context); // intval($lineContents[5])
                                            break;
                                            case 'KL': // to clarification
                                                $this->processQSK($lineContents[1],-intval($lineContents[5]),intval($lineContents[5]),0,0,0,$context); // intval($lineContents[5])
                                            break;
                                            default:
                                                $this->processQSK($lineContents[1],-intval($lineContents[5]),0,0,0,intval($lineContents[5]),$context); // intval($lineContents[5])
                                            break;
                                        }
                                    break;
                                    default:
                                        switch($lineContents[9])
                                        {
                                            case 'KL': // to clarification
                                                $this->processQSK($lineContents[1],0,intval($lineContents[5]),0,0,-intval($lineContents[5]),$context); 
                                            break;
                                            case 'NB': // to postprocessing
                                                $this->processQSK($lineContents[1],0,0,intval($lineContents[5]),0,-intval($lineContents[5]),$context); 
                                            break;
                                            case 'SO': // to other
                                                $this->processQSK($lineContents[1],0,0,0,intval($lineContents[5]),-intval($lineContents[5]),$context); 
                                            break;
                                            case 'DF': // to faulty
                                                $this->processQSK($lineContents[1],intval($lineContents[5]),0,0,0,-intval($lineContents[5]),$context); 
                                            break;
                                        }
                                    break;
                                }
                            }
                        break;
                        case 'BESTAND': // Daily report of current available items
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents); 
                            $uniqueProductNumbers = array();
                            $condensedArray = array();      
                            $prevValue = 0;
                            foreach ($fileContentsByLine as $contentLine) {
                                if($contentLine == "") // skip if line contains no information
                                    continue;

                                $contentLineFields = explode(';',$contentLine);
                                $productNumber = $contentLineFields[1];
                                if($productNumber == "99999") // skip if line contains information about stored files, they always have the product ID 99999
                                    continue;

                                if(!in_array($productNumber,$uniqueProductNumbers))
                                    array_push($uniqueProductNumbers,$productNumber);

                                $prevValue = array_key_exists($productNumber . '-' . "available", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "available"] : 0;
                                $condensedArray[$productNumber . '-' . "available"] = $prevValue + intval($contentLineFields[5]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsFaulty", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsFaulty"] : 0;
                                $condensedArray[$productNumber . '-' . "qsFaulty"] = $prevValue + intval($contentLineFields[6]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsClarification", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsClarification"] : 0;
                                $condensedArray[$productNumber . '-' . "qsClarification"] = $prevValue + intval($contentLineFields[7]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsPostprocessing", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsPostprocessing"] : 0;
                                $condensedArray[$productNumber . '-' . "qsPostprocessing"] = $prevValue + intval($contentLineFields[8]);

                                $prevValue = array_key_exists($productNumber . '-' . "qsOther", $condensedArray) ? $condensedArray[$contentLineFields[1] . '-' . "qsOther"] : 0;
                                $condensedArray[$productNumber . '-' . "qsOther"] = $prevValue + intval($contentLineFields[9]);
                            }

                            $productCount = count($condensedArray) / 5; // amount of entries in the condensed array divided by the amount of unique entries per product

                            for($y = 0; $y < $productCount; $y++)
                            {
                                $articleNumber = $uniqueProductNumbers[$y];

                                $available = $condensedArray[$articleNumber . '-' . "available"];
                                $qsFaulty = $condensedArray[$articleNumber . '-' . "qsFaulty"];
                                $qsClarification = $condensedArray[$articleNumber . '-' . "qsClarification"];
                                $qsPostprocessing = $condensedArray[$articleNumber . '-' . "qsPostprocessing"];
                                $qsOther = $condensedArray[$articleNumber . '-' . "qsOther"];

                                $productRepository = $this->container->get('product.repository');
                                $stockQSRepository = $this->container->get('as_stock_qs.repository');

                                /** @var ProductEntity $productEntity */
                                $productEntity = $this->oiUtils->getProduct($productRepository, $articleNumber, $context);
                                if($productEntity == null)
                                {
                                    $this->sendErrorNotification('Stock feedback contains unknown product', 'A product mentioned in the daily stock feedback report is unkown.<br>Please check the stock feedback at line ' . $y, [$path . $filename]);
                                    continue;
                                }

                                $criteria = new Criteria();
                                $criteria->addFilter(new EqualsFilter('productId',$productEntity->getId()));

                                $searchResult = $stockQSRepository->search($criteria,$context);
                                /** @var OrderInterfaceStockQSEntity $stockQSEntity */
                                $stockQSEntity = $searchResult->first();

                                $discrepancy = false;
                                $discrepancyValue = 0;
                                if($productEntity->getAvailableStock() != $available)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $productEntity->getAvailableStock() - $available;
                                }
                                if($stockQSEntity->getFaulty() != $qsFaulty)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getFaulty() - $qsFaulty;
                                }
                                if($stockQSEntity->getClarification() != $qsClarification)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getClarification() - $qsClarification;
                                }
                                if($stockQSEntity->getPostprocessing() != $qsPostprocessing)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getPostprocessing() - $qsPostprocessing;
                                }
                                if($stockQSEntity->getOther() != $qsOther)
                                {
                                    $discrepancy = true;
                                    $discrepancyValue = $stockQSEntity->getOther() - $qsOther;
                                }
                                if($discrepancy)
                                {
                                    $deleteFilesWhenFinished = false;
                                    $this->sendErrorNotification('Stock Feedback Discrepancy','Discrepancies found in stock feedback check logfile for further informations.<br>Articlenumber: ' . $articleNumber . "<br>Discrepancy: " . $discrepancyValue, [$path . $filename]);
                                }
                            }                       
                        break;
                        case 'BS+': // addition of currently available items (items lost but found, etc.)
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents); 
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }
                                $articleNumber = $lineContents[1];
                                $productRepository = $this->container->get('product.repository');
                                /** @var EntityRepositoryInterface $stockQSRepository */
                                $stockQSRepository = $this->container->get('as_stock_qs.repository');
                                $productEntity = $this->oiUtils->getProduct($productRepository, $articleNumber, $context);

                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification

                                        $this->updateQSStockBS(0,0,0,intval($lineContents[5]),$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        
                                        $this->updateQSStockBS(0,intval($lineContents[5]),0,0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'SO': // Sonstige / other
                                        
                                        $this->updateQSStockBS(0,0,intval($lineContents[5]),0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'DF': // Defekt // faulty
                                        
                                        $this->updateQSStockBS(intval($lineContents[5]),0,0,0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Addition','Products added, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    default:
                                        $current = $productEntity->getStock();
                                        $productRepository->update([
                                            ['id' => $productEntity->getId(), 'stock' => $current + intval($lineContents[5])],
                                        ],$context);
                                    break;
                                }
                            }
                        break;
                        case 'BS-': // subtraction of currently available items (lost, stolen, destroyed, etc.)
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents);       
                            foreach ($fileContentsByLine as $entryID => $contentLine) {
                                $lineContents = explode(';', $contentLine);
                                if(count($lineContents) <= 1)
                                {
                                    continue;
                                }

                                $articleNumber = $lineContents[1];
                                $productRepository = $this->container->get('product.repository');
                                /** @var EntityRepositoryInterface $stockQSRepository */
                                $stockQSRepository = $this->container->get('as_stock_qs.repository');
                                $productEntity = $this->oiUtils->getProduct($productRepository, $articleNumber, $context);
                                switch($lineContents[7])
                                {
                                    case 'KL': // klärfall / clarification
                                        
                                        $this->updateQSStockBS(0,0,0,intval($lineContents[5]),$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'NB': // Nachbearbeitung / postprocessing
                                        
                                        $this->updateQSStockBS(0,intval($lineContents[5]),0,0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'SO': // Sonstige / other
                                        
                                        $this->updateQSStockBS(0,0,intval($lineContents[5]),0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    case 'DF': // Defekt // faulty
                                        
                                        $this->updateQSStockBS(intval($lineContents[5]),0,0,0,$articleNumber,$context);
                                        $this->sendErrorNotification('Stock Subtraction','Products removed, clarification needed. EntryID: ' . $entryID, [$path . $filename]);
                                    break;
                                    default:
                                        $current = $productEntity->getStock();

                                        $productRepository->update([
                                            ['id' => $productEntity->getId(), 'stock' => $current - intval($lineContents[5])],
                                        ],$context);
                                    break;
                                }
                            }
                        break;
                        default:
                            $filecontents = file_get_contents($path . $filename);
                            $fileContentsByLine = explode(PHP_EOL,$filecontents);       
                            foreach ($fileContentsByLine as $contentLine) {
                                $lineContents = explode(';', $contentLine);
                            }
                        break;
                    }
            }
        }
        $this->archiveFiles($path,$deleteFilesWhenFinished);
        return new Response('',Response::HTTP_NO_CONTENT);
    }

    private function updateQSStockBS(int $faulty, int $postprocessing, int $other, int $clarification, string $articleNumber, Context $context)
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');

        $productEntity = $this->oiUtils->getProduct($productRepository, $articleNumber, $context);
        $productID = $productEntity->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId',$productID));
        
        /** @var EntitySearchResult $searchResult */
        $searchResult = null;
        
        $searchResult = $stockQSRepository->search($criteria,$context);
        if(count($searchResult) == 0)
        {
            // generate new entry
            $stockQSRepository->create([
                ['productId' => $productID, 'faulty' => $faulty, 'clarification' => $clarification, 'postprocessing' => $postprocessing, 'other' => $other],
            ], $context);
            return;
        }
        else
        {
            // update entry
            $entry = $searchResult->first();

            /** @var OrderInterfaceStockQSEntity $stockQSEntity */
            $stockQSEntity = $searchResult->first();
            $currentFaulty = $stockQSEntity->getFaulty();
            $currentClarification = $stockQSEntity->getClarification();
            $currentPostprocessing = $stockQSEntity->getPostprocessing();
            $currentOther = $stockQSEntity->getOther();
            
            $stockQSRepository->update([
                ['id' => $entry->getId(), 'productId' => $productID, 'faulty' => $currentFaulty + $faulty, 'clarification' => $currentClarification + $clarification, 'postprocessing' => $currentPostprocessing + $postprocessing, 'other' => $currentOther + $other],
            ],
                $context
            );
        }
    }

    private function processQSK(string $articleNumber, int $faulty, int $clarification, int $postprocessing, int $other, int $stock ,Context $context)
    {
        /** @var EntityRepositoryInterface $stockQSRepository */
        $stockQSRepository = $this->container->get('as_stock_qs.repository');
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');
        /** @var ProductEntity $product */
        $product = $this->oiUtils->getProduct($productRepository,$articleNumber,$context);
        $productID = $product->getId();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId',$productID));

        $searchResult = null;
        $searchResult = $stockQSRepository->search($criteria,$context);
        

        if(count($searchResult) == 0)
        {
            if($faulty < 0 || $clarification < 0 || $postprocessing < 0 || $other < 0)
            {
                $this->sendErrorNotification('QSK error','major error, check logs.<br>A new stock qs entry tried to be created with negative values.', ['']);
                return;
            }
            $stockQSRepository->create([
                ['productId' => $productID, 'faulty' => $faulty, 'clarification' => $clarification, 'postprocessing' => $postprocessing, 'other' => $other],
            ], $context);
        }
        else
        {
            /** @var OrderInterfaceStockQSEntity $stockQSEntity */
            $stockQSEntity = $searchResult->first();
            $currentFaulty = $stockQSEntity->getFaulty();
            $currentClarification = $stockQSEntity->getClarification();
            $currentPostprocessing = $stockQSEntity->getPostprocessing();
            $currentOther = $stockQSEntity->getOther();

            $stockQSRepository->update([
                ['id' => $stockQSEntity->getId(), 'faulty' => $currentFaulty + $faulty, 'clarification' => $currentClarification + $clarification, 'postprocessing' => $currentPostprocessing + $postprocessing, 'other' => $currentOther + $other],
            ], $context);

            

            $currentStock = $product->getStock();
            $newStockValue = $currentStock + $stock;
            $productRepository->update(
                [
                    [ 'id' => $product->getId(), 'stock' => $newStockValue ],
                ],
                $context
            );
            if($newStockValue < 0)
            {
                $this->sendErrorNotification('QSK error','New stockvalue is below 0, check logs and data' . $product->getProductNumber(),['']);
            }
        }
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/modifyOrdersState", name="api.custom.synlab_order_interface.modifyOrdersState", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function modifyOrdersState(Context $context)
    {//reopen,process,complete,cancel
        /** @var EntitySearchResult $entities */
        $entities = $this->oiUtils->getOrderEntities($this->container->get('order.repository'), false, $context);

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

    /* Sends an eMail to every entry in the plugin configuration inside the administration frontend */
    private function sendErrorNotification(string $errorSubject, string $errorMessage, array $fileArray)
    {
        $notificationSalesChannel = $this->systemConfigService->get('SynlabOrderInterface.config.fallbackSaleschannelNotification');

        $recipientList = $this->systemConfigService->get('SynlabOrderInterface.config.errorNotificationRecipients');
        $recipientData = explode(';', $recipientList);
        $recipients = null;
        for ($i = 0; $i< count($recipientData); $i +=2 )
        {
            $recipientName = $recipientData[$i];
            $recipientAddress = $recipientData[$i+1];

            $mailCheck = explode('@', $recipientAddress);
            if(count($mailCheck) != 2)
            {
                continue;
            }
            $recipients[$recipientAddress] = $recipientName;
        }

        $this->mailserviceHelper->sendMyMail($recipients, $notificationSalesChannel, $this->senderName, $errorSubject, $errorMessage, $errorMessage, $fileArray);
    }
    /* Deletes recursive every file and folder in given path. So... be careful which path gets passed to this function */
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
    /* Deletes recursive every file and folder in given path. So... be careful which path gets passed to this function */
    private function archiveFiles($dir, $delete)
    {
        if(!$delete)
        {
            $splitDir = explode('/', $dir);
            $archivePath = '';
            foreach($splitDir as $dirPart)
            {
                if($dirPart == '')
                {
                    continue;
                }
                $archivePath = $archivePath . $dirPart . '/' ;
                if($dirPart == 'InterfaceData')
                {
                    $archivePath = $archivePath . 'Archive' . '/';
                }
            }
            if (!file_exists($archivePath)) {
                mkdir($archivePath, 0777, true);
            }
            //copy all files from $dir to $archivePath
            $files = scandir($dir);
            for($i = 2; $i < count($files); $i++)
            {
                $source = $dir . $files[$i]; 
                $dest = $archivePath . $files[$i]; 
                copy($source,$dest);
            }
            $this->deleteFiles($dir);
        }
        else
        {
            $this->deleteFiles($dir);
        }        
    }

    /* Transmission of local file to destination path on remote sFTP server */
    private function sendFile(string $filePath, string $destinationPath)
    {
        $this->sftpController->pushFile($filePath, $destinationPath);
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/healthPing", name="api.custom.synlab_order_interface.healthPing", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function healthPing(Context $context)
    {
        /** @var EntityRepositoryInterface $scheduledTaskRepository */
        $scheduledTaskRepository = $this->container->get('scheduled_task.repository');
        $scheduledTasks = $scheduledTaskRepository->search(new Criteria(),Context::createDefaultContext());

        /** @var ScheduledTaskEntity $scheduledTask */
        foreach($scheduledTasks as $taskID => $scheduledTask)
        {
            $scheduledTaskName = $scheduledTask->getName();
            if(! $this->isMyScheduledTaskCk($scheduledTaskName))
                continue;

            $taskStatus = $scheduledTask->getStatus();
            if($taskStatus == 'failed')
            {
                $this->sendErrorNotification('Scheduled Task Failed', "Task $scheduledTaskName has failed.<br>Check dead messages for further informations.", ['']);
            }
        }

        return new Response('',Response::HTTP_NO_CONTENT);
    }
    private function isMyScheduledTaskCk(string $taskName): bool
    {
        if($taskName == 'synlab.scheduled_order_transfer_task')
            return true;
        if($taskName == 'synlab.scheduled_order_process_article_error')
            return true;
        if($taskName == 'synlab.scheduled_order_process_rmwa')
            return true;
        if($taskName == 'synlab.scheduled_order_process_rmwe')
            return true;
        if($taskName == 'synlab.scheduled_order_process_stock_feedback')
            return true;
    
        return false;
    }
}