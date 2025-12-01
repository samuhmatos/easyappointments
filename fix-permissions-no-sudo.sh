#!/bin/bash

# Script para corrigir permissões SEM sudo (quando possível)
# Tenta corrigir o que conseguir, mostra o que precisa de sudo

echo "🔧 Tentando corrigir permissões (sem sudo)..."

# Tentar corrigir arquivos que pertencem ao root
echo "📁 Procurando arquivos com permissões incorretas..."

# Encontrar e tentar corrigir arquivos em assets/
FOUND_FILES=$(find assets/ -user root 2>/dev/null)

if [ -z "$FOUND_FILES" ]; then
    echo "✅ Nenhum arquivo com permissões incorretas encontrado!"
    exit 0
fi

echo "⚠️  Encontrados arquivos que pertencem ao root:"
echo "$FOUND_FILES"
echo ""
echo "❌ Estes arquivos precisam ser corrigidos com sudo."
echo ""
echo "Execute:"
echo "  sudo ./fix-permissions.sh"
echo ""
echo "Ou execute manualmente:"
echo "  sudo chown -R \$USER:\$USER assets/"
echo "  sudo chmod -R u+w assets/"

