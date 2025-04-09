#!/bin/bash
while true
do
    php artisan rabbitmq:consume-published-recourse-emails
    echo "Worker da publicação caiu. Reiniciando em 60s..."
    sleep 60
done
