# API de E-mail — Mapa Cultural do Ceará

## Sobre

Este repositório contém uma API REST em Laravel utilizada para envio de e-mails a agentes culturais do Mapa Cultural do Ceará. A aplicação se conecta ao RabbitMQ, responsável pela mensageria entre os sistemas produtores e os consumidores desta API.

Além dos demais fluxos da aplicação, o consumidor de **importação de inscrições** processa notificações de inscrições importadas e controla a idempotência por `opportunity_id` e `registration_id`, para evitar novos envios em caso de mensagens duplicadas.

## Outros repositórios

- [Mapa Cultural do Ceará V5](https://laravel.com/docs/routing).
- [Mapa Cultural do Ceará V7](https://laravel.com/docs/container).

## Laravel Framework

O Laravel é o framework PHP utilizado pela aplicação. Consulte a [documentação oficial](https://laravel.com/docs/contributions).

## Instalação

Instruções existentes para subir a API:

```bash
cp .env.example .env
docker compose up -d
docker exec php_api composer update
docker exec php_api php artisan key:generate
docker exec php_api php artisan migrate
docker exec php_api chmod -R 777 database
```

A aplicação fica disponível na porta **8081**, conforme a configuração atual do projeto.

## RabbitMQ

As instruções gerais de conexão estão em [docs/RABBITMQ.md](https://github.com/secultce/api-email/blob/develop/docs/RABBITMQ.md).

### Notificação de inscrições importadas

O fluxo de importação utiliza a fila principal `queue_import_registration` e a exchange `exchange_notification`, com routing key `module_import_registration_draft`.

O consumidor Laravel executa o processamento e utiliza a chave única `(opportunity_id, registration_id)` para evitar o envio repetido de notificações da mesma inscrição. A DLQ definitiva `queue_import_registration_dlq` recebe mensagens rejeitadas que não devem ser reprocessadas automaticamente.

### Retry controlado

O retry é permitido **somente quando a falha é comprovadamente anterior ao início de `Mail::send()`**:

- Falha na reserva da inscrição, antes do início do envio.
- Falha de preparação, renderização ou auditoria do e-mail, desde que o estado `failed` tenha sido persistido com sucesso.

A partir da tentativa de envio SMTP, o resultado pode ser incerto. Nesse caso, o consumidor registra `unknown` e **não reenvia automaticamente**. Os estados `sending`, `unknown` e `sent` também não são elegíveis a reenvio automático. Se houver falha ao persistir `failed`, a mensagem segue para tratamento de falha/DLQ, em vez de autorizar um retry potencialmente duplicado.

#### Intervalos e limite de tentativas

| Processamento | Cabeçalho `x-import-registration-retry-count` | Destino após uma falha segura |
| --- | ---: | --- |
| Inicial | 0 ou ausente | Retry 1, após 1 minuto |
| Retry 1 | 1 | Retry 2, após 5 minutos |
| Retry 2 | 2 | Retry 3, após 15 minutos |
| Retry 3 | 3 | DLQ definitiva |

São **até quatro processamentos** na mesma cadeia (o inicial e três retries). O contador é transportado em um **cabeçalho AMQP**; o JSON da mensagem, inclusive `message_id`, não é alterado.

O campo `attempts` do banco contabiliza reservas de processamento e não necessariamente coincide com a quantidade de entregas ou com o cabeçalho AMQP, especialmente quando uma falha ocorre antes da reserva.

#### Encaminhamento seguro para retry

Quando identifica uma falha elegível, o consumidor publica a mensagem na exchange `exchange_notification_retry`, na rota correspondente ao próximo retry. A publicação utiliza mensagem persistente, `mandatory=true` e **publisher confirms**; o `ack()` da mensagem original ocorre somente após confirmar a publicação e verificar que ela não foi devolvida por ausência de roteamento.

Caso a publicação falhe ou seu resultado seja incerto, o consumidor não confirma a mensagem original; o RabbitMQ poderá reentregá-la quando a conexão de consumo se encerrar. A publicação de retry e o `ack()` **não são atômicos**: uma falha entre essas operações pode produzir duplicatas, bloqueadas pela idempotência da aplicação.

#### Configuração

Adicione ao `config/rabbitmq.php`, **preservando as configurações já usadas pelos demais módulos**:

```php
'retry' => [
    'enabled' => env('RABBITMQ_IMPORT_REGISTRATION_RETRY_ENABLED', false),
    'exchange' => 'exchange_notification_retry',
    'routes' => [
        1 => 'module_import_registration_draft_retry_1',
        2 => 'module_import_registration_draft_retry_2',
        3 => 'module_import_registration_draft_retry_3',
    ],
],
```

Inicie com o recurso desligado no `.env`:

```dotenv
RABBITMQ_IMPORT_REGISTRATION_RETRY_ENABLED=false
```

Após implantar e validar as configurações no RabbitMQ, incluindo as filas, bindings e permissões do usuário RabbitMQ do Laravel, habilite:

```dotenv
RABBITMQ_IMPORT_REGISTRATION_RETRY_ENABLED=true
```

Recarregue a configuração e reinicie o worker para aplicar a alteração:

```bash
php artisan optimize:clear
# Se o ambiente utiliza cache de configuração:
php artisan config:cache
# Reinicie o processo correspondente na configuração do Supervisor.
```

Desabilitar a flag interrompe **novos encaminhamentos** para as filas de retry, mas não cancela mensagens que já estejam aguardando TTL; elas continuarão retornando à fila principal.

#### Implantação

1. Confirme a existência da exchange `exchange_notification_retry`, das três filas de retry, das respectivas rotas e da DLQ definitiva.
2. Verifique se o usuário usado pela API tem permissão de publicação na nova exchange e acesso à fila principal.
3. Execute os testes automatizados e os testes integrados em homologação, sem disparar e-mails reais.
4. Habilite a flag, atualize o cache de configuração, reinicie o worker e monitore as filas e os registros de processamento.

Não é necessário recriar filas existentes nem apagar volumes do RabbitMQ.

#### Testes automatizados

Use um banco **exclusivo de testes**, compatível com as migrations existentes:

```bash
php artisan test tests/Feature/ImportRegistrationProcessorTest.php
php artisan test tests/Feature/ImportRegistrationCommandTest.php
php artisan test tests/Unit/ImportRegistrationRetryPublisherTest.php
```

Execute também o teste de concorrência do fluxo de importação:

```bash
RUN_IMPORT_REGISTRATION_CONCURRENCY_TEST=1 \
php artisan test tests/Integration/ImportRegistrationConcurrencyTest.php
```

**Confirme que o teste de concorrência foi executado e aprovado**, e não simplesmente ignorado por falta de configuração.

#### Cenários de homologação

- Falha anterior ao SMTP: mensagem encaminhada ao retry 1; após TTL, retorna à fila principal.
- Falhas seguras recorrentes: percorrem os retries 1, 2 e 3; após o limite, a mensagem vai para a DLQ.
- E-mail enviado: estado `sent`, com `ack()` e sem novo envio.
- Resultado SMTP incerto: estado `unknown`, sem retry automático.
- Mensagem inválida ou cabeçalho de retry incorreto: rejeição para a DLQ.
- Falha na publicação do retry: mensagem original permanece sem ACK.
- Redelivery de mensagem já processada: nenhuma duplicação de e-mail.

Simule falhas em ambiente controlado e com destinatários de teste. Não utilize destinatários reais para provocar erros de SMTP.

#### Monitoramento e limitações

Monitore a fila principal, as três filas de retry, a DLQ e os registros com status `failed`, `sending` e `unknown`.

O mecanismo não garante entrega exatamente uma vez, não recupera automaticamente resultados SMTP incertos e não resolve falhas de publicação no produtor Mapas Culturais. Não reenvie mensagens da DLQ nem altere indiscriminadamente estados `sending` ou `unknown` para `failed`.

## Tarefas agendadas

As instruções de configuração estão em [docs/TASKSHEDULING.md](https://github.com/secultce/api-email/blob/develop/docs/TASKSHEDULING.md).

## Autenticação JWT

A API utiliza o pacote [jwt-auth](https://jwt-auth.readthedocs.io/en/develop/) para gerar tokens de acesso utilizados na autenticação de requisições a endpoints protegidos.

## Workers e Supervisor

Para cada componente que processe tarefas de fila, adicione o comando correspondente ao arquivo `supervisord.conf`, localizado em `docker/php-fpm/`, seguindo o padrão atual do projeto:

```ini
[program:name_command]
command=php artisan command:command
autostart=true
autorestart=true
stderr_logfile=/dev/stderr
stdout_logfile=/dev/stdout
```

## Contribuição

Contribuições são bem-vindas.

## Licença

O projeto utiliza o Laravel, framework de código aberto disponibilizado sob a [licença MIT](https://opensource.org/licenses/MIT). Confirme a licença específica desta aplicação nos arquivos do repositório.
