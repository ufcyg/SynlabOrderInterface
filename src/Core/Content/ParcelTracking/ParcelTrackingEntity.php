<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Content\ParcelTracking;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ParcelTrackingEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;
    /**
     * @var string
     */
    protected $service;
    /**
     * @var int
     */
    protected $position;
    /**
     * @var string
     */
    protected $trackingNumber;

    /** Get the value of orderId @return  string */
    public function getOrderId() { return $this->orderId; }

    /** Get the value of service @return  string */
    public function getService() { return $this->service; }

    /** Get the value of position @return  int */
    public function getPosition() { return $this->position; }

    /** Get the value of trackingNumber @return string */
    public function getTrackingNumber() { return $this->trackingNumber; }
}