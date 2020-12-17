<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class OrderInterfaceRepositoryContainer
{
    /** @var EntityRepositoryInterface $orderRepository */
    private $orderRepository;
    /** @var EntityRepositoryInterface $orderDeliveryAddressRepository */
    private $orderDeliveryAddressRepository;
    /** @var EntityRepositoryInterface $lineItems */
    private $lineItemsRepository;
    /** @var EntityRepositoryInterface $productsRepository */
    private $productsRepository;
    /** @var EntityRepositoryInterface $orderDeliveryRepository */
    private $orderDeliveryRepository;
    /** @var EntityRepositoryInterface $manufacturerTranslation */
    private $manufacturerTranslation;
    /** @var EntityRepositoryInterface $productTranslation */
    private $productTranslation;
    /** @var EntityRepositoryInterface $customerRepository */
    private $customerRepository;
    /** @var EntityRepositoryInterface $languageRepository */
    private $languageRepository;
    /** @var EntityRepositoryInterface $countryRepository */
    private $countryRepository;
    /** @var EntityRepositoryInterface $unitTranslation */
    private $unitTranslation;
    /** @var EntityRepositoryInterface $parcelTracking */
    private $parcelTracking;

    public function __construct(EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository,
                                EntityRepositoryInterface $orderDeliveryRepository,
                                EntityRepositoryInterface $manufacturerTranslation,
                                EntityRepositoryInterface $productTranslation,
                                EntityRepositoryInterface $customerRepository,
                                EntityRepositoryInterface $languageRepository,
                                EntityRepositoryInterface $countryRepository,
                                EntityRepositoryInterface $unitTranslation,
                                EntityRepositoryInterface $parcelTracking

    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;     
        $this->manufacturerTranslation = $manufacturerTranslation;   
        $this->productTranslation = $productTranslation;
        $this->customerRepository = $customerRepository;
        $this->languageRepository = $languageRepository;
        $this->countryRepository = $countryRepository;
        $this->unitTranslation = $unitTranslation;
        $this->parcelTracking = $parcelTracking;
    }

    /** Get the value of orderRepository */
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /** Get the value of orderDeliveryAddressRepository */
    public function getOrderDeliveryAddressRepository()
    {
        return $this->orderDeliveryAddressRepository;
    }

    /** Get the value of lineItemsRepository */
    public function getLineItemsRepository()
    {
        return $this->lineItemsRepository;
    }

    /** Get the value of productsRepository */
    public function getProductsRepository()
    {
        return $this->productsRepository;
    }

    /** Get the value of orderDeliveryRepository */
    public function getOrderDeliveryRepository()
    {
        return $this->orderDeliveryRepository;
    }

    /** Get the value of manufacturerTranslation */
    public function getManufacturerTranslation()
    {
        return $this->manufacturerTranslation;
    }

    /** Get the value of productTranslation */
    public function getProductTranslation()
    {
        return $this->productTranslation;
    }

    /** Get the value of customerRepository */
    public function getCustomerRepository()
    {
        return $this->customerRepository;
    }

    /** Get the value of languageRepository */ 
    public function getLanguageRepository()
    {
        return $this->languageRepository;
    }

    /** Get the value of countryRepository */ 
    public function getCountryRepository()
    {
        return $this->countryRepository;
    }

    /** Get the value of unitTranslation */ 
    public function getUnitTranslation()
    {
        return $this->unitTranslation;
    }

    /**
     * Get the value of parcelTracking
     */ 
    public function getParcelTracking()
    {
        return $this->parcelTracking;
    }
}