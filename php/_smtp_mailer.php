<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

final class DmportalSmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $from;

    public function __construct()
    {
        $this->host = (string)dmportal_env('DM_PORTAL_SMTP_HOST', '');
        $this->port = (int)dmportal_env('DM_PORTAL_SMTP_PORT', '0');
        $this->encryption = strtolower((string)dmportal_env('DM_PORTAL_SMTP_ENCRYPTION', 'tls'));
        $this->username = (string)dmportal_env('DM_PORTAL_SMTP_USER', '');
        $this->password = (string)dmportal_env('DM_PORTAL_SMTP_PASS', '');
        $this->from = (string)dmportal_env('DM_PORTAL_SMTP_FROM', $this->username);
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->port > 0 && $this->username !== '' && $this->password !== '' && $this->from !== '';
    }

    /**
     * @param array<int, array{name:string,mime:string,data:string}> $attachments
     * @param array<int, string> $cc
     */
    public function send(string $to, string $subject, string $body, array $attachments = [], array $cc = []): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SMTP is not configured.');
        }

        $to = trim($to);
        if ($to === '') {
            throw new RuntimeException('Recipient is required.');
        }

        $ccList = $this->normalizeAddressList($cc);

        $socket = $this->connect();
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO dmportal');
            $this->expect($socket, 250);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Failed to enable TLS.');
                }
                $this->command($socket, 'EHLO dmportal');
                $this->expect($socket, 250);
            }

            $this->command($socket, 'AUTH LOGIN');
            $this->expect($socket, 334);
            $this->command($socket, base64_encode($this->username));
            $this->expect($socket, 334);
            $this->command($socket, base64_encode($this->password));
            $this->expect($socket, 235);

            $this->command($socket, 'MAIL FROM:<' . $this->from . '>');
            $this->expect($socket, 250);
            $this->command($socket, 'RCPT TO:<' . $to . '>');
            $this->expect($socket, 250);
            foreach ($ccList as $ccAddress) {
                $this->command($socket, 'RCPT TO:<' . $ccAddress . '>');
                $this->expect($socket, 250);
            }

            $this->command($socket, 'DATA');
            $this->expect($socket, 354);

            $message = $this->buildMimeMessage($to, $subject, $body, $attachments, $ccList);
            $this->write($socket, $message . "\r\n.");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    private function normalizeAddressList(array $addresses): array
    {
        $clean = [];
        foreach ($addresses as $addr) {
            $value = trim((string)$addr);
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }
        return $clean;
    }

    private function connect()
    {
        $target = 'tcp://' . $this->host . ':' . $this->port;
        $socket = stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException('Failed to connect to SMTP: ' . $errstr);
        }
        stream_set_timeout($socket, 20);
        return $socket;
    }

    private function buildMimeMessage(string $to, string $subject, string $body, array $attachments, array $cc): string
    {
        $boundary = 'dmportal_' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . $this->from,
            'To: ' . $to,
        ];
        if (!empty($cc)) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $lines = [];
        $lines[] = '--' . $boundary;
        $lines[] = 'Content-Type: text/plain; charset="utf-8"';
        $lines[] = 'Content-Transfer-Encoding: 7bit';
        $lines[] = '';
        $lines[] = $body;

        foreach ($attachments as $attachment) {
            $fileName = (string)($attachment['name'] ?? 'attachment');
            $mime = (string)($attachment['mime'] ?? 'application/octet-stream');
            $data = (string)($attachment['data'] ?? '');
            $encoded = chunk_split(base64_encode($data));

            $lines[] = '';
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Type: ' . $mime . '; name="' . $fileName . '"';
            $lines[] = 'Content-Disposition: attachment; filename="' . $fileName . '"';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = $encoded;
        }

        $lines[] = '';
        $lines[] = '--' . $boundary . '--';

        return implode("\r\n", array_merge($headers, [''], $lines));
    }

    private function command($socket, string $command): void
    {
        $this->write($socket, $command);
    }

    private function write($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    private function expect($socket, int $code): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        if ((int)substr($response, 0, 3) !== $code) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }
}
