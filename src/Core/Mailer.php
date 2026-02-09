<?php

namespace App\Core;

class Mailer
{
    private static ?array $config = null;

    public static function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $config = self::loadConfig();
        $fromAddress = $options['from_address'] ?? $config['from_address'];
        $fromName = $options['from_name'] ?? $config['from_name'];

        $headers = self::buildHeaders($fromAddress, $fromName, $to, $subject);
        $message = self::buildMessage($headers, $body);

        try {
            $socket = self::connect($config);
            if (!$socket) {
                return false;
            }

            $success = self::sendSmtp($socket, $config, $fromAddress, $to, $headers, $message);
            fclose($socket);

            if ($success) {
                Logger::channel('mail')->info('Email sent', ['to' => $to, 'subject' => $subject]);
            }

            return $success;
        } catch (\Throwable $e) {
            Logger::channel('mail')->error('Email failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $viewsPath = $config['paths']['views'];
        $templatePath = $viewsPath . '/' . $template . '.php';

        if (!file_exists($templatePath)) {
            Logger::channel('mail')->error('Email template not found', ['template' => $template]);
            return false;
        }

        extract($data);

        ob_start();
        include $templatePath;
        $body = ob_get_clean();

        return self::send($to, $subject, $body);
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }

    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $mail = $appConfig['mail'] ?? [];

        self::$config = [
            'host'         => $mail['host'] ?? 'localhost',
            'port'         => (int)($mail['port'] ?? 587),
            'username'     => $mail['username'] ?? '',
            'password'     => $mail['password'] ?? '',
            'encryption'   => $mail['encryption'] ?? 'tls',
            'from_address' => $mail['from_address'] ?? 'noreply@example.com',
            'from_name'    => $mail['from_name'] ?? 'App',
        ];

        return self::$config;
    }

    /** @return resource|false */
    private static function connect(array $config)
    {
        $host = $config['host'];
        $port = $config['port'];
        $encryption = $config['encryption'];

        // SSL wraps the entire connection
        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $timeout = $config['timeout'] ?? 10;
        $socket = @stream_socket_client(
            "{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            Logger::channel('mail')->error('SMTP connection failed', [
                'host' => $config['host'],
                'port' => $port,
                'error' => "{$errno}: {$errstr}",
            ]);
            return false;
        }

        stream_set_timeout($socket, $timeout);

        return $socket;
    }

    /** @param resource $socket */
    private static function sendSmtp($socket, array $config, string $from, string $to, string $headers, string $message): bool
    {
        // Read greeting
        if (!self::expectCode($socket, 220)) {
            return false;
        }

        $hostname = gethostname() ?: 'localhost';

        // EHLO
        self::write($socket, "EHLO {$hostname}\r\n");
        if (!self::expectCode($socket, 250)) {
            return false;
        }

        // STARTTLS for port 587
        if ($config['encryption'] === 'tls') {
            self::write($socket, "STARTTLS\r\n");
            if (!self::expectCode($socket, 220)) {
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                Logger::channel('mail')->error('STARTTLS handshake failed');
                return false;
            }

            // Re-EHLO after STARTTLS
            self::write($socket, "EHLO {$hostname}\r\n");
            if (!self::expectCode($socket, 250)) {
                return false;
            }
        }

        // AUTH LOGIN
        if ($config['username'] !== '' && $config['password'] !== '') {
            self::write($socket, "AUTH LOGIN\r\n");
            if (!self::expectCode($socket, 334)) {
                return false;
            }

            self::write($socket, base64_encode($config['username']) . "\r\n");
            if (!self::expectCode($socket, 334)) {
                return false;
            }

            self::write($socket, base64_encode($config['password']) . "\r\n");
            if (!self::expectCode($socket, 235)) {
                return false;
            }
        }

        // MAIL FROM
        self::write($socket, "MAIL FROM:<{$from}>\r\n");
        if (!self::expectCode($socket, 250)) {
            return false;
        }

        // RCPT TO
        self::write($socket, "RCPT TO:<{$to}>\r\n");
        if (!self::expectCode($socket, 250)) {
            return false;
        }

        // DATA
        self::write($socket, "DATA\r\n");
        if (!self::expectCode($socket, 354)) {
            return false;
        }

        // Send headers + body, dot-stuffed
        $data = $headers . "\r\n" . str_replace("\r\n.", "\r\n..", $message) . "\r\n.\r\n";
        self::write($socket, $data);
        if (!self::expectCode($socket, 250)) {
            return false;
        }

        // QUIT
        self::write($socket, "QUIT\r\n");
        // Don't check response — server may close immediately

        return true;
    }

    /** @param resource $socket */
    private static function write($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    /** @param resource $socket */
    private static function expectCode($socket, int $expected): bool
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Multi-line responses have '-' at position 3, last line has ' '
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expected) {
            Logger::channel('mail')->error('Unexpected SMTP response', [
                'expected' => $expected,
                'got' => $code,
                'response' => trim($response),
            ]);
            return false;
        }

        return true;
    }

    private static function buildHeaders(string $fromAddress, string $fromName, string $to, string $subject): string
    {
        $date = date('r');
        $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $fromAddress);

        $headers = "From: {$fromName} <{$fromAddress}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Date: {$date}\r\n";
        $headers .= "Message-ID: {$messageId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit";

        return $headers;
    }

    private static function buildMessage(string $headers, string $body): string
    {
        return $body;
    }
}
