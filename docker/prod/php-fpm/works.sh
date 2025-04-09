#!/bin/bash
while true
do
    php artisan diligence:consumer
    echo "Worker da PC caiu. Reiniciando em 60s..."
    sleep 60
done
