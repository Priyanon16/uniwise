#!/usr/bin/env bash
set -euo pipefail

echo "Dify-related containers:"
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Networks}}' | grep -Ei 'dify|api|worker|web|nginx' || true

echo
echo "Docker networks:"
docker network ls --format 'table {{.Name}}\t{{.Driver}}'

echo
echo "Choose the network used by Dify API/worker and put its exact name in .env as DIFY_NETWORK=..."
