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
        // TODO: Load WhatsApp library when implemented
        // $this->CI->load->library('whatsapp_messages');
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
        // TODO: Implement WhatsApp notification logic
        // Example structure:
        /*
        $appointment = $payload['appointment'];
        $customer = $payload['customer'];
        
        if (!empty($customer['phone_number']) && 
            filter_var($customer['whatsapp_notifications'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            
            $message = $this->format_whatsapp_message($appointment, $customer);
            $this->CI->whatsapp_messages->send($customer['phone_number'], $message);
        }
        */
        
        // Placeholder - log that WhatsApp notification would be sent
        log_message('debug', 'WhatsAppNotificationListener - Appointment saved event received (not yet implemented)');
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
        // TODO: Implement WhatsApp cancellation notification
        log_message('debug', 'WhatsAppNotificationListener - Appointment deleted event received (not yet implemented)');
    }

    /**
     * Format WhatsApp message for appointment.
     *
     * @param array $appointment Appointment data.
     * @param array $customer Customer data.
     *
     * @return string Formatted message.
     */
    protected function format_whatsapp_message(array $appointment, array $customer): string
    {
        // TODO: Implement message formatting
        return "Olá {$customer['first_name']}! Seu agendamento foi confirmado...";
    }
}

