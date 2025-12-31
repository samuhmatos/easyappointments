<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * MarcaAgora - Agendamento Online
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Notifications library.
 *
 * Handles the notifications related functionality.
 *
 * @package Libraries
 */
class Notifications
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Notifications constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->library('ics_file');
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * This method now dispatches an event asynchronously instead of sending emails directly.
     * The actual email sending is handled by EmailNotificationListener.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     * @param bool|false $manage_mode Manage mode.
     */
    public function notify_appointment_saved(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        bool $manage_mode = false,
    ): void {
        try {
            $this->CI->load->library('event_dispatcher');

            // Generate ICS stream for calendar attachment
            $ics_stream = $this->CI->ics_file->get_stream($appointment, $service, $provider, $customer);

            log_message('debug', 'Notifications::notify_appointment_saved - dispatch_sync start');

            // Dispatch synchronously so the listener publica na fila dentro da mesma requisição
            $this->CI->event_dispatcher->dispatch_sync(Event_dispatcher::EVENT_APPOINTMENT_SAVED, [
                'event' => 'appointment.saved',
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'manage_mode' => $manage_mode,
                'ics_stream' => $ics_stream,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Notifications - Could not dispatch appointment.saved event (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }

    /**
     * Send the required notifications, related to an appointment removal.
     *
     * This method now dispatches an event asynchronously instead of sending emails directly.
     * The actual email sending is handled by EmailNotificationListener.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     * @param string $cancellation_reason Cancellation reason.
     */
    public function notify_appointment_deleted(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        string $cancellation_reason = '',
    ): void {
        try {
            $this->CI->load->library('event_dispatcher');

            log_message('debug', 'Notifications::notify_appointment_deleted - dispatch_sync start');

            // Dispatch synchronously para publicar na fila na mesma requisição
            $this->CI->event_dispatcher->dispatch_sync(Event_dispatcher::EVENT_APPOINTMENT_DELETED, [
                'event' => 'appointment.deleted',
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'cancellation_reason' => $cancellation_reason,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Notifications - Could not dispatch appointment.deleted event (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }
}
