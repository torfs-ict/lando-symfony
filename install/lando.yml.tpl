recipe: lemp

config:
  webroot: public
  database: mysql

proxy:
  - nginx
  - mailhog
  - phpmyadmin
  - elk

services:
  appserver:
    type: php:7.1
    via: nginx
    ssl: true
    install_dependencies_as_root:
      - 'bash $LANDO_MOUNT/.lando/install/blackfire.sh'
      - 'pecl install igbinary'
      - 'docker-php-ext-enable igbinary'
      - 'docker-php-ext-install sockets'
      - 'echo "xdebug.remote_host=$LANDO_HOST_IP" >> /usr/local/etc/php/conf.d/zzzz-blackfire.ini'
    install_dependencies_as_me:
      - "composer install --working-dir=$LANDO_MOUNT"
    config:
      server: .lando/install/nginx.conf
      conf: .lando/install/php.ini
    xdebug: true
    composer:
      hirak/prestissimo: "^0.3"
  blackfire:
      type: compose
      services:
        image: blackfire/blackfire
        command: blackfire-agent
  database:
    portforward: 3306
  phpmyadmin:
    type: phpmyadmin
    hosts:
      - database
  elk:
    type: compose
    portforward: 5601
    services:
      image: sebp/elk
      command: /usr/local/bin/start.sh
      volumes:
        - data_elasticsearch:/var/lib/elasticsearch
      ports:
        - '5601:5601'
      environment:
        - ES_CONNECT_RETRY=120
    overrides:
      volumes:
        data_elasticsearch: {}
  node:
    type: node
    install_dependencies_as_root:
      # Upgrade Yarn
      - "curl -o- -L https://yarnpkg.com/install.sh | bash"
    install_dependencies_as_me:
      - "yarn --cwd=$LANDO_MOUNT"
  mailhog:
    type: mailhog
    hogfrom:
      - appserver
  memcached:
    type: memcached
    mem: 256
  chrome-headless:
    type: compose
    services:
      image: justinribeiro/chrome-headless
      command: google-chrome --headless --disable-gpu --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --no-sandbox
  unoconv:
    type: compose
    services:
      image: zrrrzzt/docker-unoconv-webservice
      command: 'bash /app/.lando/install/unoconv.sh'
  pdftk:
    type: compose
    services:
      image: torfsict/docker-pdftk-webservice
      command: php /app/bin/console server:run 0.0.0.0:80

tooling:
  sf:
    service: appserver
    description: Run Symfony console commands
    cmd: php bin/console
  diff:
    service: appserver
    description: Generate a database migration by comparing your current database to your mapping information
    cmd: php bin/console doctrine:migrations:diff
  migrate:
    service: appserver
    description: Execute a database migration to a specified version or the latest available version
    cmd: php bin/console doctrine:migrations:migrate
  cache:
    service: appserver
    description: Clears the Symfony cache
    cmd: php bin/console cache:clear
  yarn:
    service: node
    description: Run the Yarn package manager
    cmd: yarn
  blackfire:
    service: appserver
    description: Profile a Symfony console command using blackfire
    cmd: blackfire run php bin/console
  encore:
    service: node
    description: Runs Webpack Encore
    cmd: node_modules/.bin/encore