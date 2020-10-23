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
    /** @var string $folderRoot */
    private $folderRoot;
    /** @var string $todaysFolderPath */
    private $todaysFolderPath;
    
    public function __construct()
    {
        $this->folderRoot = '../custom/plugins/SynlabOrderInterface/SubmittedOrders/';
        $this->todaysFolderPath = '';
    }
    /// boolean checks
    public function newOrdersCk(EntityRepositoryInterface $orderRepository, Context $context): bool
    {
        $criteria = new Criteria();
        $this->addCriteriaFilterDate($criteria);

        /** @var EntitySearchResult $entities */
        $entities = $orderRepository->search($criteria, $context);

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
        $this->todaysFolderPath = $this->folderRoot . $timeStamp;
        if (!file_exists($this->todaysFolderPath)) {
            mkdir($this->todaysFolderPath, 0777, true);
        }    
    }

    public function createOrderFolder(string $orderNumber,&$folderPath)
    {
        if ($this->todaysFolderPath === '')
        {
            $this->createDateFolder();
        }
        if (!file_exists($this->todaysFolderPath . '/' . $orderNumber)) {
            mkdir($this->todaysFolderPath . '/' . $orderNumber, 0777, true);
        }
        $folderPath = $this->todaysFolderPath;
    }

    public function getProducts(EntityRepositoryInterface $productsRepository, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        /** @var EntitySearchResult */
        return $productsRepository->search($criteria, $context);
    }

    public function getOrderEntities(EntityRepositoryInterface $orderRepository, bool $applyFilter, Context $context)
    {
        $criteria = $applyFilter ? $this->addCriteriaFilterDate(new Criteria()) : new Criteria();

        /** @var EntitySearchResult $entities */
        $entities = $orderRepository->search($criteria, $context);

        if(count($entities) === 0){
            return 0;
        }
        return $entities;
    }

    public function getOrderedProducts(EntityRepositoryInterface $lineItemsRepository, string $orderID, Context $context): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderID));
        /** @var EntitySearchResult $lineItemEntity */
        $lineItemEntity = $lineItemsRepository->search($criteria, $context);

        $lineItemArray = [];
        $i = 0;
        foreach($lineItemEntity as $lineItem)
        {
            $lineItemArray[$i] = $lineItem;
            $i++;
        }

        return $lineItemArray;
    }

    public function getDeliveryAddress(EntityRepositoryInterface $orderDeliveryAddressRepository, string $orderID, string $eMailAddress, Context $context): array
    {
        /** @var Criteria $criteria */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderID));
        /** @var EntitySearchResult $addressEntity */
        $addressEntity = $orderDeliveryAddressRepository->search($criteria, $context);

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

    public function getDeliveryEntityID(EntityRepositoryInterface $orderDeliveryRepository, string $orderEntityID, Context $context): string
    {
        $criteria = new Criteria();
        $entities = $orderDeliveryRepository->search($criteria, $context);

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