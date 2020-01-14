<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\BigIntegerIdentityGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

class PrefixSubscriber implements EventSubscriber
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
        $this->prefix = mb_convert_encoding($prefix, $encoding);
        $this->bundles = $bundles;
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
        return ['loadClassMetadata'];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $args->getClassMetadata();

        if (!$this->isFiltered($classMetadata)) {
            return;
        }

        $this->generateTable($classMetadata);
        $this->generateIndexes($classMetadata);
        $this->generateSequence($args, $classMetadata);
    }

    private function addPrefix($name)
    {
        if (mb_strpos($name, $this->prefix) === 0) {
            return $name;
        }

        return $this->prefix . $name;
    }

    private function isFiltered(ClassMetadata $classMetadata): bool
    {
        return (bool) (empty($this->bundles)
            || iterator_to_array(
                new \RegexIterator(
                    new \ArrayIterator($this->bundles),
                    sprintf('/%s/i', strtr($classMetadata->namespace, ['\\' => '\\\\']))
                )
            )
        );
    }

    private function generateTable(ClassMetadata $classMetadata): void
    {
        $classMetadata->setPrimaryTable(['name' => $this->addPrefix($classMetadata->getTableName())]);
    }

    private function generateIndexes(ClassMetadata $classMetadata): void
    {
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
    }

    private function generateSequence(LoadClassMetadataEventArgs $args, ClassMetadata $classMetadata): void
    {
        $em = $args->getEntityManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSqlPlatform) {
            if ($classMetadata->isIdGeneratorSequence()) {
                $newDefinition = $classMetadata->sequenceGeneratorDefinition;
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
                $fieldName = $classMetadata->identifier ? $classMetadata->getSingleIdentifierFieldName() : null;
                $columnName = $classMetadata->getSingleIdentifierColumnName();
                $quoted = isset($classMetadata->fieldMappings[$fieldName]['quoted'])
                    || isset($classMetadata->table['quoted']);
                $sequenceName = $classMetadata->getTableName() . '_' . $columnName . '_seq';
                $definition = ['sequenceName' => $platform->fixSchemaElementName($sequenceName)];

                if ($quoted) {
                    $definition['quoted'] = true;
                }

                $sequenceName = $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                    $definition,
                    $classMetadata,
                    $platform
                )
                ;
                $generator = ($fieldName && $classMetadata->fieldMappings[$fieldName]['type'] === 'bigint')
                    ? new BigIntegerIdentityGenerator($sequenceName)
                    : new IdentityGenerator($sequenceName);

                $classMetadata->setIdGenerator($generator);
            }
        }
    }
}
