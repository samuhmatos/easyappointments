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

class Migration_Add_composite_index_to_event_queue extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('event_queue')) {
            // Tabela não existe ainda, pular esta migration
            return;
        }

        try {
            // Check if the composite index already exists
            $indexes = $this->db
                ->query(
                    "SHOW INDEXES FROM `event_queue` WHERE Key_name = 'event_queue_status_scheduled_at_attempts_create_datetime'",
                )
                ->result_array();

            if (empty($indexes)) {
                $this->db->query("CREATE INDEX `event_queue_status_scheduled_at_attempts_create_datetime` 
                    ON `event_queue` (`status`, `scheduled_at`, `attempts`, `create_datetime`)");
            }
        } catch (Exception $e) {
            // Se houver erro ao verificar/criar índice, ignorar (pode ser que a tabela não exista ainda)
            log_message('debug', 'Migration 062: Could not add index to event_queue: ' . $e->getMessage());
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('event_queue')) {
            // Tabela não existe, pular esta migration
            return;
        }

        try {
            $indexes = $this->db
                ->query(
                    "SHOW INDEXES FROM `event_queue` WHERE Key_name = 'event_queue_status_scheduled_at_attempts_create_datetime'",
                )
                ->result_array();

            if (!empty($indexes)) {
                $this->db->query(
                    'DROP INDEX `event_queue_status_scheduled_at_attempts_create_datetime` ON `event_queue`',
                );
            }
        } catch (Exception $e) {
            // Se houver erro ao verificar/remover índice, ignorar
            log_message('debug', 'Migration 062: Could not remove index from event_queue: ' . $e->getMessage());
        }
    }
}
