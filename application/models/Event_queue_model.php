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
 * Event queue model.
 *
 * Handles all the database operations of the event queue resource.
 *
 * @package Models
 */
class Event_queue_model extends EA_Model
{
    /**
     * @var string
     */
    protected string $table = 'event_queue';

    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Event_queue_model constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Enqueue an event to be processed later.
     *
     * @param array $event Associative array with the event data.
     *
     * @return int Returns the ID of the inserted event.
     */
    public function enqueue(array $event): int
    {
        $event['create_datetime'] = date('Y-m-d H:i:s');
        $event['update_datetime'] = date('Y-m-d H:i:s');
        $event['status'] = $event['status'] ?? 'pending';
        $event['attempts'] = $event['attempts'] ?? 0;
        $event['max_attempts'] = $event['max_attempts'] ?? 3;

        if (isset($event['payload']) && is_array($event['payload'])) {
            $event['payload'] = json_encode($event['payload']);
        }

        $this->db->insert($this->table, $event);
        return $this->db->insert_id();
    }

    /**
     * Get pending events from the queue.
     *
     * @param int $limit Maximum number of events to retrieve.
     *
     * @return array Returns an array of pending events.
     */
    public function get_pending(int $limit = 10): array
    {
        return $this->db
            ->where('status', 'pending')
            ->where('(scheduled_at IS NULL OR scheduled_at <= NOW())', null, false)
            ->where('attempts < max_attempts', null, false)
            ->order_by('create_datetime', 'ASC')
            ->limit($limit)
            ->get($this->table)
            ->result_array();
    }

    /**
     * Mark an event as processing.
     *
     * @param int $id Event ID.
     *
     * @return void
     */
    public function mark_processing(int $id): void
    {
        $this->db->where('id', $id)->update($this->table, [
            'status' => 'processing',
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark an event as processed.
     *
     * @param int $id Event ID.
     *
     * @return void
     */
    public function mark_processed(int $id): void
    {
        $this->db->where('id', $id)->update($this->table, [
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark an event as failed.
     *
     * @param int $id Event ID.
     * @param string $error Error message.
     *
     * @return void
     */
    public function mark_failed(int $id, string $error): void
    {
        $this->db
            ->where('id', $id)
            ->set('status', 'failed')
            ->set('error_message', $error)
            ->set('attempts', 'attempts + 1', false)
            ->set('update_datetime', date('Y-m-d H:i:s'))
            ->update($this->table);
    }

    /**
     * Reset failed events back to pending for retry.
     *
     * @param int $limit Maximum number of events to reset.
     *
     * @return void
     */
    public function reset_failed_for_retry(int $limit = 10): void
    {
        $this->db
            ->where('status', 'failed')
            ->where('attempts < max_attempts', null, false)
            ->limit($limit)
            ->update($this->table, [
                'status' => 'pending',
                'update_datetime' => date('Y-m-d H:i:s'),
            ]);
    }
}
