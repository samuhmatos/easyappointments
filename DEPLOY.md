# Guia de Deploy - EasyAppointments

Este documento explica como funciona o processo de deploy do EasyAppointments e as diferentes formas de fazer o deploy.

## 📋 Visão Geral

O EasyAppointments é uma aplicação web PHP baseada no framework CodeIgniter que permite agendamento de compromissos online. O deploy pode ser feito de duas formas principais:

1. **Deploy Tradicional** (servidor web tradicional)
2. **Deploy com Docker** (containerização)

---

## 🚀 Deploy Tradicional (Produção)

### Requisitos do Servidor

- **Servidor Web**: Apache 2.4+ ou Nginx
- **PHP**: 8.1 ou superior (recomendado 8.2+)
- **Banco de Dados**: MySQL 5.7+ ou MariaDB
- **Extensões PHP necessárias**:
  - curl
  - json
  - mbstring
  - gd
  - simplexml
  - fileinfo
  - mysqli
  - pdo_mysql

### Passos para Deploy

#### 1. Preparar o Ambiente

```bash
# No servidor, certifique-se de ter:
# - Apache/Nginx configurado
# - PHP 8.1+ instalado
# - MySQL/MariaDB instalado
# - Composer instalado (opcional, mas recomendado)
```

#### 2. Fazer Build dos Assets

Antes de fazer upload, você precisa compilar os assets JavaScript/CSS:

```bash
# No seu ambiente local ou CI/CD
npm install
npm run build  # ou npx gulp build

# Isso gera os arquivos compilados na pasta assets/
```

#### 3. Upload dos Arquivos

```bash
# Faça upload de todos os arquivos para o servidor
# Exemplo: /var/www/html/easyappointments/ ou /public_html/appointments/
```

#### 4. Configurar Permissões

```bash
# A pasta storage precisa ter permissões de escrita
chmod -R 777 storage
# OU (mais seguro)
chown -R www-data:www-data storage
chmod -R 755 storage
```

#### 5. Configurar o Banco de Dados

```bash
# Crie um banco de dados MySQL
mysql -u root -p
CREATE DATABASE easyappointments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ea_user'@'localhost' IDENTIFIED BY 'senha_segura';
GRANT ALL PRIVILEGES ON easyappointments.* TO 'ea_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 6. Configurar o Arquivo config.php

```bash
# Copie o arquivo de exemplo
cp config-sample.php config.php

# Edite o config.php com suas configurações
```

**Exemplo de config.php para produção:**

```php
<?php
class Config
{
    // ------------------------------------------------------------------------
    // GENERAL SETTINGS
    // ------------------------------------------------------------------------
    
    const BASE_URL = 'https://seusite.com.br/appointments'; // SEM barra no final
    const LANGUAGE = 'portuguese'; // ou 'english'
    const DEBUG_MODE = false; // SEMPRE false em produção

    // ------------------------------------------------------------------------
    // DATABASE SETTINGS
    // ------------------------------------------------------------------------
    
    const DB_HOST = 'localhost'; // ou IP do servidor MySQL
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = 'ea_user';
    const DB_PASSWORD = 'senha_segura';

    // ------------------------------------------------------------------------
    // GOOGLE CALENDAR SYNC
    // ------------------------------------------------------------------------
    
    const GOOGLE_SYNC_FEATURE = false; // true se quiser usar
    const GOOGLE_CLIENT_ID = '';
    const GOOGLE_CLIENT_SECRET = '';
}
```

#### 7. Instalar Dependências (se necessário)

```bash
# Se você não fez upload da pasta vendor/
composer install --no-dev --optimize-autoloader

