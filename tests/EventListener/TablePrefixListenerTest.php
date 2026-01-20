<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\Tests\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Roukmoute\DoctrinePrefixBundle\EventListener\TablePrefixListener;

class TablePrefixListenerTest extends TestCase
{
    public function testGetPrefix(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $this->assertSame('app_', $listener->getPrefix());
    }

    public function testTableNameIsPrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('app_user', $classMetadata->getTableName());
    }

    public function testTableNameIsNotDoublePrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('app_user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('app_user', $classMetadata->getTableName());
    }

    public function testEmptyPrefixDoesNotModifyTableName(): void
    {
        $listener = new TablePrefixListener('', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('user', $classMetadata->getTableName());
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

        $this->assertArrayHasKey('app_idx_email', $classMetadata->table['indexes']);
        $this->assertArrayHasKey('app_idx_username', $classMetadata->table['indexes']);
        $this->assertArrayNotHasKey('idx_email', $classMetadata->table['indexes']);
        $this->assertArrayNotHasKey('idx_username', $classMetadata->table['indexes']);
    }

    public function testManyToManyJoinTableIsPrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $classMetadata->associationMappings['roles'] = [
            'type' => ClassMetadata::MANY_TO_MANY,
            'joinTable' => ['name' => 'user_role'],
        ];
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('app_user_role', $classMetadata->associationMappings['roles']['joinTable']['name']);
    }

    public function testBundleFilteringAllowsMatchingNamespace(): void
    {
        $listener = new TablePrefixListener('app_', ['App\\Entity'], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user', 'App\\Entity');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('app_user', $classMetadata->getTableName());
    }

    public function testBundleFilteringSkipsNonMatchingNamespace(): void
    {
        $listener = new TablePrefixListener('app_', ['App\\Entity'], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user', 'Vendor\\Bundle\\Entity');
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('user', $classMetadata->getTableName());
    }

    public function testPostgreSQLSequenceIsPrefixed(): void
    {
        $listener = new TablePrefixListener('app_', [], 'UTF-8');

        $classMetadata = $this->createClassMetadata('user');
        $classMetadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_SEQUENCE);
        $classMetadata->setSequenceGeneratorDefinition([
            'sequenceName' => 'user_id_seq',
            'allocationSize' => 1,
        ]);
        $eventArgs = $this->createEventArgs($classMetadata, PostgreSQLPlatform::class);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame('app_user_id_seq', $classMetadata->sequenceGeneratorDefinition['sequenceName']);
    }

    #[DataProvider('unicodePrefixProvider')]
    public function testUnicodePrefixIsHandledCorrectly(string $prefix, string $tableName, string $expected): void
    {
        $listener = new TablePrefixListener($prefix, [], 'UTF-8');

        $classMetadata = $this->createClassMetadata($tableName);
        $eventArgs = $this->createEventArgs($classMetadata);

        $listener->loadClassMetadata($eventArgs);

        $this->assertSame($expected, $classMetadata->getTableName());
    }

    public static function unicodePrefixProvider(): array
    {
        return [
            'ascii prefix' => ['test_', 'user', 'test_user'],
            'unicode prefix' => ['préfixe_', 'user', 'préfixe_user'],
            'emoji prefix' => ['🚀_', 'user', '🚀_user'],
        ];
    }

    private function createClassMetadata(string $tableName, string $namespace = 'App\\Entity'): ClassMetadata
    {
        $classMetadata = new ClassMetadata($namespace . '\\User');
        $classMetadata->setPrimaryTable(['name' => $tableName]);
        $classMetadata->namespace = $namespace;

        return $classMetadata;
    }

    private function createEventArgs(ClassMetadata $classMetadata, string $platformClass = AbstractPlatform::class): LoadClassMetadataEventArgs
    {
        $platform = $this->createMock($platformClass);

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
