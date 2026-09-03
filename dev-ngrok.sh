#!/bin/bash

PROJECT_DIR="$HOME/projects/marketplace-analytics"

cd "$PROJECT_DIR" || exit 1

echo "Menunggu ngrok..."

for i in {1..20}; do
    NGROK_URL=$(curl -s http://127.0.0.1:4040/api/tunnels \
        | grep -o '"public_url":"https://[^"]*"' \
        | head -1 \
        | cut -d'"' -f4)

    if [ -n "$NGROK_URL" ]; then
        break
    fi

    sleep 1
done

if [ -z "$NGROK_URL" ]; then
    echo "ERROR: ngrok tidak ditemukan."
    echo "Jalankan dulu: ngrok http 80"
    exit 1
fi

echo ""
echo "======================================"
echo " NGROK URL: $NGROK_URL"
echo "======================================"
echo ""

export VITE_PUBLIC_URL="$NGROK_URL"

sail exec -e VITE_PUBLIC_URL="$NGROK_URL" laravel.test npm run dev
