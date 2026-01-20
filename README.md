# DoctrinePrefixBundle

[![CI](https://github.com/roukmoute/DoctrinePrefixBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/roukmoute/DoctrinePrefixBundle/actions/workflows/ci.yml)

A Symfony bundle that automatically prefixes Doctrine ORM table names, indexes, unique constraints, and PostgreSQL sequences.

Prefixes are useful when you need to:
- Share a database with tables from another project
- Use reserved SQL keywords as entity names (like `user` or `group`)
- Organize tables by application or module

## Requirements

- PHP ^8.1
- Symfony ^6.4 || ^7.0
- Doctrine ORM ^3.0

## Installation

```bash
composer require roukmoute/doctrine-prefix-bundle
```

With Symfony Flex, the bundle is automatically registered.

## Configuration

```yaml
# config/packages/roukmoute_doctrine_prefix.yaml
roukmoute_doctrine_prefix:
    prefix: 'app_'
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `prefix` | string | `''` | The prefix to prepend to names |
| `bundles` | array | `[]` | If set, only entities from these namespaces will be prefixed |
| `encoding` | string | `'UTF-8'` | The encoding for the prefix |

### Example with bundle filtering

```yaml
roukmoute_doctrine_prefix:
    prefix: 'app_'
    bundles:
        - 'App\Entity'
        - 'Acme\BlogBundle\Entity'
```

## What gets prefixed?

| Element | Example (prefix: `app_`) |
|---------|--------------------------|
| Table names | `user` → `app_user` |
| Index names | `idx_email` → `app_idx_email` |
| Unique constraint names | `uniq_email` → `app_uniq_email` |
| Many-to-many join tables | `user_role` → `app_user_role` |
| PostgreSQL sequences | `user_id_seq` → `app_user_id_seq` |

## Example

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    indexes: [new ORM\Index(name: 'idx_email', columns: ['email'])],
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_username', columns: ['username'])]
)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    #[ORM\ManyToMany(targetEntity: Role::class)]
    private Collection $roles;
}
```

With `prefix: 'app_'`, this will generate:
- Table: `app_user`
- Index: `app_idx_email`
- Unique constraint: `app_uniq_username`
- Join table: `app_user_role`

## License

MIT
