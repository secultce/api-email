# Como usar conexão com RabbitMQ

Para iniciar o serviço RabbitMq na mesma rede que esse repositório usa no docker-compose, deve rodar esse comando.

- Preencher as variáveis de ambiente na sessão do RabbitMQ
- Rodar o comando `docker exec php_api php artisan diligence:consumer`

## ✨ Recuros

Para notificação de e-mail para os recuros deve rodar o seguinte comando:

- `docker exec php_api php artisan rabbitmq:consume-published-recourse-emails`

## ✨ Observação

Todos os demais comandos de consumer estão no arquivos `supervisord.conf`

## 🧱 Construindo Workers
Com o comando: `make:custom-files nome_do_arquivo` , criará 03 arquivos padrões na seguinte estrutura
 - app/Console/Commands
 - app/Mail
 - resources/views/emails

Todos será o nome que foi passado por parâmetro, sendo assim bastará editar o necessário para a sua atividade.

## Comando uteis para Rabbitmq

1. **Limpar Filas**:
   - Liste as filas:
     ```bash
     rabbitmqctl list_queues
     ```
   - Delete filas desnecessárias:
     ```bash
     rabbitmqctl delete_queue nome_da_fila
     ```
     - Delete TODAS as filas desnecessárias:
       ```bash
           rabbitmqctl list_queues | awk '{ print $1 }' | xargs -L1 rabbitmqctl delete_queue
       ```
