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
 * Email Notification Listener.
 *
 * Handles email notifications for events.
 *
 * @package Listeners
 */
class EmailNotificationListener
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * EmailNotificationListener constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email_messages');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
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

        log_message('error', 'EmailNotificationListener::handle() called with event: ' . ($event ?? 'null'));

        if ($event === 'appointment.saved') {
            $this->handle_appointment_saved($payload);
        } elseif ($event === 'appointment.deleted') {
            $this->handle_appointment_deleted($payload);
        } else {
            log_message('error', 'EmailNotificationListener::handle() - Unknown event type: ' . ($event ?? 'null'));
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
            log_message('error', 'EmailNotificationListener::handle_appointment_saved() called');

            $current_language = config('language');

            $appointment = $payload['appointment'] ?? null;
            $service = $payload['service'] ?? null;
            $provider = $payload['provider'] ?? null;
            $customer = $payload['customer'] ?? null;
            $settings = $payload['settings'] ?? null;
            $manage_mode = $payload['manage_mode'] ?? false;

            if (!$appointment || !$service || !$provider || !$customer || !$settings) {
                throw new Exception(
                    'Missing required payload data: appointment=' .
                        ($appointment ? 'ok' : 'missing') .
                        ', service=' .
                        ($service ? 'ok' : 'missing') .
                        ', provider=' .
                        ($provider ? 'ok' : 'missing') .
                        ', customer=' .
                        ($customer ? 'ok' : 'missing') .
                        ', settings=' .
                        ($settings ? 'ok' : 'missing'),
                );
            }

            $customer_link = site_url('booking/reschedule/' . $appointment['hash']);
            $provider_link = site_url('calendar/reschedule/' . $appointment['hash']);

            // Use ICS stream from payload if available, otherwise generate it
            $ics_stream =
                $payload['ics_stream'] ?? $this->CI->ics_file->get_stream($appointment, $service, $provider, $customer);

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                log_message('error', 'EmailNotificationListener - Sending email to customer: ' . $customer['email']);
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_booked');
                $message = $manage_mode ? '' : lang('thank_you_for_appointment');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $customer_link,
                        $customer['email'],
                        $ics_stream,
                        $customer['timezone'],
                    );
                    log_message(
                        'error',
                        'EmailNotificationListener - Email sent successfully to customer: ' . $customer['email'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to customer', $appointment['id'] ?? null);
                }
            } else {
                log_message('error', 'EmailNotificationListener - Skipping customer email (send_customer=false)');
            }

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                log_message('error', 'EmailNotificationListener - Sending email to provider: ' . $provider['email']);
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $provider['email'],
                        $ics_stream,
                        $provider['timezone'],
                    );
                    log_message(
                        'error',
                        'EmailNotificationListener - Email sent successfully to provider: ' . $provider['email'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to provider', $appointment['id'] ?? null);
                }
            } else {
                log_message('error', 'EmailNotificationListener - Skipping provider email (send_provider=false)');
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $admin['email'],
                        $ics_stream,
                        $admin['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to admin', $appointment['id'] ?? null);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');
                $subject = $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan');
                $message = $manage_mode ? '' : lang('appointment_link_description');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $subject,
                        $message,
                        $provider_link,
                        $secretary['email'],
                        $ics_stream,
                        $secretary['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-saved to secretary', $appointment['id'] ?? null);
                }
            }
        } catch (Throwable $e) {
            $this->log_exception($e, 'appointment-saved (general exception)', $appointment['id'] ?? null);
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
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
            $current_language = config('language');

            $appointment = $payload['appointment'];
            $service = $payload['service'];
            $provider = $payload['provider'];
            $customer = $payload['customer'];
            $settings = $payload['settings'];
            $cancellation_reason = $payload['cancellation_reason'] ?? '';

            // Notify provider.
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $provider['email'],
                        $cancellation_reason,
                        $provider['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to provider', $appointment['id'] ?? null);
                }
            }

            // Notify customer.
            $send_customer =
                !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $customer['email'],
                        $cancellation_reason,
                        $customer['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to customer', $appointment['id'] ?? null);
                }
            }

            // Notify admins.
            $admins = $this->CI->admins_model->get();

            foreach ($admins as $admin) {
                if ($admin['settings']['notifications'] === '0') {
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $admin['email'],
                        $cancellation_reason,
                        $admin['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to admin', $appointment['id'] ?? null);
                }
            }

            // Notify secretaries.
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if ($secretary['settings']['notifications'] === '0') {
                    continue;
                }

                if (!in_array($provider['id'], $secretary['providers'])) {
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $secretary['email'],
                        $cancellation_reason,
                        $secretary['timezone'],
                    );
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to secretary', $appointment['id'] ?? null);
                }
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'EmailNotificationListener - Could not email cancellation details of appointment (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Log exception.
     *
     * @param Throwable $e Exception.
     * @param string $message Message.
     * @param int|null $appointment_id Appointment ID.
     *
     * @return void
     */
    private function log_exception(Throwable $e, string $message, ?int $appointment_id): void
    {
        log_message(
            'error',
            'EmailNotificationListener - Could not email ' .
                $message .
                ' (' .
                ($appointment_id ?? '-') .
                ') : ' .
                $e->getMessage(),
        );
        log_message('error', $e->getTraceAsString());
    }
}
