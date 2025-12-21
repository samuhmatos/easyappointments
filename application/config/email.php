<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

// $config['useragent'] = 'MarcaAgora';
// $config['protocol'] = 'mail'; // or 'smtp'
// $config['mailtype'] = 'html'; // or 'text'
// $config['smtp_debug'] = '0'; // or '1'
// $config['smtp_auth'] = TRUE; //or FALSE for anonymous relay.
// $config['smtp_host'] = '';
// $config['smtp_user'] = '';
// $config['smtp_pass'] = '';
// $config['smtp_crypto'] = 'ssl'; // or 'tls'
// $config['smtp_port'] = 25;
// $config['from_name'] = '';
// $config['from_address'] = '';
// $config['reply_to'] = '';
// $config['crlf'] = "\r\n";
// $config['newline'] = "\r\n";

$config['useragent'] = 'MarcaAgora';
$config['protocol'] = 'smtp'; // or 'smtp'
$config['mailtype'] = 'html'; // or 'text'
$config['smtp_debug'] = '1'; // Habilitado temporariamente para debug
$config['smtp_auth'] = true; //or FALSE for anonymous relay.
$config['smtp_host'] = 'smtp.hostinger.com';
$config['smtp_user'] = 'contato@marcaagora.com';
$config['smtp_pass'] = 'Sambed2020@#';
$config['smtp_crypto'] = 'tls'; // TLS geralmente funciona melhor que SSL
$config['smtp_port'] = 587; // Porta 587 com TLS é mais comum e geralmente não é bloqueada
$config['from_name'] = 'Marca Agora';
// Usando o mesmo endereço autenticado como remetente (Hostinger não permite alias como from_address)
$config['from_address'] = 'contato@marcaagora.com';
$config['reply_to'] = 'contato@marcaagora.com';
$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";
