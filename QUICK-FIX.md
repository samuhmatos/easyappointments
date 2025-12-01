# 🔧 Correção Rápida de Permissões

## Problema
Arquivos criados pelo Docker pertencem ao usuário `root`, impedindo o Gulp de escrever neles.

## Solução Rápida

Execute este comando único:

```bash
sudo chown -R $USER:$USER assets/ && sudo chmod -R u+w assets/
```

Ou use o script completo:

```bash
sudo ./fix-permissions.sh
```

## Arquivos que precisam ser corrigidos

Atualmente, estes arquivos pertencem ao root:
- `assets/css/pages/update.css`
- `assets/css/layouts/message_layout.min.css`

## Após corrigir

Execute `npm start` normalmente. O Gulp vai recriar os arquivos com as permissões corretas.

