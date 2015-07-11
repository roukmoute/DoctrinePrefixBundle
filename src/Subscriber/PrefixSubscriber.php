<?php

namespace Roukmoute\DoctrinePrefixBundle\Subscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Id\BigIntegerIdentityGenerator;
use Doctrine\ORM\Id\AssignedGenerator;

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
        $this->prefix   = mb_convert_encoding($prefix, $encoding);
        $this->bundles  = $bundles;
        $this->encoding = $encoding;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
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

        $entity = strtr($classMetadata->namespace, array('\\' => '\\\\'));
        $filter = (bool)(empty($this->bundles)
                         || new \RegexIterator(new \ArrayIterator($this->bundles), '/' . $entity . '/i'));
        if ($filter) {
            // Generate Table
            $classMetadata->setPrimaryTable(array('name' => $this->addPrefix($classMetadata->getTableName())));

            // Generate indexes
            if (isset($classMetadata->table['indexes'])) {
                foreach ($classMetadata->table['indexes'] as $index => $value) {
                    unset($classMetadata->table['indexes'][$index]);
                    $classMetadata->table['indexes'][$this->addPrefix($index)] = $value;
                }
            }

            foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
                if ($mapping['type'] == \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_MANY
                    && isset($classMetadata->associationMappings[$fieldName]['joinTable']['name'])
                ) {
                    $mappedTableName
                        = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                    $classMetadata->associationMappings[$fieldName]['joinTable']['name']
                        = $this->addPrefix($mappedTableName);
                }
            }

            // Generate Sequence
            $em = $args->getEntityManager();
            $platform = $em->getConnection()->getDatabasePlatform();
            if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSqlPlatform) {
                if ($classMetadata->isIdGeneratorSequence()) {
                    $newDefinition                 = $classMetadata->sequenceGeneratorDefinition;
                    $newDefinition['sequenceName'] = $this->addPrefix($newDefinition['sequenceName']);

                    $classMetadata->setSequenceGeneratorDefinition($newDefinition);
                    if (isset($classMetadata->idGenerator)) {
                        $sequenceGenerator = new \Doctrine\ORM\Id\SequenceGenerator(
                            $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                                $newDefinition,
                                $classMetadata,
                                $platform
                            ),
                            $newDefinition['allocationSize']
                        );
                        $classMetadata->setIdGenerator($sequenceGenerator);
                    }
                } elseif ($classMetadata->isIdGeneratorIdentity()) {
                    $sequenceName = null;
                    $fieldName    = $classMetadata->identifier ? $classMetadata->getSingleIdentifierFieldName() : null;
                    $columnName   = $classMetadata->getSingleIdentifierColumnName();
                    $quoted       = isset($classMetadata->fieldMappings[$fieldName]['quoted'])
                                    || isset($classMetadata->table['quoted']);
                    $sequenceName = $classMetadata->getTableName() . '_' . $columnName . '_seq';
                    $definition   = array('sequenceName' => $platform->fixSchemaElementName($sequenceName));

                    if ($quoted) {
                        $definition['quoted'] = true;
                    }

                    $sequenceName = $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                        $definition,
                        $classMetadata,
                        $platform
                    );
                    $generator = ($fieldName && $classMetadata->fieldMappings[$fieldName]['type'] === 'bigint')
                        ? new BigIntegerIdentityGenerator($sequenceName)
                        : new IdentityGenerator($sequenceName);

                    $classMetadata->setIdGenerator($generator);
                }
            }
        }
    }

    private function addPrefix($name)
    {
        if (strpos($name, $this->prefix) === 0) {
            return $name;
        }

        return $this->prefix . $name;
    }
}
