<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use DateInterval;
use DateTime;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class OrderInterfaceUtils
{
    public function __construct()
    {
        
    }
    /// boolean checks
    public function newOrdersCk(EntityRepositoryInterface $orderRepository): bool
    {
        $criteria = new Criteria();
        $this->addCriteriaFilterDate($criteria);

        /** @var EntitySearchResult $entities */
        $entities = $orderRepository->search($criteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return false;
        }
        return true;
    }

    /// filter criteria
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
    public function createDateFromString(string $daytime): string
    {
        $timeStamp = new DateTime();
        $timeStamp->add(DateInterval::createFromDateString($daytime));
        $timeStamp = $timeStamp->format('Y-m-d H:i:s.u');
        $timeStamp = substr($timeStamp, 0, strlen($timeStamp) - 3);

        return $timeStamp;
    }


    public function createDateFolder()
    {
        $timeStamp = new DateTime();
        $timeStamp = $timeStamp->format('d-m-Y');
        $this->todaysFolderPath = '../custom/plugins/SynlabOrderInterface/SubmittedOrders/' . $timeStamp;
        if (!file_exists($this->todaysFolderPath)) {
            mkdir($this->todaysFolderPath, 0777, true);
        }    
    }




    public function getProducts(): EntitySearchResult
    {
        $criteria = new Criteria();
        /** @var EntitySearchResult */
        return $this->productsRepository->search($criteria, Context::createDefaultContext());
    }

    public function getOrderEntities(Context $context, bool $applyFilter)
    {
        $criteria = $applyFilter ? $this->addCriteriaFilterDate(new Criteria()) : new Criteria();

        /** @var EntitySearchResult $entities */
        $entities = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if(count($entities) === 0){
            return 0;
        }
        return $entities;
    }

    public function getOrderedProducts(string $orderID): array
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

    public function getDeliveryAddress(string $orderID, string $eMailAddress): array
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

    public function getDeliveryEntityID(string $orderEntityID): string
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
}