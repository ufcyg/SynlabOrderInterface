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
}