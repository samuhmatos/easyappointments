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

class Migration_Create_event_queue_table extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('event_queue')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'event_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '100',
                    'null' => false,
                ],
                'payload' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['pending', 'processing', 'processed', 'failed'],
                    'default' => 'pending',
                ],
                'attempts' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'max_attempts' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 3,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'scheduled_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'processed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'update_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('status');
            $this->dbforge->add_key('event_name');
            $this->dbforge->add_key('scheduled_at');

            $this->dbforge->create_table('event_queue', true, ['engine' => 'InnoDB']);
            
            // Verificar se a tabela foi criada com sucesso antes de adicionar o índice
            if ($this->db->table_exists('event_queue')) {
                try {
                    // Add composite index for get_pending() query optimization
                    $this->db->query("CREATE INDEX `event_queue_status_scheduled_at_attempts_create_datetime` 
                        ON `event_queue` (`status`, `scheduled_at`, `attempts`, `create_datetime`)");
                } catch (Exception $e) {
                    // Se o índice já existir ou houver outro erro, apenas logar
                    log_message('debug', 'Migration 061: Could not create index on event_queue: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('event_queue')) {
            $this->dbforge->drop_table('event_queue');
        }
    }
}

