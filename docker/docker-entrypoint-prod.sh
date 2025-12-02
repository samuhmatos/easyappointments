#!/bin/bash

# -----------------------------------------------------------------------------
# Easy!Appointments - Docker Entrypoint para Produção
# -----------------------------------------------------------------------------

set -e

# Criar config.php a partir de variáveis de ambiente
cat <<EOF >/var/www/html/config.php
<?php
class Config {
    const BASE_URL              = '${BASE_URL}';
    const LANGUAGE              = '${LANGUAGE}';
    const DEBUG_MODE            = ${DEBUG_MODE};
    const DB_HOST               = '${DB_HOST}';
    const DB_NAME               = '${DB_NAME}';
    const DB_USERNAME           = '${DB_USERNAME}';
    const DB_PASSWORD           = '${DB_PASSWORD}';
    const GOOGLE_SYNC_FEATURE   = ${GOOGLE_SYNC_FEATURE};
    const GOOGLE_CLIENT_ID      = '${GOOGLE_CLIENT_ID}';
    const GOOGLE_CLIENT_SECRET  = '${GOOGLE_CLIENT_SECRET}';
}
EOF

# Configurar email (se necessário)
if [ -n "$MAIL_SMTP_HOST" ]; then
    cat <<EOF >/var/www/html/application/config/email.php
<?php defined('BASEPATH') or exit('No direct script access allowed');

\$config['useragent'] = 'Easy!Appointments';
\$config['protocol'] = '${MAIL_PROTOCOL}';
\$config['mailtype'] = 'html';
\$config['smtp_debug'] = '${MAIL_SMTP_DEBUG}';
\$config['smtp_auth'] = ${MAIL_SMTP_AUTH};
\$config['smtp_host'] = '${MAIL_SMTP_HOST}';
\$config['smtp_user'] = '${MAIL_SMTP_USER}';
\$config['smtp_pass'] = '${MAIL_SMTP_PASS}';
\$config['smtp_crypto'] = '${MAIL_SMTP_CRYPTO}';
\$config['smtp_port'] = ${MAIL_SMTP_PORT};
\$config['from_name'] = '${MAIL_FROM_NAME}';
\$config['from_address'] = '${MAIL_FROM_ADDRESS}';
\$config['reply_to'] = '${MAIL_REPLY_TO_ADDRESS}';
\$config['crlf'] = "\r\n";
\$config['newline'] = "\r\n";
EOF
fi


FILE=/var/www/html/application/config/config.php
STRING="\$config['base_url'] = '${BASE_URL}';"

if [ "$(tail -n 1 "$FILE")" != "$STRING" ]; then
    echo "$STRING" >> "$FILE"
fi

# Iniciar Apache
exec apache2-foreground

