# DoctrinePrefixBundle

This bundle prefixes tables and, if you are using PostgreSQL, sequences with a
string of your choice by changing the metadata of your entities. Prefixes are
good if you need to share a database with tables from another project, or if
you want to name your entities using reserved keywords like `user` or `group`.

## Installation

    composer require roukmoute/doctrine-prefix-bundle

## Configuration

First, you need to register the bundle in your application kernel, like this :

```php
<?php
//app/AppKernel.php
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            …
            new Roukmoute\DoctrinePrefixBundle\RoukmouteDoctrinePrefixBundle()
        );
        …
```

The configuration looks as follows :

```yaml
roukmoute_doctrine_prefix:

    # will be prepended to table and sequence names
    prefix:               sf

    # if set, the prefix will be applied to specified bundles only
    bundles:              []

    # the encoding to convert the prefix to
    encoding:             UTF-8
```
