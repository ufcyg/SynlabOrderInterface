<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/*

Since only classes which inherit 'AbstactController' have a $container variable through which the DBAL can be accessed easily 
we use our own repository container for the parts of the plugin where those repositories cannot be accessed easier.

*/
class OrderInterfaceRepositoryContainer
{
    /** @var EntityRepositoryInterface $manufacturerTranslation */
    private $manufacturerTranslation;
    /** @var EntityRepositoryInterface $productTranslation */
    private $productTranslation;
    /** @var EntityRepositoryInterface $countryRepository */
    private $countryRepository;
    /** @var EntityRepositoryInterface $unitTranslation */
    private $unitTranslation;

    public function __construct(EntityRepositoryInterface $manufacturerTranslation,
                                EntityRepositoryInterface $productTranslation,
                                EntityRepositoryInterface $countryRepository,
                                EntityRepositoryInterface $unitTranslation

    )
    {  
        $this->manufacturerTranslation = $manufacturerTranslation;   
        $this->productTranslation = $productTranslation;
        $this->countryRepository = $countryRepository;
        $this->unitTranslation = $unitTranslation;
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
}