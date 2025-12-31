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
 * WhatsApp Notification Listener.
 *
 * Handles WhatsApp notifications for events.
 * This is a placeholder for future WhatsApp integration.
 *
 * @package Listeners
 */
class WhatsAppNotificationListener
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * WhatsAppNotificationListener constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('rabbitmq_producer');
    }

    /**
     * Handle the event.
     *
     * @param array $payload Event payload.
     *
     * @return void
     */
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;

        if ($event === 'appointment.saved') {
            $this->handle_appointment_saved($payload);
        } elseif ($event === 'appointment.deleted') {
            $this->handle_appointment_deleted($payload);
        }
    }

    /**
     * Handle appointment saved event.
     *
     * @param array $payload Event payload.
     *
     * @return void
     */
    protected function handle_appointment_saved(array $payload): void
    {
        try {
            log_message('debug', 'WhatsAppNotificationListener::handle_appointment_saved() called');

            $appointment = $payload['appointment'] ?? null;
            $service = $payload['service'] ?? null;
            $provider = $payload['provider'] ?? null;
            $customer = $payload['customer'] ?? null;
            $settings = $payload['settings'] ?? null;
            $manage_mode = $payload['manage_mode'] ?? false;

            if (!$appointment || !$service || !$provider || !$customer || !$settings) {
                throw new Exception('Missing required payload data for WhatsApp notification');
            }

            // Prepare WhatsApp notification data for RabbitMQ
            $whatsappData = [
                'type' => 'appointment.saved',
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'manage_mode' => $manage_mode,
            ];

            // Send to RabbitMQ queue
            $success = $this->CI->rabbitmq_producer->send_whatsapp($whatsappData);

            if ($success) {
                log_message('debug', 'WhatsAppNotificationListener - WhatsApp notification sent to RabbitMQ queue');
            } else {
                log_message('error', 'WhatsAppNotificationListener - Failed to send WhatsApp notification to RabbitMQ');
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'WhatsAppNotificationListener - Error handling appointment.saved: ' . $e->getMessage(),
            );
            log_message('error', 'WhatsAppNotificationListener - Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Handle appointment deleted event.
     *
     * @param array $payload Event payload.
     *
     * @return void
     */
    protected function handle_appointment_deleted(array $payload): void
    {
        try {
            log_message('debug', 'WhatsAppNotificationListener::handle_appointment_deleted() called');

            $appointment = $payload['appointment'] ?? null;
            $service = $payload['service'] ?? null;
            $provider = $payload['provider'] ?? null;
            $customer = $payload['customer'] ?? null;
            $settings = $payload['settings'] ?? null;
            $cancellation_reason = $payload['cancellation_reason'] ?? '';

            if (!$appointment || !$service || !$provider || !$customer || !$settings) {
                throw new Exception('Missing required payload data for WhatsApp cancellation notification');
            }

            // Prepare WhatsApp notification data for RabbitMQ
            $whatsappData = [
                'type' => 'appointment.deleted',
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'cancellation_reason' => $cancellation_reason,
            ];

            // Send to RabbitMQ queue
            $success = $this->CI->rabbitmq_producer->send_whatsapp($whatsappData);

            if ($success) {
                log_message(
                    'debug',
                    'WhatsAppNotificationListener - WhatsApp cancellation notification sent to RabbitMQ queue',
                );
            } else {
                log_message(
                    'error',
                    'WhatsAppNotificationListener - Failed to send WhatsApp cancellation notification to RabbitMQ',
                );
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'WhatsAppNotificationListener - Error handling appointment.deleted: ' . $e->getMessage(),
            );
            log_message('error', 'WhatsAppNotificationListener - Stack trace: ' . $e->getTraceAsString());
        }
    }
}
