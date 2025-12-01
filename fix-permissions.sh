#!/bin/bash

# Script para corrigir permissões de arquivos criados pelo Docker
# Execute com: sudo ./fix-permissions.sh

echo "🔧 Corrigindo permissões dos arquivos..."

# Obter o usuário atual (antes do sudo)
if [ -n "$SUDO_USER" ]; then
    REAL_USER="$SUDO_USER"
else
    REAL_USER=$(whoami)
fi

# Obter o grupo do usuário
REAL_GROUP=$(id -gn "$REAL_USER")

echo "📁 Ajustando permissões para: $REAL_USER:$REAL_GROUP"

# Corrigir permissões da pasta assets/vendor
if [ -d "assets/vendor" ]; then
    echo "  - Corrigindo assets/vendor/"
    chown -R "$REAL_USER:$REAL_GROUP" assets/vendor/
    chmod -R u+w assets/vendor/
fi

# Corrigir permissões da pasta assets/css
if [ -d "assets/css" ]; then
    echo "  - Corrigindo assets/css/"
    chown -R "$REAL_USER:$REAL_GROUP" assets/css/
    chmod -R u+w assets/css/
fi

# Corrigir permissões da pasta assets/js
if [ -d "assets/js" ]; then
    echo "  - Corrigindo assets/js/"
    chown -R "$REAL_USER:$REAL_GROUP" assets/js/
    chmod -R u+w assets/js/
fi

# Corrigir permissões da pasta storage (se necessário)
if [ -d "storage" ]; then
    echo "  - Corrigindo storage/"
    chown -R "$REAL_USER:$REAL_GROUP" storage/
    chmod -R 755 storage/
    chmod -R 777 storage/logs storage/cache storage/sessions storage/backups 2>/dev/null || true
fi

# Corrigir permissões de outros arquivos que possam ter sido criados pelo Docker
if [ -d "node_modules" ]; then
    echo "  - Verificando node_modules/"
    find node_modules -user root -exec chown "$REAL_USER:$REAL_GROUP" {} \; 2>/dev/null || true
fi

if [ -d "vendor" ]; then
    echo "  - Verificando vendor/"
    find vendor -user root -exec chown "$REAL_USER:$REAL_GROUP" {} \; 2>/dev/null || true
fi

echo "✅ Permissões corrigidas!"
echo ""
echo "Agora você pode executar: npm start"

