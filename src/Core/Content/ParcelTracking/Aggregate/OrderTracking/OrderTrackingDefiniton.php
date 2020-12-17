<?php declare(strict_types=1);

// namespace SynlabOrderInterface\Core\Content\ParcelTracking\Aggregate\OrderTracking;

// use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
// use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
// use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
// use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
// use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

// class OrderTrackingDefiniton extends MappingEntityDefinition
// {
//     public function getEntityName(): string
//     {
//         return '';
//     }

//     protected function defineFields(): FieldCollection
//     {
//         return new FieldCollection([
//             (new FkField('bundle_id', 'bundleId', BundleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
//             (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
//             (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
//             new OneToManyAssociationField('bundle', 'bundle_id', BundleDefinition::class),
//             new OneToManyAssociationField('product', 'product_id', ProductDefinition::class),
//             new CreatedAtField()
//         ]);
//     }
// }