# Se você não fez upload da pasta node_modules/ (geralmente não é necessário)
# npm install --production
```

#### 8. Configurar o Servidor Web

**Apache (.htaccess já deve estar incluído):**

```apache
# O projeto já vem com .htaccess configurado
# Certifique-se de que o mod_rewrite está habilitado
```

**Nginx (exemplo de configuração):**

```nginx
server {
    listen 80;
    server_name seusite.com.br;
    root /var/www/html/easyappointments;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

#### 9. Acessar o Wizard de Instalação

1. Abra o navegador em: `https://seusite.com.br/appointments`
2. O sistema detectará que é a primeira instalação
3. Preencha os dados do administrador e da empresa
4. Clique em "Install"
5. Pronto! O sistema está instalado

---

## 🐳 Deploy com Docker (Desenvolvimento)

O projeto já vem com configuração Docker para desenvolvimento local.

### Estrutura Docker

O `docker-compose.yml` inclui:

- **php-fpm**: Container PHP 8.4 com todas as extensões necessárias
- **nginx**: Servidor web Nginx
- **mysql**: Banco de dados MySQL 8.0
- **phpmyadmin**: Interface web para MySQL (porta 8080)
- **mailpit**: Servidor de email para testes (porta 8025)
- **swagger-ui**: Documentação da API (porta 8000)
- **baikal**: Servidor CalDAV para testes (porta 8100)
- **openldap**: Servidor LDAP para testes (porta 389)

### Como Usar (Desenvolvimento)

```bash
# 1. Configure o config.php (veja exemplo abaixo)
cp config-sample.php config.php

# 2. Inicie os containers
docker compose up -d

# 3. Acesse a aplicação
# http://localhost (aplicação)
# http://localhost:8080 (phpMyAdmin)
# http://localhost:8025 (Mailpit)
```

**config.php para Docker:**

```php
<?php
class Config
{
    const BASE_URL = 'http://localhost';
    const LANGUAGE = 'english';
    const DEBUG_MODE = true; // true para desenvolvimento

    const DB_HOST = 'mysql';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = 'user';
    const DB_PASSWORD = 'password';

    const GOOGLE_SYNC_FEATURE = false;
    const GOOGLE_CLIENT_ID = '';
    const GOOGLE_CLIENT_SECRET = '';
}
```

### Script de Inicialização

O container PHP-FPM executa automaticamente o script `docker/php-fpm/start-container` que:

1. Configura permissões do Git
2. Define permissões da pasta `storage`
3. Instala dependências do Composer (se necessário)
4. Instala dependências do NPM (se necessário)
5. Compila os assets (se necessário)
6. Inicia o PHP-FPM

---

## 🏭 Deploy com Docker (Produção)

**⚠️ IMPORTANTE**: O `docker-compose.yml` fornecido é apenas para **desenvolvimento**.

### Quando NÃO usar o repositório oficial `easyappointments-docker`

O repositório oficial (https://github.com/alextselegidis/easyappointments-docker) **baixa releases oficiais** do GitHub. Se você vai fazer **modificações no código**, você **NÃO deve usar** esse repositório porque:

- Ele baixa código oficial via ZIP (não suas modificações)
- Suas customizações não serão incluídas
- É útil apenas para usar versões oficiais sem modificações

### Criando sua Própria Imagem Docker para Produção

Para usar seu código modificado, você precisa criar sua própria imagem Docker. O projeto já inclui arquivos prontos:

1. **`Dockerfile.prod`** - Dockerfile de produção baseado no seu código
2. **`docker-compose.prod.yml`** - Orquestração para produção
3. **`docker-entrypoint-prod.sh`** - Script de inicialização

### Como usar os arquivos de produção

```bash
# 1. Construir a imagem
docker compose -f docker-compose.prod.yml build

# 2. Iniciar os serviços
docker compose -f docker-compose.prod.yml up -d

# 3. Verificar logs
docker compose -f docker-compose.prod.yml logs -f

# 4. Parar os serviços
docker compose -f docker-compose.prod.yml down
```

### Configuração via Variáveis de Ambiente

O `docker-compose.prod.yml` permite configurar tudo via variáveis de ambiente. Você pode:

- Editar diretamente o arquivo `docker-compose.prod.yml`
- Criar um arquivo `.env` na raiz do projeto
- Passar variáveis via linha de comando

**Exemplo de `.env`:**

```env
BASE_URL=https://seusite.com.br
LANGUAGE=portuguese
DEBUG_MODE=FALSE
DB_HOST=mysql
DB_NAME=easyappointments
DB_USERNAME=root
DB_PASSWORD=senha_segura
```

### Diferenças entre Desenvolvimento e Produção

| Aspecto | Desenvolvimento | Produção |
|---------|----------------|----------|
| Arquivo | `docker-compose.yml` | `docker-compose.prod.yml` |
| Base | PHP-FPM + Nginx | PHP-Apache |
| Assets | Compilados no container | Compilados no build |
| Debug | Habilitado | Desabilitado |
| Código | Volume montado (hot reload) | Copiado na imagem |
| Dependências | Instaladas no startup | Instaladas no build |

---

## 📦 Processo de Build

### Assets Frontend

O projeto usa **Gulp** para compilar assets:

```bash
# Desenvolvimento (watch mode)
npm start
# ou
npx gulp

# Produção (build único)
npm run build
# ou
npx gulp build
```

**O que é compilado:**
- JavaScript (Babel + minificação)
- CSS/SCSS (Sass + minificação)
- Assets são gerados na pasta `assets/`

### Dependências

```bash
# PHP (Composer)
composer install --no-dev --optimize-autoloader

# JavaScript (NPM)
npm install --production
npm run build
```

---

## 🔄 Atualização

Para atualizar uma instalação existente:

1. **Backup do banco de dados**
2. **Backup da pasta `storage/`** (contém uploads e logs)
3. **Substituir arquivos** (exceto `config.php` e `storage/`)
4. **Executar migrações** (se houver)
5. **Limpar cache** (se aplicável)

---

## 🔐 Segurança em Produção

1. **DEBUG_MODE**: Sempre `false` em produção
2. **Permissões**: Configure corretamente as permissões de arquivos
3. **HTTPS**: Use SSL/TLS em produção
4. **Senhas**: Use senhas fortes para o banco de dados
5. **Backup**: Configure backups regulares do banco de dados
6. **Atualizações**: Mantenha PHP e dependências atualizadas

---

## 📝 Checklist de Deploy

- [ ] Servidor com requisitos atendidos (PHP 8.1+, MySQL, Apache/Nginx)
- [ ] Banco de dados criado
- [ ] Assets compilados (`npm run build`)
- [ ] Arquivos enviados para o servidor
- [ ] Permissões da pasta `storage/` configuradas (777 ou 755)
- [ ] Arquivo `config.php` criado e configurado
- [ ] Dependências instaladas (`composer install`)
- [ ] Servidor web configurado (Apache/Nginx)
- [ ] Wizard de instalação executado
- [ ] DEBUG_MODE = false em produção
- [ ] HTTPS configurado
- [ ] Backups configurados

---

## 🆘 Troubleshooting

### Erro: "config.php file is missing"
- Copie `config-sample.php` para `config.php`

### Erro: "storage directory is not writable"
- Execute: `chmod -R 777 storage` ou configure permissões adequadas

### Erro: "vendor/autoload.php is missing"
- Execute: `composer install`

### Erro: "Database connection failed"
- Verifique credenciais no `config.php`
- Verifique se o MySQL está rodando
- Verifique se o usuário tem permissões no banco

### Assets não carregam
- Execute: `npm run build` para compilar os assets
- Verifique se a pasta `assets/vendor/` existe

---

## 📚 Referências

- [Documentação Oficial](https://easyappointments.org/docs)
- [Guia de Instalação](docs/installation-guide.md)
- [Docker para Desenvolvimento](docs/docker.md)
- [Repositório Docker Produção](https://github.com/alextselegidis/easyappointments-docker)

