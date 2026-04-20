<?php

/**
 * RouterOS API PHP Client
 * Compatible with MikroTik RouterOS 6.x and 7.x
 * Supports both old (MD5 challenge) and new (plain) login methods
 */
class RouterosAPI
{
    /** @var resource|false */
    private $socket = false;

    /** @var string */
    public $error = '';

    /** @var bool */
    public $debug = false;

    // ----------------------------------------------------------------
    // Connection
    // ----------------------------------------------------------------

    /**
     * Connect and authenticate to the router.
     */
    public function connect(string $host, string $user, string $password, int $port = 8728, int $timeout = 5): bool
    {
        $this->error  = '';
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($this->socket === false) {
            $this->error = "Cannot connect to {$host}:{$port} — {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($this->socket, $timeout);

        // --- Try new-style plain-text login (RouterOS 6.43+) ---
        // We MUST use readResponse() (reads until !done) instead of readSentence()
        // so that ALL sentences (including the closing !done after a !trap) are
        // consumed before we do anything else.  Leaving !done unread and then
        // writing to a socket that the router has already closed causes the
        // "Broken pipe" notice and the subsequent "Login failed" error.
        $this->sendSentence(['/login', '=name=' . $user, '=password=' . $password]);
        $result = $this->readResponse(); // consumes sentences until !done

        if ($result !== false) {
            // readResponse() returns an array on success (no !trap).
            // If the router is old (< 6.43) it ignores =password= and replies
            // with !done =ret=<challenge> — that ends up in $result[0]['ret'].
            if (!empty($result[0]['ret'])) {
                // --- Old-style MD5 challenge-response (same connection) ---
                $challenge = pack('H*', $result[0]['ret']);
                $md5       = '00' . md5(chr(0) . $password . $challenge);
                $this->sendSentence(['/login', '=name=' . $user, '=response=' . $md5]);
                $result2 = $this->readResponse();
                if ($result2 !== false) {
                    return true;
                }
                // error already set by readResponse()
                $this->disconnect();
                return false;
            }

            // Clean !done — new-style login succeeded.
            return true;
        }

        // readResponse() returned false → a !trap was received.
        if ($this->error === '') {
            $this->error = 'Login failed';
        }
        $this->disconnect();
        return false;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== false;
    }

    // ----------------------------------------------------------------
    // High-level command interface
    // ----------------------------------------------------------------

    /**
     * Execute a RouterOS API command.
     *
     * @param string  $command  e.g. '/interface/wireguard/peers/print'
     * @param array   $params   associative array of attribute=value pairs
     * @param array   $queries  array of query words e.g. ['?disabled=no']
     * @return array|false      array of result rows, or false on error
     */
    public function comm(string $command, array $params = [], array $queries = [])
    {
        if (!$this->socket) {
            $this->error = 'Not connected';
            return false;
        }

        $words = [$command];

        foreach ($params as $key => $value) {
            $words[] = '=' . $key . '=' . $value;
        }

        foreach ($queries as $q) {
            $words[] = $q;
        }

        $this->sendSentence($words);
        return $this->readResponse();
    }

    // ----------------------------------------------------------------
    // Wire protocol – write
    // ----------------------------------------------------------------

    private function sendSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }
        $this->writeWord(''); // end-of-sentence
    }

    private function writeWord(string $word): void
    {
        $this->writeLen(strlen($word));
        if (strlen($word) > 0) {
            @fwrite($this->socket, $word);
        }
        if ($this->debug) {
            echo '>>> ' . $word . PHP_EOL;
        }
    }

    private function writeLen(int $len): void
    {
        if ($len < 0x80) {
            @fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            @fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            @fwrite($this->socket, chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            @fwrite(
                $this->socket,
                chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) .
                    chr(($len >> 8)  & 0xFF) . chr($len & 0xFF)
            );
        } else {
            @fwrite(
                $this->socket,
                chr(0xF0) .
                    chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) .
                    chr(($len >> 8)  & 0xFF) . chr($len & 0xFF)
            );
        }
    }

    // ----------------------------------------------------------------
    // Wire protocol – read
    // ----------------------------------------------------------------

    private function readLen(): int
    {
        $b = ord(fread($this->socket, 1));

        if (($b & 0x80) === 0) {
            return $b;
        }
        if (($b & 0xC0) === 0x80) {
            return (($b & ~0x80) << 8) | ord(fread($this->socket, 1));
        }
        if (($b & 0xE0) === 0xC0) {
            $b2 = fread($this->socket, 2);
            return (($b & ~0xC0) << 16) | (ord($b2[0]) << 8) | ord($b2[1]);
        }
        if (($b & 0xF0) === 0xE0) {
            $b3 = fread($this->socket, 3);
            return (($b & ~0xE0) << 24) | (ord($b3[0]) << 16) | (ord($b3[1]) << 8) | ord($b3[2]);
        }
        // 0xF0 – extra byte carries full 32-bit length
        $b4 = fread($this->socket, 4);
        return (ord($b4[0]) << 24) | (ord($b4[1]) << 16) | (ord($b4[2]) << 8) | ord($b4[3]);
    }

    private function readWord(): string
    {
        $len = $this->readLen();
        if ($len === 0) {
            return '';
        }

        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $word      .= $chunk;
            $remaining -= strlen($chunk);
        }

        if ($this->debug) {
            echo '<<< ' . $word . PHP_EOL;
        }
        return $word;
    }

    private function readSentence(): array
    {
        $sentence = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }
            $sentence[] = $word;
        }
        return $sentence;
    }

    private function readResponse(): array
    {
        $this->error = '';
        $rows        = [];

        while (true) {
            $sentence = $this->readSentence();
            if (empty($sentence)) {
                continue;
            }

            $type = $sentence[0];

            // Parse key=value pairs from this sentence
            $row = [];
            for ($i = 1; $i < count($sentence); $i++) {
                $word = $sentence[$i];
                if (strpos($word, '=') !== false) {
                    // words look like  =key=value
                    $parts = explode('=', $word, 3);
                    if (count($parts) === 3) {
                        $row[$parts[1]] = $parts[2];
                    }
                }
            }

            if ($type === '!re') {
                $rows[] = $row;
            } elseif ($type === '!trap') {
                $this->error = $row['message'] ?? 'RouterOS error';
                return [];
            } elseif ($type === '!done') {
                // Some commands return data with !done (e.g. generate-keypair)
                if (!empty($row)) {
                    $rows[] = $row;
                }
                break;
            }
        }

        return $rows;
    }
}
