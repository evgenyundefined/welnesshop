#!/bin/sh
set -eu

: "${SERVER_NAME:=_}"
: "${CERT_NAME:=}"

if [ -z "$CERT_NAME" ]; then
    CERT_NAME="${SERVER_NAME%% *}"
fi

CONFIG=/etc/nginx/conf.d/default.conf
CERT=/etc/letsencrypt/live/$CERT_NAME/fullchain.pem

export SERVER_NAME CERT_NAME

render() {
    template=/etc/nginx/templates/http.conf.template

    if [ -f "$CERT" ]; then
        template=/etc/nginx/templates/https.conf.template
    fi

    envsubst '${SERVER_NAME} ${CERT_NAME}' < "$template" > "$1"
}

# A renewed certificate keeps the same path, so the certificate itself is part
# of what decides whether nginx has to be told to re-read anything.
fingerprint() {
    {
        cat "$1"
        cat "$CERT" 2>/dev/null || true
    } | md5sum
}

render "$CONFIG"

if [ "$SERVER_NAME" = "_" ]; then
    echo '[nginx] SERVER_NAME is not set, serving plain http without a certificate'
elif [ -f "$CERT" ]; then
    echo "[nginx] serving https for $SERVER_NAME"
else
    echo "[nginx] no certificate for $CERT_NAME yet, serving http until certbot issues one"
fi

# Picks up the first certificate and every renewal without a restart.
(
    known=$(fingerprint "$CONFIG")

    while sleep 60; do
        render /tmp/default.conf.next
        current=$(fingerprint /tmp/default.conf.next)

        [ "$current" = "$known" ] && continue

        cp /tmp/default.conf.next "$CONFIG"

        if nginx -t 2>/dev/null; then
            nginx -s reload
            known=$current
            echo '[nginx] configuration reloaded'
        else
            echo '[nginx] refused the generated configuration, keeping the running one'
            nginx -t
        fi
    done
) &

exec nginx -g 'daemon off;'
