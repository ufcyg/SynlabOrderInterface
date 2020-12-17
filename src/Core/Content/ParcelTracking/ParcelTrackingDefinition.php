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

    protected function defineFields(): FieldCollection
    {

        /*
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `as_parcel_tracking` (
              `id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `service` VARCHAR(4) NOT NULL,
              `position` VARCHAR(6) NOT NULL,
              `trackingnumber` VARCHAR(46) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        */
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new StringField('service', 'service'))->addFlags(new Required()),
            (new IntField('position', 'position'))->addFlags(new Required()),
            (new StringField('trackingnumber', 'trackingnumber'))->addFlags(new Required())
        ]);
    }
}