<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
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
        $this->CI->load->model('event_queue_model');
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
        $event = [
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'pending',
        ];

        if ($scheduledAt !== null) {
            $event['scheduled_at'] = $scheduledAt->format('Y-m-d H:i:s');
        }

        $this->CI->event_queue_model->enqueue($event);
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
        $events = $this->CI->event_queue_model->get_pending($limit);
        
        log_message('error', 'Event Dispatcher - Found ' . count($events) . ' pending events to process');

        foreach ($events as $event) {
            log_message('error', 'Event Dispatcher - Processing event ID: ' . $event['id'] . ', event_name: ' . $event['event_name']);
            $this->CI->event_queue_model->mark_processing($event['id']);

            try {
                $payload = json_decode($event['payload'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON payload: ' . json_last_error_msg());
                }

                log_message('error', 'Event Dispatcher - Payload decoded successfully, keys: ' . implode(', ', array_keys($payload)));
                $this->fire($event['event_name'], $payload);
                log_message('error', 'Event Dispatcher - Event fired successfully, marking as processed');
                $this->CI->event_queue_model->mark_processed($event['id']);
                log_message('error', 'Event Dispatcher - Event ID ' . $event['id'] . ' marked as processed');
            } catch (Throwable $e) {
                $this->CI->event_queue_model->mark_failed($event['id'], $e->getMessage());
                log_message(
                    'error',
                    'Event Dispatcher - Failed to process event (' .
                        ($event['id'] ?? '-') .
                        '): ' .
                        $e->getMessage(),
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

        log_message('error', 'Event Dispatcher - Firing event: ' . $eventName . ' with ' . count($listeners) . ' listeners');

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
                    log_message('error', 'Event Dispatcher - Listener class not found: ' . $listener . ' (file: ' . $listenerFile . ')');
                }
            } catch (Throwable $e) {
                log_message(
                    'error',
                    'Event Dispatcher - Listener ' . $listener . ' failed: ' . $e->getMessage(),
                );
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

