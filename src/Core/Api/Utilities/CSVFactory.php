<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CSVFactory
{
    /** @var PropertyAccess $propertyAccessor */
    private $properyAccessor;
    /** @var string $companyID */
    private $companyID;
    /** @var OrderInterfaceRepositoryContainer $repositoryContainer */
    private $repositoryContainer;
    /** @var Context $currentContext */
    private $currentContext;
    public function __construct(string $companyID, OrderInterfaceRepositoryContainer $repositoryContainer)
    {
        $this->properyAccessor = PropertyAccess::createPropertyAccessor();
        $this->companyID = $companyID;
        $this->repositoryContainer = $repositoryContainer;
    }

    public function generateArticlebase(string $csvString, ProductEntity $product, Context $context): string
    {
        $this->currentContext = $context;
        $customFields = $this->getProductCustomField($product);
        $placeholder = '';
        // $csvString = $csvString . 'Nr.' . ';' . 'Feldname' . ';' . 'Wert' . "\n";                                    // (maximum)Length
        $csvString = $csvString . $this->companyID . '.Artikelstamm' . ';';              // Kennung* (maximum)Length
        $csvString = $csvString . $product->getProductNumber() . ';';                   // Artikelnummer* (28)
        $csvString = $csvString . $placeholder . ';';                                  // Matchcode (28)Length
        $csvString = $csvString . $this->getProductName($product) . ';';                       // Artikelbezeichnung 1* (30)Length
        $csvString = $csvString . $placeholder . ';';                       // Artikelbezeichnung 2 (30)Length
        $csvString = $csvString . $placeholder . ';';                       // Artikelbezeichnung 3 (30)Length
        $csvString = $csvString . $placeholder . ';';                                // Warengruppe (6)Length
        $csvString = $csvString . $placeholder . ';';                         // Basismengeneinheit* (3)Length
        $csvString = $csvString . $placeholder . ';';   // Basismengeneinheit, Gewicht in KG, netto (9.5)Length
        $csvString = $csvString . $product->getWeight() . ';'; // Basismengeneinheit, Gewicht in KG, brutto (9.5)Length
        $csvString = $csvString . $product->getLength() . ';';           // Basismengeneinheit, Länge in mm (5.2)Length
        $csvString = $csvString . $product->getWidth() . ';';          // Basismengeneinheit, Breite in mm (5.2)Length
        $csvString = $csvString . $product->getHeight() . ';';            // Basismengeneinheit, Höhe in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';     // Verpackungseinheit (VE) Mengeneinheit (3)Length
        $csvString = $csvString . $product->getPurchaseUnit() . ';';                                  // VE Menge (8.3)Length
        $csvString = $csvString . $placeholder . ';';     // VE Mengeneinheit Gewicht in KG, netto (9.5)Length
        $csvString = $csvString . $placeholder . ';';    // VE Mengeneinheit Gewicht in KG, brutto (9.5)Length
        $csvString = $csvString . $placeholder . ';';             // VE Mengeneinheit, Länge in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';            // VE Mengeneinheit, Breite in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';              // VE Mengeneinheit, Höhe in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';                       // Lademittel (LM) Typ (3)Length
        $csvString = $csvString . $placeholder . ';';                                  // LM Menge (8.3)Length
        $csvString = $csvString . $placeholder . ';';    // LM Mengeneinheit, Gewicht in KG, netto (9.5)Length
        $csvString = $csvString . $placeholder . ';';   // LM Mengeneinheit, Gewicht in KG, brutto (9.5)Length
        $csvString = $csvString . $placeholder . ';';             // LM Mengeneinheit, Länge in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';            // LM Mengeneinheit, Breite in mm (5.2)Length
        $csvString = $csvString . $placeholder . ';';              // (LM Mengeneinheit, Höhe in mm 5.2)Length
        $csvString = $csvString . $product->getEan() . ';';                              // EAN Nummer 1 (14)Length
        $csvString = $csvString . $placeholder . ';';                              // EAN Nummer 2 (14)Length
        $csvString = $csvString . $placeholder . ';';                              // EAN Nummer 3 (14)Length
        $csvString = $csvString . $placeholder . ';';                              // EAN Nummer 4 (14)Length
        $csvString = $csvString . $placeholder . ';';                              // EAN Nummer 5 (14)Length
        $csvString = $csvString . $placeholder . ';';                             // EAN Nummer VE (14)Length
        $csvString = $csvString . $placeholder . ';';                      // Lief.Artikelnummer 1 (18)Length
        $csvString = $csvString . $placeholder . ';';                      // Lief.Artikelnummer 2 (18)Length
        $csvString = $csvString . $placeholder . ';';                      // Lief.Artikelnummer 3 (18)Length
        $csvString = $csvString . $placeholder . ';';                      // Lief.Artikelnummer 4 (18)Length
        $csvString = $csvString . $placeholder . ';';                      // Lief.Artikelnummer 5 (18)Length
        $csvString = $csvString . $placeholder . ';';                              // MHD Pflicht? (1)Length
        if($customFields != null)
        {
            $csvString = $csvString . $customFields['custom_rieck_properties_MHD_WE'] . ';';                      // MHD Restlaufzeit, WE (5)Length
            $csvString = $csvString . $customFields['custom_rieck_properties_MHD_WA'] . ';';                       // MHD Restlaufzeit WA (5)Length
            $csvString = $csvString . $customFields['custom_rieck_properties_MHD'] . ';';                      // Maximale Haltbarkeit (5)Length
        }        
        $csvString = $csvString . '0' . ';';                                   // Chargen Pflicht? (1)Length
        $csvString = $csvString . $placeholder . ';';                         // S/N Erfassung WE? (1)Length
        $csvString = $csvString . $placeholder . ';';                         // S/N Erfassung WA? (1)Length
        $csvString = $csvString . $placeholder . ';';                               // Einzelpreis (7.4)Length
        $csvString = $csvString . $placeholder . ';';                           // Zolltarifnummer (25)Length
        $csvString = $csvString . $placeholder . ';';                             // Ursprungsland (3)Length
        $csvString = $csvString . $this->getManufacturerName($product) . ';';                                // Hersteller (15)Length
        $csvString = $csvString . $placeholder . ';';                                 // Bemerkung (78)Length
        $csvString = $csvString . "\n";
        
        return $csvString;
    }
    private function getManufacturerName(ProductEntity $product):string
    {
        $manufacturerID = $product->getManufacturerId();
        $manufacturerTranslationRepository = $this->repositoryContainer->getManufacturerTranslation();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productManufacturerId',$manufacturerID));
        $manufacturer = $manufacturerTranslationRepository->search($criteria,$this->currentContext);
        $manufacturer = $manufacturer->first();
        $manufacturerName = $manufacturer->getName();
        return $manufacturerName;
    }
    
    private function getProductTranslation(ProductEntity $product):ProductTranslationEntity
    {
        $productID = $product->getId();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId',$productID));
        /** @var EntityRepositoryInterface $productTranslationRepository */
        $productTranslationRepository = $this->repositoryContainer->getProductTranslation();

        $entities = $productTranslationRepository->search($criteria,$this->currentContext);
        $context = $this->currentContext;
        /** @var ProductTranslationEntity $translationEntity */
        foreach($entities as $translationEntity)
        {// TODO language
            $translationEntity;
        }
        return $translationEntity;
    }
    private function getProductName(ProductEntity $product):string
    {//maxlength 30
        $translationEntity = $this->getProductTranslation($product);
        return $translationEntity->getName();
    }
    private function getProductCustomField(ProductEntity $product)
    {//maxlength 30
        /** @var ProductTranslationEntity $translationEntity */
        $translationEntity = $this->getProductTranslation($product);
        return $translationEntity->getCustomFields();
    }
    public function generateHeader(array $associativeArray, string $orderNumber): string
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
    public function generateDetails(array $associativeArray, string $orderNumber, int $i): string
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
    private function getArticleNumber(OrderLineItemEntity $product): string
    {
        $payload = $product->getPayload();
        return $this->properyAccessor->getValue($payload, '[productNumber]');
    }
}