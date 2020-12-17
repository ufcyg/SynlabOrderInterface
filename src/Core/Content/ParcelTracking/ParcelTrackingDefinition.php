<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Content\ParcelTracking;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ParcelTrackingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'as_parcel_tracking';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ParcelTrackingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ParcelTrackingCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('order_id', 'orderId'))->addFlags(new Required()),
            (new StringField('service', 'service'))->addFlags(new Required()),
            (new IntField('position', 'position'))->addFlags(new Required()),
            (new StringField('tracking_number', 'trackingNumber'))->addFlags(new Required())
        ]);
    }
}