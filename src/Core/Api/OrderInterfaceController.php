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
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
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
    /** @var EntityRepositoryInterface $productsRepository */
    private $productsRepository;
    /** @var PropertyAccess $propertyAccessor */
    private $properyAccessor;
    /** @var string */
    private $companyID;
    /** @var string */
    private $todaysFolderPath;
    public function __construct(EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        $this->properyAccessor = PropertyAccess::createPropertyAccessor();
        $this->companyID = 'synlabRieckInterfaceID';

        $timeStamp = new DateTime();
        $timeStamp = $timeStamp->format('d-m-Y');
        $this->todaysFolderPath = '../custom/plugins/SynlabOrderInterface/SubmittedOrders/' . $timeStamp;


        if (!file_exists($this->todaysFolderPath)) {
            mkdir($this->todaysFolderPath, 0777, true);
        }
    }

    /**
     * @Route("/api/v{version}/_action/synlab-order-interface/chill", name="api.custom.synlab_order_interface.chill", methods={"POST"})
     * @param Context $context;
     * @return Response
     */
    public function chill(Context $context): Response
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
            $productData = array(
                'Artikelnummer' => ''
            );
            $articleBase = $this->generateArticlebase($productData);
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

    private function generateArticlebase()
    {
        $placeholder = '';
        $csvString = '';
        $csvString = $csvString . 'Nr.' . ';' . 'Feldname' . ';' . 'Wert' . "\n";                                       // (maximum)Length
        $csvString = $csvString . '1' . ';' . 'Kennung' . ';' . $this->companyID . "\n";                                // (maximum)Length
        $csvString = $csvString . '2' . ';' . 'Artikelnummer' . ';' . $placeholder . "\n";                              // (maximum)Length
        $csvString = $csvString . '3' . ';' . 'Matchcode' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '4' . ';' . 'Artikelbezeichnung 1' . ';' . $placeholder . "\n";                       // (maximum)Length
        $csvString = $csvString . '5' . ';' . 'Artikelbezeichnung 2' . ';' . $placeholder . "\n";                       // (maximum)Length
        $csvString = $csvString . '6' . ';' . 'Artikelbezeichnung 3' . ';' . $placeholder . "\n";                       // (maximum)Length
        $csvString = $csvString . '7' . ';' . 'Warengruppe' . ';' . $placeholder . "\n";                                // (maximum)Length
        $csvString = $csvString . '8' . ';' . 'Basismengeneinheit' . ';' . $placeholder . "\n";                         // (maximum)Length
        $csvString = $csvString . '9' . ';' . 'Basismengeneinheit, Gewicht in KG, netto' . ';' . $placeholder . "\n";   // (maximum)Length
        $csvString = $csvString . '10' . ';' . 'Basismengeneinheit, Gewicht in KG, brutto' . ';' . $placeholder . "\n"; // (maximum)Length
        $csvString = $csvString . '11' . ';' . 'Basismengeneinheit, Länge in mm' . ';' . $placeholder . "\n";           // (maximum)Length
        $csvString = $csvString . '12' . ';' . 'Basismengeneinheit, Breite in mm' . ';' . $placeholder . "\n";          // (maximum)Length
        $csvString = $csvString . '13' . ';' . 'Basismengeneinheit, Höhe in mm' . ';' . $placeholder . "\n";            // (maximum)Length
        $csvString = $csvString . '14' . ';' . 'Verpackungseinheit (VE) Mengeneinheit' . ';' . $placeholder . "\n";     // (maximum)Length
        $csvString = $csvString . '15' . ';' . 'VE Menge' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '16' . ';' . 'VE Mengeneinheit Gewicht in KG, netto' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '17' . ';' . 'VE Mengeneinheit Gewicht in KG, brutto' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '18' . ';' . 'VE Mengeneinheit, Länge in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '19' . ';' . 'VE Mengeneinheit, Breite in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '20' . ';' . 'VE Mengeneinheit, Höhe in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '21' . ';' . 'Lademittel (LM) Typ' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '22' . ';' . 'LM Menge' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '23' . ';' . 'LM Mengeneinheit, Gewicht in KG, netto' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '24' . ';' . 'LM Mengeneinheit, Gewicht in KG, brutto' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '25' . ';' . 'LM Mengeneinheit, Länge in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '26' . ';' . 'LM Mengeneinheit, Breite in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '27' . ';' . 'LM Mengeneinheit, Höhe in mm' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '28' . ';' . 'EAN Nummer 1' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '29' . ';' . 'EAN Nummer 2' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '30' . ';' . 'EAN Nummer 3' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '31' . ';' . 'EAN Nummer 4' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '32' . ';' . 'EAN Nummer 5' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '33' . ';' . 'EAN Nummer VE' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '34' . ';' . 'Lief.Artikelnummer 1' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '35' . ';' . 'Lief.Artikelnummer 2' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '36' . ';' . 'Lief.Artikelnummer 3' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '37' . ';' . 'Lief.Artikelnummer 4' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '38' . ';' . 'Lief.Artikelnummer 5' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '39' . ';' . 'MHD Pflicht?' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '40' . ';' . 'MHD Restlaufzeit, WE' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '41' . ';' . 'MHD Restlaufzeit WA' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '42' . ';' . 'Maximale Haltbarkeit' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '43' . ';' . 'Chargen Pflicht?' . ';' . '0' . "\n";                                  // (maximum)Length
        $csvString = $csvString . '44' . ';' . 'S/N Erfassung WE?' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '45' . ';' . 'S/N Erfassung WA?' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '46' . ';' . 'Einzelpreis' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '47' . ';' . 'Zolltarifnummer' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '48' . ';' . 'Ursprungsland' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '49' . ';' . 'Hersteller' . ';' . $placeholder . "\n";                                  // (maximum)Length
        $csvString = $csvString . '50' . ';' . 'Bemerkung' . ';' . $placeholder . "\n";                                  // (maximum)Length
    }

    private function writeFile(Context $context)
    {
        $criteria = $this->addCriteriaFilter(new Criteria());

        /**
         * @var EntitySearchResult $entities
         */
        $entities = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return;
        }

        $exportData = [];
        /**
         * @var OrderEntity $order
         */
        foreach($entities as $order){
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
                $fileContent = $this->generateDetails($exportData, $orderNumber, $i);
                file_put_contents($this->todaysFolderPath . '/' . $orderNumber . '/' . $this->companyID . '-' . $orderNumber . '-' . $product->getPosition() . '-details.csv',$fileContent);    
                $i++;
            }
            $fileContent = $this->generateHeader($exportData, $orderNumber);
            file_put_contents($this->todaysFolderPath . '/' . $orderNumber . '/' . $this->companyID . '-' . $orderNumber . '-header.csv',$fileContent);
        }
    }

    private function addCriteriaFilter(Criteria $criteria): Criteria
    {//uncomment this for only picking orders of the last day
        $yesterday = $this->createDateFromString('yesterday');

        $now = $this->createDateFromString('now');

        $criteria->addFilter(new RangeFilter('orderDate', [
            RangeFilter::GTE => $yesterday,
            RangeFilter::LTE => $now
        ]));

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

    private function generateHeader(array $associativeArray, string $orderNumber): string
    {
        $placeholder = '';
        $csvString = '';
        $csvString = $csvString . 'Nr.' . ';' . 'Feldname' . ';' . 'Wert' . "\n";                                           // (maximum)Length
        $csvString = $csvString . '1' . ';' . 'Kennung' . ';' . $this->companyID . "\n";                                    //30
        $csvString = $csvString . '2' . ';' . 'Auftragsnummer Kunde' . ';' . $orderNumber . "\n";                           //25
        $csvString = $csvString . '3' . ';' . 'Bereitstelldatum' . ';' . $placeholder . "\n";                               //8
        $csvString = $csvString . '4' . ';' . 'Bereitstelluhrzeit' . ';' . $placeholder . "\n";                             //6
        $csvString = $csvString . '5' . ';' . 'Referenz 1' . ';' . $placeholder . "\n";                                     //30
        $csvString = $csvString . '6' . ';' . 'Referenz 2' . ';' . $placeholder . "\n";                                     //30
        $csvString = $csvString . '7' . ';' . 'Referenz 3' . ';' . $placeholder . "\n";                                     //30
        $csvString = $csvString . '8' . ';' . 'Name 1, Kunde' . ';' . $this->properyAccessor->getValue($associativeArray, '[firstNameCustomer]') . "\n";                                //35
        $csvString = $csvString . '9' . ';' . 'Name 2, Kunde' . ';' . $this->properyAccessor->getValue($associativeArray, '[lastNameCustomer]') . "\n";                                 //35
        $csvString = $csvString . '10' . ';' . 'Name 3, Kunde' . ';' . $placeholder . "\n";                                 //35
        $csvString = $csvString . '11' . ';' . 'Straße, Kunde' . ';' .  $this->properyAccessor->getValue($associativeArray, '[streetCustomer]') . "\n";                                 //45
        $csvString = $csvString . '12' . ';' . 'PLZ, Kunde' . ';' . $this->properyAccessor->getValue($associativeArray, '[zipCodeCustomer]') . "\n";                                    //10
        $csvString = $csvString . '13' . ';' . 'Ort, Kunde' . ';' . $this->properyAccessor->getValue($associativeArray, '[cityCustomer]') . "\n";                                       //35
        $csvString = $csvString . '14' . ';' . 'Land, Kunde' . ';' . 'DE' . "\n";                                           //3
        $csvString = $csvString . '15' . ';' . 'Name 1, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[firstNameDelivery]') . "\n";                     //35
        $csvString = $csvString . '16' . ';' . 'Name 2, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[lastNameDelivery]') . "\n";                      //35
        $csvString = $csvString . '17' . ';' . 'Name 3, Lieferanschrift' . ';' . $placeholder . "\n";                       //35
        $csvString = $csvString . '18' . ';' . 'Straße, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[streetDelivery]') . "\n";                        //45
        $csvString = $csvString . '19' . ';' . 'PLZ, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[zipCodeDelivery]') . "\n";                          //10
        $csvString = $csvString . '20' . ';' . 'Ort, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[cityDelivery]') . "\n";                             //35
        $csvString = $csvString . '21' . ';' . 'Land, Lieferanschrift' . ';' . 'DE' . "\n";                                 //3
        $csvString = $csvString . '22' . ';' . 'Mailadresse, Lieferanschrift' . ';' . $this->properyAccessor->getValue($associativeArray, '[eMail]') . "\n";                  //55
        $csvString = $csvString . '23' . ';' . 'Telefon, Lieferanschrift' . ';' . $placeholder . "\n";                      //20
        $csvString = $csvString . '24' . ';' . 'Fixtermindatum' . ';' . $placeholder . "\n";                                //8
        $csvString = $csvString . '25' . ';' . 'Fixterminuhrzeit' . ';' . $placeholder . "\n";                              //6
        $csvString = $csvString . '26' . ';' . 'Speditionshinweis 1' . ';' . $placeholder . "\n";                           //35
        $csvString = $csvString . '27' . ';' . 'Speditionshinweis 1, Zusatzhinweis' . ';' . $placeholder . "\n";            //30
        $csvString = $csvString . '28' . ';' . 'Speditionshinweis 2' . ';' . $placeholder . "\n";                           //35
        $csvString = $csvString . '29' . ';' . 'Speditionshinweis 2, Zusatzhinweis' . ';' . $placeholder . "\n";            //30
        $csvString = $csvString . '30' . ';' . 'Frankatur' . ';' . $placeholder . "\n";                                     //3
        $csvString = $csvString . '31' . ';' . 'Frankatur, Zusatzinformation' . ';' . $placeholder . "\n";                  //25
        $csvString = $csvString . '32' . ';' . 'Warenwert' . ';' . $this->getOrderValue($associativeArray) . "\n";          //7.4
        $csvString = $csvString . '33' . ';' . 'Versandart LFS' . ';' . $placeholder . "\n";                                //4
        $csvString = $csvString . '34' . ';' . 'Servicecode LFS' . ';' . $placeholder . "\n";                               //4
        $csvString = $csvString . '35' . ';' . 'Tour LFS' . ';' . $placeholder . "\n";                                      //6
        $csvString = $csvString . '36' . ';' . 'Schnittstelle LFS' . ';' . '1' . "\n";                                      //1
        $csvString = $csvString . '37' . ';' . 'Priorität' . ';' . '02' . "\n";                                             //2


        return $csvString;
    }
    private function generateDetails(array $associativeArray, string $orderNumber, int $i): string
    {
        $accessstring = '[' . $i . ']';
        /** @var OrderLineItemEntity $product */
        $product = $this->properyAccessor->getValue($associativeArray, $accessstring);
        $placeholder = '';
        $csvString = '';
        $csvString = $csvString . 'Nr.' . ';' . 'Feldname' . ';' . 'Wert' . "\n";                                           // (maximum)Length
        $csvString = $csvString . '1' . ';' . 'Kennung' . ';' . $this->companyID . "\n";                                    //30
        $csvString = $csvString . '2' . ';' . 'Auftragsnummer Kunde' . ';' . $orderNumber . "\n";                           //25
        $csvString = $csvString . '3' . ';' . 'Auftragspositionsnummer Kunde' . ';' . $product->getPosition() . "\n";                            //6
        $csvString = $csvString . '4' . ';' . 'Artikelnummer' . ';' . $this->getArticleNumber($product) . "\n";             //28
        $csvString = $csvString . '5' . ';' . 'Gesamtmenge in Basismengeneinheit' . ';' . $product->getQuantity() . "\n";              //8.3
        $csvString = $csvString . '6' . ';' . 'Externe NVE' . ';' . $placeholder . "\n";                                    //46
        $csvString = $csvString . '7' . ';' . 'MHD' . ';' . $placeholder . "\n";                                            //8
        $csvString = $csvString . '8' . ';' . 'Charge' . ';' . $placeholder . "\n";                                         //15
        $csvString = $csvString . '9' . ';' . 'Qualitätsstatus' . ';' . $placeholder . "\n";                                //2
        $csvString = $csvString . '10' . ';' . 'Seriennummern' . ';' . $placeholder . "\n";                                 // Delimiter |
        $csvString = $csvString . '11' . ';' . 'Referenz 1' . ';' . $placeholder . "\n";                                    //30
        $csvString = $csvString . '12' . ';' . 'Referenz 2' . ';' . $placeholder . "\n";                                    //30
        $csvString = $csvString . '13' . ';' . 'Referenz 3' . ';' . $placeholder . "\n";                                    //30

        return $csvString;
    }

    private function getArticleNumber(OrderLineItemEntity $product): string
    {
        $payload = $product->getPayload();
        return $this->properyAccessor->getValue($payload, '[productNumber]');
    }

    private function getOrderValue(array $associativeArray): string //7.4
    {
        $orderValue = 0;
        for ($i = 0; $i < count($associativeArray)-11; $i++)
        {
            $product = $associativeArray[$i];
            $orderValue += $this->properyAccessor->getValue($product, 'totalPrice');
        }
        return strval($orderValue);
    }
}