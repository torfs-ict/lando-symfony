# Lando environment builder

This [composer](https://getcomposer.org) package allows you to easily configure
a [Lando](https://docs.devwithlando.io) development environment for
[Symfony](https://symfony.com) projects.

Instead of having to copy `.lando.yml` for every project, you can start with a
clean `lando.yml` file with only a few required settings. Using the builder you
can then generate the full `.lando.yml` file which will actually be used to spin
up your development environment.

## Development environment info

### Included services

1. Nginx webserver
2. PHP 7.1 (Xdebug ready)
3. MySQL database
4. Node.js container
5. Blackfire profiler
6. phpMyAdmin
7. ELK stack
8. Mailhog
9. Memcached
10. Headless chrome
11. Unoconv
12. PDFtk

### Included Lando tooling

- `sf`: Run Symfony console commands
  - target: PHP
  - alias for: `$ php bin/console`
  - example: `$ lando sf about`
- `diff`: Generate a database migration by comparing your current database to your mapping information
  - target: PHP
  - alias for: `$ php bin/console doctrine:migrations:diff`
  - example: `$ lando diff`
- `migrate`: Execute a database migration to a specified version or the latest available version
  - target: PHP
  - alias for: `$ php bin/console doctrine:migrations:migrate`
  - example: `$ lando migrate prev`
- `cache`: Clears the Symfony cache
  - target: PHP
  - alias for: `$ php bin/console cache:clear`
  - example: `$ lando cache`
- `warmup`: Warms up an empty cache
  - target: PHP
  - alias for: `$ php bin/console cache:warmup`
  - example: `$ lando warmup`
- `blackfire`: Profile a **Symfony console command** using blackfire
  - target: PHP
  - alias for: `$ blackfire run php bin/console`
  - example: `$ lando blackfire about`
- `yarn`: Run the Yarn package manager
  - target: Node.js
  - alias for: `$ yarn`
  - example: `$ lando yarn add bootstrap`
- `encore`: Runs Webpack Encore
  - target: Node.js
  - alias for: `$ node_modules/.bin/encore`
  - example: `$ lando encore dev --watch`

## Usage

### Installing

Install the package with composer, and make sure to have a `.env` file in
your project root containing all variables defined in [.env.dist](samples/.env.dist).

```bash
$ composer require --dev torfs-ict/lando-symfony
```

### Setting up

A sample `lando.yml` file can be found in [lando.yml.dist](samples/lando.yml.dist).
If this file doesn't exist, you can have the builder create one for you.

```yaml
name: my-project

proxy:
  nginx:
    - "my-project.dev.local.torfs.org"
  mailhog:
    - "my-project.mhg.local.torfs.org"
  phpmyadmin:
    - "my-project.pma.local.torfs.org"
  elk:
    - 'my-project.elk.local.torfs.org:5601'

tooling:
  worker:
    service: appserver
    description: Runs the background worker broker
    cmd: php bin/console app:worker --no-debug -vvv
```

### Required settings

The only mandatory settings are the project `name` and `proxy` domains. Should your
project have removed a pre-defined service, you should omit this from the `proxy`
configuration as well.

### Customizing the environment

When building the environment, your `lando.yml` file will be merged with the
default configuration file, so you can customize as you see fit.

### Removing services

Should you want to remove a service, you can simple set it to `null` in your
`lando.yml` file. The example below illustrates how to remove the `elk` service.

Do note that if you remove a service which requires a proxy domain, you need to
remote the service from the `proxy` settings as well.

```yaml
name: my-project

proxy:
  nginx:
    - "my-project.dev.local.torfs.org"
  mailhog:
    - "my-project.mhg.local.torfs.org"
  phpmyadmin:
    - "my-project.pma.local.torfs.org"

services:
  elk: ~
```

### Building the environment

Once your `lando.yml` and `.env` files are in place (or at least the `.env` file)
you can generate the actual `.lando.yml` file by running the build script.

```bash
$ vendor/bin/lando
```