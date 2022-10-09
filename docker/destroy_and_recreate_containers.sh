#!/bin/bash

set -e

# Stop and remove my old containers and their data (Note: this is destructive and I'm only doing it in my local development environment)
docker-compose stop db www
docker-compose rm db www
sudo rm -rf db www

docker-compose up -d
