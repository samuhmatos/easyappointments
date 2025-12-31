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
     * Queue names.
     */
    // Queue names / routing keys expected by the consumer
    const QUEUE_EMAIL = 'marcaagora.notification-email.send';
    const QUEUE_WHATSAPP = 'marcaagora.notification-whatsapp.send';

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
                log_message(
                    'debug',
                    'RabbitMQ Producer - Connecting to ' . $uri . " (host={$host}, port={$port}, vhost={$vhost})",
                );
                $this->connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
            } catch (Exception $e) {
                log_message('error', 'RabbitMQ Producer - Failed to connect: ' . $e->getMessage());
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
     * Declare queue if it doesn't exist.
     *
     * @param string $queueName Queue name.
     *
     * @return void
     * @throws Exception
     */
    protected function declare_queue(string $queueName): void
    {
        $channel = $this->get_channel();
        // Exchange já existe no broker como 'topic'; não forçar re-declaração com outro tipo
        $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
        $channel->queue_declare($queueName, false, true, false, false);
        $channel->queue_bind($queueName, self::EXCHANGE, $queueName);
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
            $this->declare_queue(self::QUEUE_EMAIL);

            $channel = $this->get_channel();
            log_message(
                'debug',
                'RabbitMQ Producer - Publishing email notification | exchange=' .
                    self::EXCHANGE .
                    ' routing_key=' .
                    self::QUEUE_EMAIL,
            );
            $message = new AMQPMessage(json_encode($data), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            // Publish to the expected exchange using routing key = queue name
            $channel->basic_publish($message, self::EXCHANGE, self::QUEUE_EMAIL);

            log_message('debug', 'RabbitMQ Producer - Email notification sent to queue: ' . self::QUEUE_EMAIL);
            return true;
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ Producer - Failed to send email notification: ' . $e->getMessage());
            log_message('error', 'RabbitMQ Producer - Stack trace: ' . $e->getTraceAsString());
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
            $this->declare_queue(self::QUEUE_WHATSAPP);

            $channel = $this->get_channel();
            log_message(
                'debug',
                'RabbitMQ Producer - Publishing WhatsApp notification | exchange=' .
                    self::EXCHANGE .
                    ' routing_key=' .
                    self::QUEUE_WHATSAPP,
            );
            $message = new AMQPMessage(json_encode($data), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            // Publish to the expected exchange using routing key = queue name
            $channel->basic_publish($message, self::EXCHANGE, self::QUEUE_WHATSAPP);

            log_message('debug', 'RabbitMQ Producer - WhatsApp notification sent to queue: ' . self::QUEUE_WHATSAPP);
            return true;
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ Producer - Failed to send WhatsApp notification: ' . $e->getMessage());
            log_message('error', 'RabbitMQ Producer - Stack trace: ' . $e->getTraceAsString());
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
            log_message('error', 'RabbitMQ Producer - Error closing channel: ' . $e->getMessage());
        }

        try {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
                $this->connection = null;
            }
        } catch (Exception $e) {
            log_message('error', 'RabbitMQ Producer - Error closing connection: ' . $e->getMessage());
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
