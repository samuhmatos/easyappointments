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

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * RabbitMQ Producer Library.
 *
 * Handles sending messages to RabbitMQ queues for email and WhatsApp notifications.
 *
 * @package Libraries
 */
class Rabbitmq_producer
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * @var AMQPStreamConnection|null
     */
    protected ?AMQPStreamConnection $connection = null;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel|null
     */
    protected $channel = null;

    /**
     * Queue names and routing keys.
     */
    // Queue names
    const QUEUE_EMAIL = 'marcaagora.notification-email';
    const QUEUE_WHATSAPP = 'marcaagora.notification-whatsapp';

    // Routing keys
    const ROUTING_KEY_EMAIL = 'marcaagora.notification-email.send';
    const ROUTING_KEY_WHATSAPP = 'marcaagora.notification-whatsapp.send';

    // Exchange expected by the consumer
    const EXCHANGE = 'marcaagora';

    /**
     * Rabbitmq_producer constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Get RabbitMQ connection.
     *
     * @return AMQPStreamConnection
     * @throws Exception
     */
    protected function get_connection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            // Try to get from Config class first, then from environment variables
            // Fallback: host.docker.internal to alcançar broker externo quando não está no mesmo compose
            $uri = null;
            if (class_exists('Config') && defined('Config::RABBITMQ_URI')) {
                $uri = Config::RABBITMQ_URI;
            } else {
                $uri = env('RABBITMQ_URI', getenv('RABBITMQ_URI') ?: 'amqp://guest:guest@host.docker.internal:5672');
            }

            // Parse URI
            $parsed = parse_url($uri);

            if ($parsed === false) {
                throw new Exception('Invalid RABBITMQ_URI format');
            }

            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 5672;

            // Get user and password from Config or environment
            $user = $parsed['user'] ?? null;
            $pass = $parsed['pass'] ?? null;

            if ($user === null) {
                if (class_exists('Config') && defined('Config::RABBITMQ_USER')) {
                    $user = Config::RABBITMQ_USER;
                } else {
                    $user = env('RABBITMQ_USER', getenv('RABBITMQ_USER') ?: 'guest');
                }
            }

            if ($pass === null) {
                if (class_exists('Config') && defined('Config::RABBITMQ_PASSWORD')) {
                    $pass = Config::RABBITMQ_PASSWORD;
                } else {
                    $pass = env('RABBITMQ_PASSWORD', getenv('RABBITMQ_PASSWORD') ?: 'guest');
                }
            }

            $vhost = isset($parsed['path']) && $parsed['path'] !== '/' ? ltrim($parsed['path'], '/') : '/';

            try {
                $this->connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
            } catch (Exception $e) {
                log_message('error', 'RabbitMQ - Connection failed: ' . $e->getMessage());
                throw new Exception('Failed to connect to RabbitMQ: ' . $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * Get or create channel.
     *
     * @return \PhpAmqpLib\Channel\AMQPChannel
     * @throws Exception
     */
    protected function get_channel()
    {
        if ($this->channel === null || !$this->channel->is_open()) {
            $connection = $this->get_connection();
            $this->channel = $connection->channel();
        }

        return $this->channel;
    }

    /**
     * Send email notification to RabbitMQ queue.
     *
     * @param array $data Email notification data.
     *
     * @return bool Returns true on success, false on failure.
     */
    public function send_email(array $data): bool
    {
        try {
            $channel = $this->get_channel();
            $message = new AMQPMessage(json_encode($data), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            // Publish to the existing exchange using the routing key
            // The exchange and queue are expected to already exist
            $channel->basic_publish($message, self::EXCHANGE, self::ROUTING_KEY_EMAIL);

            return true;
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ - Email notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp notification to RabbitMQ queue.
     *
     * @param array $data WhatsApp notification data.
     *
     * @return bool Returns true on success, false on failure.
     */
    public function send_whatsapp(array $data): bool
    {
        try {
            $channel = $this->get_channel();
            $message = new AMQPMessage(json_encode($data), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            // Publish to the existing exchange using the routing key
            // The exchange and queue are expected to already exist
            $channel->basic_publish($message, self::EXCHANGE, self::ROUTING_KEY_WHATSAPP);

            return true;
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ - WhatsApp notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Close connection and channel.
     *
     * Note: In web requests, connections are automatically closed when the PHP process ends.
     * This method is useful for long-running scripts or when you want to explicitly close.
     *
     * @return void
     */
    public function close(): void
    {
        try {
            if ($this->channel !== null && $this->channel->is_open()) {
                $this->channel->close();
                $this->channel = null;
            }
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ - Channel close error: ' . $e->getMessage());
        }

        try {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
                $this->connection = null;
            }
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ - Connection close error: ' . $e->getMessage());
        }
    }

    /**
     * Destructor - don't close connections explicitly.
     *
     * In web requests, PHP will automatically close the connection when the process ends.
     * Closing here would create unnecessary overhead and prevent connection reuse.
     * The RabbitMQ server will detect when the connection is lost and clean up automatically.
     */
    public function __destruct()
    {
        // Don't close connections in destructor for web requests
        // This allows the connection to be reused within the same request
        // and will be automatically closed when PHP process ends
    }
}
