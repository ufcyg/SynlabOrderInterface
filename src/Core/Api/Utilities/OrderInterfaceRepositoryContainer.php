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
    public function __construct(EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository,
                                EntityRepositoryInterface $orderDeliveryRepository,
                                EntityRepositoryInterface $manufacturerTranslation,
                                EntityRepositoryInterface $productTranslation

    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;     
        $this->manufacturerTranslation = $manufacturerTranslation;   
        $this->productTranslation = $productTranslation;
    }

    /**
     * Get the value of orderRepository
     */ 
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /**
     * Get the value of orderDeliveryAddressRepository
     */ 
    public function getOrderDeliveryAddressRepository()
    {
        return $this->orderDeliveryAddressRepository;
    }

    /**
     * Get the value of lineItemsRepository
     */ 
    public function getLineItemsRepository()
    {
        return $this->lineItemsRepository;
    }

    /**
     * Get the value of productsRepository
     */ 
    public function getProductsRepository()
    {
        return $this->productsRepository;
    }

    /**
     * Get the value of orderDeliveryRepository
     */ 
    public function getOrderDeliveryRepository()
    {
        return $this->orderDeliveryRepository;
    }

    /**
     * Get the value of manufacturerTranslation
     */ 
    public function getManufacturerTranslation()
    {
        return $this->manufacturerTranslation;
    }

    /**
     * Set the value of manufacturerTranslation
     *
     * @return  self
     */ 
    public function setManufacturerTranslation($manufacturerTranslation)
    {
        $this->manufacturerTranslation = $manufacturerTranslation;

        return $this;
    }

    /**
     * Get the value of productTranslation
     */ 
    public function getProductTranslation()
    {
        return $this->productTranslation;
    }

    /**
     * Set the value of productTranslation
     *
     * @return  self
     */ 
    public function setProductTranslation($productTranslation)
    {
        $this->productTranslation = $productTranslation;

        return $this;
    }
}