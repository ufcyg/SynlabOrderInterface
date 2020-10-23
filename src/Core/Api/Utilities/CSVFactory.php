<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CSVFactory
{
    /** @var PropertyAccess $propertyAccessor */
    private $properyAccessor;
    /** @var string $companyID */
    private $companyID;

    public function __construct(string $companyID)
    {
        $this->properyAccessor = PropertyAccess::createPropertyAccessor();
        $this->companyID = $companyID;
    }

    public function generateArticlebase()
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