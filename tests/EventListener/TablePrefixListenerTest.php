<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\Tests\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\JoinTableMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Roukmoute\DoctrinePrefixBundle\EventListener\TablePrefixListener;

class TablePrefixListenerTest extends TestCase
{
    public function testGetPrefix(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        self::assertSame('app_', $listener->getPrefix());
    }

    public function testTableNameIsPrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('app_user', $classMetadata->getTableName());
    }

    public function testTableNameIsNotDoublePrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('app_user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('app_user', $classMetadata->getTableName());
    }

    public function testEmptyPrefixDoesNotModifyTableName(): void
    {
        $listener = new TablePrefixListener('', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('user', $classMetadata->getTableName());
    }

    public function testIndexNamesArePrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $classMetadata->table['indexes'] = [
            'idx_email' => ['columns' => ['email']],
            'idx_username' => ['columns' => ['username']],
        ];
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertArrayHasKey('app_idx_email', $classMetadata->table['indexes']);
        self::assertArrayHasKey('app_idx_username', $classMetadata->table['indexes']);
        self::assertArrayNotHasKey('idx_email', $classMetadata->table['indexes']);
        self::assertArrayNotHasKey('idx_username', $classMetadata->table['indexes']);
    }

    public function testManyToManyJoinTableIsPrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');

        $mapping = new ManyToManyOwningSideMapping(
            fieldName: 'roles',
            sourceEntity: \stdClass::class,
            targetEntity: \stdClass::class,
        );
        $mapping->joinTable = new JoinTableMapping('user_role');
        $classMetadata->associationMappings['roles'] = $mapping;

        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('app_user_role', $mapping->joinTable->name);
    }

    public function testBundleFilteringAllowsMatchingNamespace(): void
    {
        $listener = new TablePrefixListener('app_', ['App\\Entity'], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user', 'App\\Entity');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('app_user', $classMetadata->getTableName());
    }

    public function testBundleFilteringSkipsNonMatchingNamespace(): void
    {
        $listener = new TablePrefixListener('app_', ['App\\Entity'], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user', 'Vendor\\Bundle\\Entity');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame('user', $classMetadata->getTableName());
    }

    #[DataProvider('unicodePrefixProvider')]
    public function testUnicodePrefixIsHandledCorrectly(string $prefix, string $tableName, string $expected): void
    {
        $listener = new TablePrefixListener($prefix, [], 'UTF-8');

        $classMetadata = $this->createClassMetadata($tableName);
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        self::assertSame($expected, $classMetadata->getTableName());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function unicodePrefixProvider(): array
    {
        return [
            'ascii prefix' => ['test_', 'user', 'test_user'],
            'unicode prefix' => ['préfixe_', 'user', 'préfixe_user'],
        ];
    }

    /**
     * @return ClassMetadata<object>
     */
    private function createClassMetadata(string $tableName, string $namespace = 'App\\Entity'): ClassMetadata
    {
        /** @var class-string<object> $className */
        $className = $namespace . '\\User';
        $classMetadata = new ClassMetadata($className);
        $classMetadata->setPrimaryTable(['name' => $tableName]);
        $classMetadata->namespace = $namespace;

        return $classMetadata;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function createEventArgs(ClassMetadata $classMetadata): LoadClassMetadataEventArgs
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getQuoteStrategy')->willReturn(new DefaultQuoteStrategy());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        return new LoadClassMetadataEventArgs($classMetadata, $entityManager);
    }
}
