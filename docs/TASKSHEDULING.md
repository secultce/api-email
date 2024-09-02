# COMO USAR

Após os container está de rodando então deve seguir os passos

- Criar um CRON no container com definição de horario para disparo de e-mail como por exemplo:  `*  *  *  *  * cd /path-to-your-project && php artisan  schedule:run  >>  /dev/null  2>&1`
- Mais instruções para desenvolvimento é seguir a doc do [Laravel](https://laravel.com/docs/11.x/scheduling)
- Em ambiente de **desenvolvimento** rodar o comando `php artisan  schedule:work`
