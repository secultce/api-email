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

``version: '3'
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
    external: true``