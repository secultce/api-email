<p align="center"><a href="https://mapacultural.secult.ce.gov.br/" target="_blank"><img src="https://mapacultural.secult.ce.gov.br/assets/img/logo-ceara-2396208294-1680122696.png" width="400" alt="MC Ceará Logo"></a></p>


## Sobre

Esse repositório é uma API REST e será utilizado para disparo de e-mail de agentes culturais do Mapa Cultural do Ceará. O mesmo terá conexão com o serviço do [Rabbitmq](https://github.com/rabbitmq) que servirá como a mensageria e fila para todas as requisições de um produtor para essa API que será o consumidor

## Outros Repositórios

- [Mapa Cultural do Ceará V5](https://laravel.com/docs/routing).
- [Mpa Cultural do Ceará V7](https://laravel.com/docs/container).

## Laravel Framewwork

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling - [Laravel](https://laravel.com).

### Outros Links

- **[Laravel documentation](https://laravel.com/docs/contributions).**

## Contribuição

Sempre é bem vinda!

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## RabbitMq

Para iniciar o serviço RabbitMq na mesma rede que esse repositório usa no docker-compose, deve rodar esse comando.

``
version: '3'
services:
  rabbitmq:
    image: rabbitmq:3-management
    container_name: rabbitmq
    hostname: rabbitmq
    ports:
      - "5672:5672"
      - "15672:15672"
    networks:
      - lab
    volumes:
      - $PWD/storage/rabbitmq1:/var/lib/rabbitmq
    environment:
      - RABBITMQ_ERLANG_COOKIE=This_is_my_secret_phrase
      - RABBITMQ_DEFAULT_USER=mqadmin
      - RABBITMQ_DEFAULT_PASS=Admin123XX_
      - CLUSTERED=true
networks:
  lab:
    external: true
``

## Instalação

Instruções para instalação da api com os seguintes comandos:

- `cp .env.example .env`

Adicionar no arquivo .env

FORWARD_DB_PORT=
DATA_PATH_HOST=~/.sail/data
APP_CODE_PATH_HOST=./
APP_CODE_PATH_CONTAINER=/var/www/html
APP_CODE_PATH_PROJECT=/var/www
APP_CODE_CONTAINER_FLAG=:cached

##### NGINX #################################################

NGINX_HOST_HTTP_PORT=8081
NGINX_HOST_HTTPS_PORT=443
NGINX_HOST_LOG_PATH=./docker/logs/nginx/
NGINX_SITES_PATH=./docker/nginx/sites/
NGINX_PHP_UPSTREAM_CONTAINER=fpm-email
NGINX_PHP_UPSTREAM_PORT=9000
NGINX_SSL_PATH=./docker/nginx/ssl/

- `docker compose up -d`
- `docker exec php_api composer update`
- `docker exec php_api php artisan key:generate`
- `docker exec php_api php artisan migrate`
- `docker exec php_api chmod -R 777 database`

Acessar na porta 8081

### JWT Auth

É usado o pacote jwt-auth (https://jwt-auth.readthedocs.io/en/develop/) para criar tokens de acesso que são enviados nas requisições, para garantir que pessoas não autorizadas acessem os dados retornados nos endpoints usados na api.
