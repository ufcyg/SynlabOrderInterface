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

    /** Set the value of orderId @param  string  $orderId @return  self */
    public function setOrderId(string $orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /** Get the value of service @return  string */ 
    public function getService() { return $this->service; }

    /** Set the value of service @param  string  $service @return  self */ 
    public function setService(string $service)
    {
        $this->service = $service;
        return $this;
    }

    /** Get the value of position @return  int */ 
    public function getPosition() { return $this->position; }

    /** Set the value of position @param  int  $position @return  self */ 
    public function setPosition(int $position)
    {
        $this->position = $position;
        return $this;
    }

    /** Get the value of trackingNumber @return  string */ 
    public function getTrackingNumber() { return $this->trackingNumber; }

    /** Set the value of trackingNumber @param  string  $trackingNumber @return  self */ 
    public function setTrackingNumber(string $trackingNumber)
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }
}