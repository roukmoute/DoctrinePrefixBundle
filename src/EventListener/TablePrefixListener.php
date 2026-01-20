<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;

class TablePrefixListener
{
    /** @var list<string> */
    protected array $bundles = [];

    /**
     * @param list<string> $bundles
     */
    public function __construct(
        protected string $prefix,
        array $bundles,
        string $encoding,
    ) {
        $this->prefix = mb_convert_encoding($prefix, $encoding);
        $this->bundles = $bundles;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        /** @var ClassMetadata<object> $classMetadata */
        $classMetadata = $args->getClassMetadata();

        if (!$this->isFiltered($classMetadata)) {
            return;
        }

        $this->prefixTable($classMetadata);
        $this->prefixIndexes($classMetadata);
        $this->prefixUniqueConstraints($classMetadata);
        $this->prefixManyToManyJoinTables($classMetadata);
        $this->prefixSequence($args, $classMetadata);
    }

    private function addPrefix(string $name): string
    {
        if ($this->prefix === '' || str_starts_with($name, $this->prefix)) {
            return $name;
        }

        return $this->prefix . $name;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function isFiltered(ClassMetadata $classMetadata): bool
    {
        if (empty($this->bundles)) {
            return true;
        }

        $namespace = $classMetadata->namespace ?? '';

        foreach ($this->bundles as $bundle) {
            if (str_contains($namespace, $bundle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function prefixTable(ClassMetadata $classMetadata): void
    {
        $classMetadata->setPrimaryTable(['name' => $this->addPrefix($classMetadata->getTableName())]);
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function prefixIndexes(ClassMetadata $classMetadata): void
    {
        if (!isset($classMetadata->table['indexes'])) {
            return;
        }

        $prefixedIndexes = [];
        foreach ($classMetadata->table['indexes'] as $indexName => $indexConfig) {
            $prefixedIndexes[$this->addPrefix((string) $indexName)] = $indexConfig;
        }
        $classMetadata->table['indexes'] = $prefixedIndexes;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function prefixUniqueConstraints(ClassMetadata $classMetadata): void
    {
        if (!isset($classMetadata->table['uniqueConstraints'])) {
            return;
        }

        $prefixedConstraints = [];
        foreach ($classMetadata->table['uniqueConstraints'] as $constraintName => $constraintConfig) {
            $prefixedConstraints[$this->addPrefix((string) $constraintName)] = $constraintConfig;
        }
        $classMetadata->table['uniqueConstraints'] = $prefixedConstraints;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function prefixManyToManyJoinTables(ClassMetadata $classMetadata): void
    {
        foreach ($classMetadata->associationMappings as $mapping) {
            if (!$mapping instanceof ManyToManyOwningSideMapping) {
                continue;
            }

            $mapping->joinTable->name = $this->addPrefix($mapping->joinTable->name);
        }
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function prefixSequence(LoadClassMetadataEventArgs $args, ClassMetadata $classMetadata): void
    {
        if (!$classMetadata->isIdGeneratorSequence()) {
            return;
        }

        $sequenceDefinition = $classMetadata->sequenceGeneratorDefinition;
        $sequenceDefinition['sequenceName'] = $this->addPrefix($sequenceDefinition['sequenceName']);
        $classMetadata->setSequenceGeneratorDefinition($sequenceDefinition);

        $em = $args->getEntityManager();
        $platform = $em->getConnection()->getDatabasePlatform();

        $sequenceName = $em->getConfiguration()
            ->getQuoteStrategy()
            ->getSequenceName($sequenceDefinition, $classMetadata, $platform);

        $classMetadata->setIdGenerator(new SequenceGenerator(
            $sequenceName,
            (int) $sequenceDefinition['allocationSize'],
        ));
    }
}
