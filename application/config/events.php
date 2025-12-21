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

/*
|--------------------------------------------------------------------------
| Event Listeners Configuration
|--------------------------------------------------------------------------
|
| This file contains the configuration for event listeners.
| Each event can have multiple listeners that will be executed when the event is fired.
|
| Example:
|   'appointment.saved' => [
|       'EmailNotificationListener',
|       'WhatsAppNotificationListener',
|   ],
|
*/

$config['event_listeners'] = [
    'appointment.saved' => [
        'EmailNotificationListener',
        // 'WhatsAppNotificationListener', // Uncomment when WhatsApp is implemented
    ],
    'appointment.deleted' => [
        'EmailNotificationListener',
        // 'WhatsAppNotificationListener', // Uncomment when WhatsApp is implemented
    ],
    'appointment.updated' => ['EmailNotificationListener'],
];

/* End of file events.php */
/* Location: ./application/config/events.php */
