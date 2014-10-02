# DoctrinePrefixBundle

This bundle prefixes tables and, if you are using PostgreSQL, sequences with a
string of your choice by changing the metadata of your entities. Prefixes are
good if you need to share a database with tables from another project, or if
you want to name your entities using reserved keywords like `user` or `group`.

## Installation

    composer require roukmoute/doctrine-prefix-bundle
