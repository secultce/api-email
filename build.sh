#!/bin/bash
VERSION=$(cat version.txt)

docker build --no-cache -t secultceara/api-email:$VERSION -t secultceara/api-email:latest -f docker/prod/php-fpm/Dockerfile .
