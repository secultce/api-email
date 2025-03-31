# Como usar conexão com RabbitMQ

Para iniciar o serviço RabbitMq na mesma rede que esse repositório usa no docker-compose, deve rodar esse comando.

- Preencher as variáveis de ambiente na sessão do RabbitMQ
- Rodar o comando `docker exec php_api php artisan diligence:consumer`

## ✨ Recuros

Para notificação de e-mail para os recuros deve rodar o seguinte comando:

- `docker exec php_api php artisan rabbitmq:consume-published-recourse-emails`