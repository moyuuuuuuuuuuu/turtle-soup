<?php

declare(strict_types=1);

namespace App\Auth\Services;

use RuntimeException;

final class SmtpMailer
{
    public function send(string $to, string $subject, string $body): void
    {
        if (!config('mail.enabled')) {
            return;
        }
        $host = (string) config('mail.host');
        $port = (int) config('mail.port');
        $from = (string) config('mail.from_address');
        if ($host === '' || $port <= 0 || $from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SMTP configuration is incomplete');
        }
        $encryption = (string) config('mail.encryption');
        $remote = ($encryption === 'ssl' ? 'ssl://' : '').$host.':'.$port;
        $socket = stream_socket_client($remote, $error, $message, 15);
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: '.$error);
        }
        stream_set_timeout($socket, 15);
        $this->expect($socket, [220], 'greeting');
        $this->command($socket, 'EHLO hgt.test', [250], 'ehlo');
        if ($encryption === 'tls') {
            $this->command($socket, 'STARTTLS', [220], 'starttls');
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->command($socket, 'EHLO hgt.test', [250], 'ehlo_tls');
        }
        $this->command($socket, 'AUTH LOGIN', [334], 'auth');
        $this->command($socket, base64_encode((string) config('mail.username')), [334], 'auth_username');
        $this->command($socket, base64_encode((string) config('mail.password')), [235], 'auth_password');
        $this->command($socket, 'MAIL FROM:<'.$from.'>', [250], 'mail_from');
        $this->command($socket, 'RCPT TO:<'.$to.'>', [250, 251], 'recipient');
        $this->command($socket, 'DATA', [354], 'data');
        $headers = ['From: '.(string) config('mail.from_name').' <'.$from.'>', 'To: <'.$to.'>', 'Subject: =?UTF-8?B?'.base64_encode($subject).'?=', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
        fwrite($socket, implode("\r\n", $headers)."\r\n\r\n".str_replace("\n.", "\n..", $body)."\r\n.\r\n");
        $this->expect($socket, [250], 'message_body');
        $this->command($socket, 'QUIT', [221], 'quit');
        fclose($socket);
    }

    /** @param resource $socket @param list<int> $codes */
    private function command($socket, string $command, array $codes, string $stage): void
    {
        fwrite($socket, $command."\r\n");
        $this->expect($socket, $codes, $stage);
    }
    /** @param resource $socket @param list<int> $codes */
    private function expect($socket, array $codes, string $stage): void
    {
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP '.$stage.' response timeout');
            }
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int) substr($line, 0, 3), $codes, true)) {
            throw new RuntimeException('SMTP '.$stage.' rejected with status '.substr($line, 0, 3));
        }
    }
}
