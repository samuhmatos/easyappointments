<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * MarcaAgora - Agendamento Online
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.2
 * ---------------------------------------------------------------------------- */

/**
 * Event Dispatcher Library.
 *
 * Sistema de eventos para desacoplar ações do código principal.
 * Permite processamento assíncrono através de fila.
 *
 * @package Libraries
 */
class Event_dispatcher
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Event constants.
     */
    const EVENT_APPOINTMENT_SAVED = 'appointment.saved';
    const EVENT_APPOINTMENT_DELETED = 'appointment.deleted';
    const EVENT_APPOINTMENT_UPDATED = 'appointment.updated';

    /**
     * Event_dispatcher constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
        // Não carregar o modelo no construtor para evitar problemas durante migrations
        // O modelo será carregado sob demanda quando necessário
    }

    /**
     * Load the event_queue_model if not already loaded and table exists.
     *
     * @return bool Returns true if model is loaded, false otherwise.
     */
    protected function ensure_model_loaded(): bool
    {
        // Tentar acessar o modelo diretamente para verificar se já está carregado
        try {
            if (isset($this->CI->event_queue_model) && is_object($this->CI->event_queue_model)) {
                return true;
            }
        } catch (Throwable $e) {
            // Modelo não existe ainda, continuar para carregar
        }

        try {
            // Verificar se a tabela existe antes de carregar o modelo
            if (!$this->CI->db->table_exists('event_queue')) {
                log_message('debug', 'Event Dispatcher: event_queue table does not exist yet');
                return false;
            }

            // Carregar o modelo
            $this->CI->load->model('event_queue_model');

            // Verificar se foi carregado com sucesso tentando acessá-lo
            try {
                if (isset($this->CI->event_queue_model) && is_object($this->CI->event_queue_model)) {
                    log_message('debug', 'Event Dispatcher: event_queue_model loaded successfully');
                    return true;
                }
            } catch (Throwable $e) {
                log_message('error', 'Event Dispatcher: Model loaded but cannot be accessed: ' . $e->getMessage());
            }

            log_message(
                'error',
                'Event Dispatcher: Failed to load event_queue_model - model object not accessible after load',
            );
            return false;
        } catch (Throwable $e) {
            log_message('error', 'Event Dispatcher: Error loading event_queue_model: ' . $e->getMessage());
            log_message('error', 'Event Dispatcher: Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Dispatch an event asynchronously (enqueue it).
     *
     * @param string $eventName Event name.
     * @param array $payload Event payload data.
     * @param \DateTime|null $scheduledAt Optional scheduled time for processing.
     *
     * @return void
     */
    public function dispatch(string $eventName, array $payload = [], ?\DateTime $scheduledAt = null): void
    {
        try {
            // Verificar se a tabela existe antes de tentar carregar o modelo
            if (!$this->CI->db->table_exists('event_queue')) {
                log_message(
                    'debug',
                    'Event Dispatcher: Cannot dispatch event "' . $eventName . '" - event_queue table does not exist',
                );
                return;
            }

            // Carregar o modelo se necessário (o Loader verifica se já está carregado)
            $this->CI->load->model('event_queue_model');

            // Acessar o modelo usando a mesma técnica da biblioteca Api
            $modelName = 'event_queue_model';

            // Tentar acessar o modelo de diferentes formas
            try {
                $model = $this->CI->{$modelName};
            } catch (Throwable $e) {
                log_message('error', 'Event Dispatcher: Error accessing model via {$modelName}: ' . $e->getMessage());
                // Tentar acessar diretamente
                try {
                    $model = $this->CI->event_queue_model;
                } catch (Throwable $e2) {
                    log_message('error', 'Event Dispatcher: Error accessing model directly: ' . $e2->getMessage());
                    return;
                }
            }

            if (!is_object($model)) {
                log_message(
                    'error',
                    'Event Dispatcher: event_queue_model is not an object after loading. Type: ' . gettype($model),
                );
                return;
            }

            $event = [
                'event_name' => $eventName,
                'payload' => $payload,
                'status' => 'pending',
            ];

            if ($scheduledAt !== null) {
                $event['scheduled_at'] = $scheduledAt->format('Y-m-d H:i:s');
            }

            $eventId = $model->enqueue($event);
            log_message('debug', 'Event Dispatcher: Event "' . $eventName . '" enqueued with ID: ' . $eventId);
        } catch (Throwable $e) {
            log_message('error', 'Event Dispatcher: Error dispatching event "' . $eventName . '": ' . $e->getMessage());
            log_message('error', 'Event Dispatcher: File: ' . $e->getFile() . ':' . $e->getLine());
            log_message('error', 'Event Dispatcher: Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Dispatch an event synchronously (for special cases).
     *
     * @param string $eventName Event name.
     * @param array $payload Event payload data.
     *
     * @return void
     */
    public function dispatch_sync(string $eventName, array $payload = []): void
    {
        $this->fire($eventName, $payload);
    }

    /**
     * Process pending events from the queue.
     *
     * @param int $limit Maximum number of events to process.
     *
     * @return void
     */
    public function process_queue(int $limit = 10): void
    {
        try {
            // Verificar se a tabela existe antes de tentar carregar o modelo
            if (!$this->CI->db->table_exists('event_queue')) {
                log_message('debug', 'Event Dispatcher: Cannot process queue - event_queue table does not exist');
                return;
            }

            // Carregar o modelo se necessário (o Loader verifica se já está carregado)
            $this->CI->load->model('event_queue_model');

            // Acessar o modelo usando a mesma técnica da biblioteca Api
            $modelName = 'event_queue_model';
            $model = $this->CI->{$modelName};

            if (!is_object($model)) {
                log_message('error', 'Event Dispatcher: Cannot process queue - event_queue_model is not an object');
                return;
            }
        } catch (Throwable $e) {
            log_message('error', 'Event Dispatcher: Error loading model for process_queue: ' . $e->getMessage());
            log_message('error', 'Event Dispatcher: Stack trace: ' . $e->getTraceAsString());
            return;
        }

        $events = $model->get_pending($limit);

        foreach ($events as $event) {
            $model->mark_processing($event['id']);

            try {
                $payload = json_decode($event['payload'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON payload: ' . json_last_error_msg());
                }

                $this->fire($event['event_name'], $payload);
                $model->mark_processed($event['id']);
            } catch (Throwable $e) {
                $model->mark_failed($event['id'], $e->getMessage());
                log_message(
                    'error',
                    'Event Dispatcher - Failed to process event (' . ($event['id'] ?? '-') . '): ' . $e->getMessage(),
                );
                log_message('error', $e->getTraceAsString());
            }
        }

        log_message('error', 'Event Dispatcher - Finished processing queue');
    }

    /**
     * Fire the event and execute registered listeners.
     *
     * @param string $eventName Event name.
     * @param array $payload Event payload data.
     *
     * @return void
     */
    protected function fire(string $eventName, array $payload): void
    {
        $listeners = $this->get_listeners($eventName);

        log_message(
            'error',
            'Event Dispatcher - Firing event: ' . $eventName . ' with ' . count($listeners) . ' listeners',
        );

        foreach ($listeners as $listener) {
            try {
                // Load the listener file if it exists
                $listenerFile = APPPATH . 'listeners/' . $listener . '.php';
                if (file_exists($listenerFile)) {
                    require_once $listenerFile;
                }

                if (class_exists($listener)) {
                    log_message('error', 'Event Dispatcher - Instantiating listener: ' . $listener);
                    $listenerInstance = new $listener();
                    if (method_exists($listenerInstance, 'handle')) {
                        log_message('error', 'Event Dispatcher - Calling handle() on listener: ' . $listener);
                        $listenerInstance->handle($payload);
                        log_message('error', 'Event Dispatcher - Listener ' . $listener . ' completed successfully');
                    } else {
                        log_message(
                            'error',
                            'Event Dispatcher - Listener ' . $listener . ' does not have a handle method',
                        );
                    }
                } else {
                    log_message(
                        'error',
                        'Event Dispatcher - Listener class not found: ' . $listener . ' (file: ' . $listenerFile . ')',
                    );
                }
            } catch (Throwable $e) {
                log_message('error', 'Event Dispatcher - Listener ' . $listener . ' failed: ' . $e->getMessage());
                log_message('error', $e->getTraceAsString());
                // Continue processing other listeners even if one fails
            }
        }
    }

    /**
     * Get registered listeners for an event.
     *
     * @param string $eventName Event name.
     *
     * @return array Array of listener class names.
     */
    protected function get_listeners(string $eventName): array
    {
        $this->CI->config->load('events', true);
        $config = $this->CI->config->item('event_listeners', 'events');
        return $config[$eventName] ?? [];
    }
}
