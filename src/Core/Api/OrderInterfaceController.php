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
            if(strcmp($order->getStateMachineState()->getName(),'Open') != 0)
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
    public function pullRMWA(Context $context): Response
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

                if($filenameContents[1] === 'STATUS') // status of order data procession
                {
                    /** @var Criteria $criteria */
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('orderNumber', $filenameContents[3]));
                    /** @var EntityRepositoryInterface $orderRepositoryContainer */
                    $orderRepositoryContainer = $this->repositoryContainer->getOrderRepository();
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
                            $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'process');
                        break;
                        case '040': // order packaging started, order cannot be changed anymore
                            $this->oiOrderServiceUtils->updateOrderStatus($order, $order->getId(), 'complete');
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
                    /** @var Criteria $criteria */
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('orderNumber', $filenameContents[2]));
                    /** @var EntityRepositoryInterface $orderRepositoryContainer */
                    $orderRepositoryContainer = $this->repositoryContainer->getOrderRepository();
                    /** @var EntitySearchResult $entities */
                    $orderEntities = $orderRepositoryContainer->search($criteria, $context);
                    /** @var OrderEntity $order */
                    $order = $orderEntities->first();

                    /** @var string $orderDelivery */
                    $orderDeliveryID = $this->oiUtils->getDeliveryEntityID($this->repositoryContainer->getOrderDeliveryRepository(),$order->getId(),$context);
                    
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

                    for ($j = 1; $j < count($fileContentsByLine)-1; $j++)
                    {
                        $lineContents = explode(';', $fileContentsByLine[$j]);

                        $trackingData[] = [
                            'id' => Uuid::randomHex(),
                            'orderId' => strval($order->getId()),
                            'service' => $headContents[4],
                            'position' => $lineContents[2],
                            'trackingNumber' => $lineContents[9]
                        ];
                    }
                    $this->repositoryContainer->getParcelTracking()->create($trackingData, $context);
                    $stateChanged = $this->oiOrderServiceUtils->updateOrderDeliveryStatus($orderDelivery, $orderDeliveryID, 'ship');
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
        return $this->checkRMWE($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkRMWE", name="api.custom.synlab_order_interface.checkRMWE", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkRMWE(Context $context): Response
    {
        //TODO
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
        return $this->checkArticleError($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkArticleError", name="api.custom.synlab_order_interface.checkArticleError", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkArticleError(Context $context): Response
    {
        //TODO
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
        return $this->checkBestand($context);
    }
    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/checkBestand", name="api.custom.synlab_order_interface.checkBestand", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function checkBestand(Context $context): Response
    {
        //TODO
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
            $this->oiOrderServiceUtils->updateOrderStatus($order, $orderID, 'reopen');
        }
        return new Response('',Response::HTTP_NO_CONTENT);
    }
}