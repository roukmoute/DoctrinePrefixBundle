<?php

namespace Roukmoute\DoctrinePrefixBundle\Subscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

class PrefixSubscriber implements \Doctrine\Common\EventSubscriber
{
    protected $prefix = '';
    protected $bundles = [];
    protected $encoding = '';

    /**
     * @param $prefix
     * @param $bundles
     * @param $encoding
     */
    public function __construct($prefix, $bundles, $encoding)
    {
        $this->prefix   = mb_convert_case($prefix, MB_CASE_TITLE, $encoding);
        $this->bundles  = $bundles;
        $this->encoding = $encoding;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array('loadClassMetadata');
    }

    /**
     * @param LoadClassMetadataEventArgs $args
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /** @var \Doctrine\ORM\Mapping\ClassMetadata $classMetadata */
        $classMetadata = $args->getClassMetadata();

        // Do not re-apply the prefix in an inheritance hierarchy.
        if ($classMetadata->isInheritanceTypeSingleTable() && !$classMetadata->isRootEntity()) {
            return;
        }

        $filter
            = (bool)(empty($this->bundles)
                     || new \RegexIterator(new \ArrayIterator($this->bundles), '/' . $classMetadata->namespace . '/i'));
        if ($filter) {
            // Generate Table
            $classMetadata->setPrimaryTable(array('name' => $this->prefix . $classMetadata->getTableName()));

            foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
                if ($mapping['type'] == \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_MANY
                    && isset($classMetadata->associationMappings[$fieldName]['joinTable']['name'])
                ) {
                    $mappedTableName
                        = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                    $classMetadata->associationMappings[$fieldName]['joinTable']['name']
                        = $this->prefix . $mappedTableName;
                }
            }

            // Generate Sequence
            $em = $args->getEntityManager();
            if ($em->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSqlPlatform
                && $classMetadata->isIdGeneratorSequence()
            ) {
                $newDefinition                 = $classMetadata->sequenceGeneratorDefinition;
                $newDefinition['sequenceName'] = $this->prefix . $newDefinition['sequenceName'];

                $classMetadata->setSequenceGeneratorDefinition($newDefinition);
                if (isset($classMetadata->idGenerator)) {
                    $sequenceGenerator = new \Doctrine\ORM\Id\SequenceGenerator(
                        $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                            $newDefinition,
                            $classMetadata,
                            $em->getConnection()->getDatabasePlatform()),
                        $newDefinition['allocationSize']
                    );
                    $classMetadata->setIdGenerator($sequenceGenerator);
                }
            }
        }
    }
}
