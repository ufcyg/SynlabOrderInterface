<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class OrderInterfaceRepositoryContainer
{
    /** @var EntityRepositoryInterface $orderRepository */
    public $orderRepository;
    /** @var EntityRepositoryInterface $orderDeliveryAddressRepository */
    public $orderDeliveryAddressRepository;
    /** @var EntityRepositoryInterface $lineItems */
    public $lineItemsRepository;
    /** @var EntityRepositoryInterface $productsRepository */
    public $productsRepository;
    /** @var EntityRepositoryInterface $orderDeliveryRepository */
    public $orderDeliveryRepository;
    public function __construct(EntityRepositoryInterface $orderRepository,
                                EntityRepositoryInterface $orderDeliveryAddressRepository,
                                EntityRepositoryInterface $lineItemsRepository,
                                EntityRepositoryInterface $productsRepository,
                                EntityRepositoryInterface $orderDeliveryRepository

    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryAddressRepository = $orderDeliveryAddressRepository;
        $this->lineItemsRepository = $lineItemsRepository;
        $this->productsRepository = $productsRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;        
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
}