# Sistema de Eventos Assíncronos

Este documento descreve o sistema de eventos implementado para tornar o envio de notificações (emails, WhatsApp, etc.) assíncrono.

## Visão Geral

O sistema de eventos permite que notificações sejam processadas de forma assíncrona, melhorando a performance da aplicação e permitindo fácil extensão para novos canais de notificação (WhatsApp, SMS, etc.).

## Arquitetura

1. **Event Dispatcher**: Dispara eventos e os enfileira para processamento assíncrono
2. **Event Queue**: Tabela no banco de dados que armazena eventos pendentes
3. **Listeners**: Classes que processam eventos específicos (EmailNotificationListener, WhatsAppNotificationListener, etc.)
4. **Worker**: Comando CLI que processa eventos da fila

## Fluxo

1. Quando um agendamento é criado/modificado, `Notifications::notify_appointment_saved()` é chamado
2. O método dispara um evento através de `Event_dispatcher::dispatch()`
3. O evento é enfileirado na tabela `event_queue`
4. A resposta HTTP retorna imediatamente (assíncrono)
5. Um worker (cron job) processa eventos pendentes periodicamente
6. Os listeners registrados executam as ações necessárias (envio de emails, WhatsApp, etc.)

## Instalação

### 1. Executar Migration

Execute a migration para criar a tabela `event_queue`:

```bash
php index.php console migrate
```

### 2. Configurar Cron Job

Adicione o seguinte comando ao crontab para processar eventos a cada minuto:

```bash
* * * * * cd /path/to/easyappointments && php index.php console process_events
```

Ou para processar mais eventos por execução:

```bash
* * * * * cd /path/to/easyappointments && php index.php console process_events 20
```

## Uso

### Disparar Eventos

Os eventos são disparados automaticamente quando agendamentos são criados/modificados. Você também pode disparar eventos manualmente:

```php
$this->load->library('event_dispatcher');

// Disparar evento assíncrono
$this->event_dispatcher->dispatch(Event_dispatcher::EVENT_APPOINTMENT_SAVED, [
    'event' => 'appointment.saved',
    'appointment' => $appointment,
    'service' => $service,
    'provider' => $provider,
    'customer' => $customer,
    'settings' => $settings,
]);

// Disparar evento síncrono (para casos especiais)
$this->event_dispatcher->dispatch_sync(Event_dispatcher::EVENT_APPOINTMENT_SAVED, $payload);
```

### Criar Novos Listeners

1. Crie uma nova classe em `application/listeners/`:

```php
<?php
class MeuListener
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function handle(array $payload): void
    {
        // Processar evento
    }
}
```

2. Registre o listener em `application/config/events.php`:

```php
$config['event_listeners'] = [
    'appointment.saved' => [
        'EmailNotificationListener',
        'MeuListener', // Adicione aqui
    ],
];
```

## Eventos Disponíveis

-   `appointment.saved`: Disparado quando um agendamento é criado ou modificado
-   `appointment.deleted`: Disparado quando um agendamento é cancelado
-   `appointment.updated`: Disparado quando um agendamento é atualizado

## Listeners Disponíveis

-   **EmailNotificationListener**: Envia emails de notificação
-   **WhatsAppNotificationListener**: Estrutura básica para envio de WhatsApp (a ser implementado)

## Monitoramento

### Verificar Eventos Pendentes

```sql
SELECT * FROM event_queue WHERE status = 'pending';
```

### Verificar Eventos Falhados

```sql
SELECT * FROM event_queue WHERE status = 'failed';
```

### Reprocessar Eventos Falhados

O sistema tenta reprocessar eventos falhados automaticamente (até `max_attempts` vezes). Você também pode resetar manualmente:

```php
$this->load->model('event_queue_model');
$this->event_queue_model->reset_failed_for_retry(10);
```

## Adicionar Suporte a WhatsApp

1. Implemente a lógica em `WhatsAppNotificationListener::handle_appointment_saved()`
2. Crie uma library `Whatsapp_messages` similar a `Email_messages`
3. Descomente `WhatsAppNotificationListener` em `application/config/events.php`
4. Configure as credenciais da API do WhatsApp

## Troubleshooting

### Eventos não estão sendo processados

1. Verifique se o cron job está configurado e rodando
2. Verifique os logs em `application/logs/`
3. Verifique se há eventos pendentes na tabela `event_queue`

### Emails não estão sendo enviados

1. Verifique se o listener está registrado em `events.php`
2. Verifique as configurações de email em `application/config/email.php`
3. Verifique os logs para erros específicos

## Performance

-   Por padrão, o worker processa 10 eventos por execução
-   Aumente o limite se necessário: `php index.php console process_events 50`
-   Eventos são processados em ordem de criação (FIFO)
