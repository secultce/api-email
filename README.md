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


## Instalação

Instruções para instalação da api com os seguintes comandos:

- `cp .env.example .env`
- `docker compose up -d`
- `docker exec php_api composer update`
- `docker exec php_api php artisan key:generate`
- `docker exec php_api php artisan migrate`
- `docker exec php_api chmod -R 777 database`

Acessar na porta 8081

## Conexão para RabbitMQ

Instruções [aqui](https://github.com/secultce/api-email/blob/develop/docs/RABBITMQ.md) 

## Conexão para as tarefas agendadas

Instruções [aqui](https://github.com/secultce/api-email/blob/develop/docs/TASKSHEDULING.md)


### JWT Auth

É usado o pacote jwt-auth (https://jwt-auth.readthedocs.io/en/develop/) para criar tokens de acesso que são enviados nas requisições, para garantir que pessoas não autorizadas acessem os dados retornados nos endpoints usados na api.

### Workers

Para cada novo componente que precise trabalhar com as tarefas de filas por exemplo, deve ser adicionar o comando no arquivo *supervisord.conf* , que se encontra no caminho ***docker/php-fpm/*** e escrever algo como: 

    [program:name_command]
    command=php artisan command:command
    autostart=true
    autorestart=true
    stderr_logfile=/dev/stderr
    stdout_logfile=/dev/stdout