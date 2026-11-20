<?php
// src/Service/RouterOsClient.php
namespace App\Service;

/**
 * Minimal MikroTik RouterOS v6 binary API client (port 8728, or 8729 for api-ssl).
 * Protocol: https://help.mikrotik.com/docs/display/ROS/API
 */
class RouterOsClient
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(private int $timeout = 5) {}

    public function connect(string $host, int $port, string $username, string $password, bool $ssl = false): void
    {
        $context = stream_context_create();
        $scheme = 'tcp';
        if ($ssl) {
            $scheme = 'ssl';
            // RouterOS v6 api-ssl without a certificate only offers anonymous DH ciphers.
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'ciphers', 'ADH:ALL@SECLEVEL=0');
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('%s://%s:%d', $scheme, $host, $port),
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$socket) {
            throw new \RuntimeException(sprintf('Connection to %s:%d failed: %s (%d)', $host, $port, $errstr ?: 'unknown error', $errno));
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        // RouterOS >= 6.43: plain login. Older versions reply with a challenge (=ret=).
        $reply = $this->command('/login', ['name' => $username, 'password' => $password]);
        $ret = $reply[0]['ret'] ?? null;
        if ($ret !== null) {
            $challenge = hex2bin($ret);
            $response = '00' . md5(chr(0) . $password . $challenge);
            $this->command('/login', ['name' => $username, 'response' => $response]);
        }
    }

    /**
     * Run a command and return the !re sentences (plus the !done attributes, if any) as assoc arrays.
     *
     * @param array<string, string|null> $args   =key=value attributes (null value sends =key=)
     * @param array<string>              $queries raw ?query words
     * @return array<int, array<string, string>>
     */
    public function command(string $path, array $args = [], array $queries = []): array
    {
        if (!$this->socket) {
            throw new \RuntimeException('Not connected');
        }

        $words = [$path];
        foreach ($args as $k => $v) {
            $words[] = '=' . $k . '=' . ($v ?? '');
        }
        foreach ($queries as $q) {
            $words[] = $q;
        }
        foreach ($words as $w) {
            $this->writeWord($w);
        }
        $this->writeWord('');

        $rows = [];
        while (true) {
            $sentence = $this->readSentence();
            $type = array_shift($sentence);
            $attrs = $this->parseAttributes($sentence);

            if ($type === '!re') {
                $rows[] = $attrs;
            } elseif ($type === '!done') {
                if ($attrs) {
                    $rows[] = $attrs;
                }
                return $rows;
            } elseif ($type === '!trap') {
                // A trap is followed by !done; drain it, then throw.
                $message = $attrs['message'] ?? 'unknown error';
                do {
                    $next = $this->readSentence();
                } while (($next[0] ?? null) !== '!done');
                throw new \RuntimeException('RouterOS error: ' . $message);
            } elseif ($type === '!fatal') {
                $this->close();
                throw new \RuntimeException('RouterOS fatal: ' . implode(' ', $sentence));
            }
        }
    }

    public function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @param array<string> $words */
    private function parseAttributes(array $words): array
    {
        $attrs = [];
        foreach ($words as $w) {
            if (str_starts_with($w, '=')) {
                $pos = strpos($w, '=', 1);
                if ($pos === false) {
                    $attrs[substr($w, 1)] = '';
                } else {
                    $attrs[substr($w, 1, $pos - 1)] = substr($w, $pos + 1);
                }
            }
        }
        return $attrs;
    }

    /** @return array<string> */
    private function readSentence(): array
    {
        $words = [];
        while (($w = $this->readWord()) !== '') {
            $words[] = $w;
        }
        return $words;
    }

    private function writeWord(string $word): void
    {
        $this->write($this->encodeLength(strlen($word)) . $word);
    }

    private function readWord(): string
    {
        $len = $this->readLength();
        return $len > 0 ? $this->read($len) : '';
    }

    private function encodeLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        if ($len < 0x4000) {
            return pack('n', $len | 0x8000);
        }
        if ($len < 0x200000) {
            $len |= 0xC00000;
            return chr(($len >> 16) & 0xFF) . pack('n', $len & 0xFFFF);
        }
        if ($len < 0x10000000) {
            return pack('N', $len | 0xE0000000);
        }
        return chr(0xF0) . pack('N', $len);
    }

    private function readLength(): int
    {
        $c = ord($this->read(1));
        if (($c & 0x80) === 0x00) {
            return $c;
        }
        if (($c & 0xC0) === 0x80) {
            return (($c & ~0xC0) << 8) + ord($this->read(1));
        }
        if (($c & 0xE0) === 0xC0) {
            $b = $this->read(2);
            return (($c & ~0xE0) << 16) + (ord($b[0]) << 8) + ord($b[1]);
        }
        if (($c & 0xF0) === 0xE0) {
            $b = $this->read(3);
            return (($c & ~0xF0) << 24) + (ord($b[0]) << 16) + (ord($b[1]) << 8) + ord($b[2]);
        }
        return unpack('N', $this->read(4))[1];
    }

    private function write(string $data): void
    {
        $total = strlen($data);
        $sent = 0;
        while ($sent < $total) {
            $n = @fwrite($this->socket, substr($data, $sent));
            if ($n === false || $n === 0) {
                throw new \RuntimeException('Write to RouterOS failed');
            }
            $sent += $n;
        }
    }

    private function read(int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len) {
            $chunk = @fread($this->socket, $len - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                throw new \RuntimeException($meta['timed_out'] ? 'Read from RouterOS timed out' : 'Connection closed by RouterOS');
            }
            $buf .= $chunk;
        }
        return $buf;
    }
}
