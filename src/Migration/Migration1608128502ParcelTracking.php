<?php declare(strict_types=1);

namespace SynlabOrderInterface\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1608128502ParcelTracking extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1608128502;
    }

    public function update(Connection $connection): void
    {
        // $connection->executeUpdate('
        //     CREATE TABLE IF NOT EXISTS `as_parcel_tracking` (
        //       `id` BINARY(16) NOT NULL,
        //       `order_id` VARCHAR(255) NOT NULL,
        //       `service` VARCHAR(4) NOT NULL,
        //       `position` VARCHAR(6) NOT NULL,
        //       `trackingnumber` VARCHAR(46) NOT NULL,
        //       `created_at` DATETIME(3) NOT NULL,
        //       
        //       PRIMARY KEY (`id`)
        //     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        // ');
        $connection->exec("CREATE TABLE IF NOT EXISTS `as_parcel_tracking` (
            `id`                BINARY(16) NOT NULL,
            `order_id`          VARCHAR(255) NOT NULL,
            `service`           VARCHAR(4) NOT NULL,
            `position`          VARCHAR(6) NOT NULL,
            `tracking_number`   VARCHAR(46) NOT NULL,
            `created_at`        DATETIME(3) NOT NULL,
            `updated_at`        DATETIME(3) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function updateDestructive(Connection $connection): void
    {
        
    }
}
