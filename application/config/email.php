<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Easy!Appointments';
$config['protocol'] = 'mail'; // or 'smtp'
$config['mailtype'] = 'html'; // or 'text'

// Configurações do Mailpit (não requer autenticação)
$config['smtp_host'] = 'mailpit'; // Nome do serviço no docker-compose
$config['smtp_port'] = 1025; // Porta SMTP do Mailpit
$config['smtp_auth'] = FALSE; // Mailpit não requer autenticação
$config['smtp_crypto'] = ''; // Sem criptografia (TLS/SSL)
$config['smtp_user'] = ''; // Vazio
$config['smtp_pass'] = ''; // Vazio
$config['smtp_debug'] = '0'; // Mude para '1' para debug

// Configurações do remetente (opcional, pode usar settings do sistema)
$config['from_name'] = 'Easy!Appointments';
$config['from_address'] = 'noreply@easyappointments.local';
$config['reply_to'] = 'noreply@easyappointments.local';

$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";