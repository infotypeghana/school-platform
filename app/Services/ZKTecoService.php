<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco binary TCP protocol client.
 *
 * Supported devices: K40, K50, K60, F18, uFace series,
 * and any device running ZKTeco firmware with TCP port 4370.
 *
 * Protocol reference:
 *   - Packet: 8-byte header (command, checksum, session_id, reply_counter) + data
 *   - Time format: 32-bit ZKTeco epoch (not Unix)
 *   - Attendance record: 40 bytes per entry
 */
class ZKTecoService
{
    // ── Commands ──────────────────────────────────────────────────────────────
    private const CMD_CONNECT      = 1000;
    private const CMD_EXIT         = 1001;
    private const CMD_ENABLEDEVICE = 1002;
    private const CMD_DISABLEDEVICE= 1003;
    private const CMD_ATTLOG_RRQ   = 13;
    private const CMD_PREPARE_DATA = 1500;
    private const CMD_DATA         = 1501;
    private const CMD_FREE_DATA    = 1502;
    private const CMD_ACK_OK       = 2000;
    private const CMD_ACK_ERROR    = 2001;

    private const HEADER_SIZE     = 8;
    private const ATTLOG_REC_SIZE = 40;

    // ── State ─────────────────────────────────────────────────────────────────
    private mixed  $socket      = null;
    private int    $sessionId   = 0;
    private int    $replyCounter= 0;
    private string $ip          = '';
    private int    $port        = 4370;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Open a TCP session to the device.
     *
     * @param  string $ip       Device LAN IP
     * @param  int    $port     Default 4370
     * @param  int    $password Device password (0 = none)
     * @param  int    $timeout  Socket timeout in seconds
     */
    public function connect(string $ip, int $port = 4370, int $password = 0, int $timeout = 10): bool
    {
        $this->ip   = $ip;
        $this->port = $port;

        $sock = @fsockopen("tcp://{$ip}", $port, $errno, $errstr, $timeout);

        if (! $sock) {
            Log::warning("ZKTeco: cannot connect to {$ip}:{$port} — {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($sock, $timeout);
        $this->socket      = $sock;
        $this->sessionId   = 0;
        $this->replyCounter= 0;

        // Send CMD_CONNECT; password as little-endian 32-bit int
        $payload  = pack('V', $password);
        $response = $this->sendCommand(self::CMD_CONNECT, $payload);

        if ($response === false || $response['command'] !== self::CMD_ACK_OK) {
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        // Session ID is the session_id field of the ACK response
        $this->sessionId = $response['session_id'];
        return true;
    }

    /**
     * Disconnect cleanly from the device.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            $this->sendCommand(self::CMD_EXIT);
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Download all attendance log entries from the device.
     *
     * @return array<int, array{user_id: string, timestamp: Carbon, verify_type: int, direction: int}>
     */
    public function getAttendanceLogs(): array
    {
        if (! $this->socket) {
            return [];
        }

        // Disable device interactions while reading
        $this->sendCommand(self::CMD_DISABLEDEVICE);

        $response = $this->sendCommand(self::CMD_ATTLOG_RRQ);

        if ($response === false) {
            $this->sendCommand(self::CMD_ENABLEDEVICE);
            return [];
        }

        // Read multi-packet response
        $rawData = $this->readLargeData($response);

        // Re-enable device
        $this->sendCommand(self::CMD_FREE_DATA);
        $this->sendCommand(self::CMD_ENABLEDEVICE);

        return $this->parseAttendanceLogs($rawData);
    }

    /**
     * Return the number of attendance records stored on the device.
     */
    public function getAttendanceCount(): int
    {
        return count($this->getAttendanceLogs());
    }

    // ── ADMS payload parser (static — no socket needed) ──────────────────────

    /**
     * Parse a ZKTeco ADMS push payload (POST body from device).
     *
     * Format per line:  USER_ID\tDATETIME\tVERIFY_TYPE\tDIRECTION\r\n
     * Example:          1\t2024-01-15 08:30:00\t1\t0\r\n
     *
     * @return array<int, array{user_id: string, timestamp: Carbon, verify_type: int, direction: int}>
     */
    public static function parseAdmsPayload(string $body): array
    {
        $records = [];

        foreach (explode("\n", trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 2) {
                continue;
            }

            $userId     = trim($parts[0]);
            $rawTime    = trim($parts[1]);
            $verifyType = isset($parts[2]) ? (int) $parts[2] : 0;
            $direction  = isset($parts[3]) ? (int) $parts[3] : 0;

            try {
                $timestamp = Carbon::createFromFormat('Y-m-d H:i:s', $rawTime);
            } catch (\Exception) {
                continue; // skip malformed entries
            }

            if ($userId === '' || ! $timestamp) {
                continue;
            }

            $records[] = [
                'user_id'     => $userId,
                'timestamp'   => $timestamp,
                'verify_type' => $verifyType,
                'direction'   => $direction,
            ];
        }

        return $records;
    }

    // ── Internal protocol helpers ─────────────────────────────────────────────

    private function sendCommand(int $command, string $data = ''): array|false
    {
        if (! $this->socket) {
            return false;
        }

        $packet = $this->buildPacket($command, $data);

        if (@fwrite($this->socket, $packet) === false) {
            return false;
        }

        return $this->readPacket();
    }

    /**
     * Build an 8-byte header + data packet with proper checksum.
     */
    private function buildPacket(int $command, string $data = ''): string
    {
        // First pass — checksum = 0
        $header = pack('vvvv', $command, 0, $this->sessionId, $this->replyCounter);
        $raw    = $header . $data;

        $checksum = $this->calcChecksum($raw);

        // Second pass — embed real checksum
        $this->replyCounter = ($this->replyCounter + 1) & 0xFFFF;

        return pack('vvvv', $command, $checksum, $this->sessionId, $this->replyCounter - 1) . $data;
    }

    /**
     * Read one response packet from the socket.
     */
    private function readPacket(): array|false
    {
        $header = $this->readBytes(self::HEADER_SIZE);
        if ($header === false || strlen($header) < self::HEADER_SIZE) {
            return false;
        }

        $unpacked = unpack('vcommand/vchecksum/vsession_id/vreply_counter', $header);

        // Peek at remaining data until socket is drained (for short responses)
        $data = '';
        $meta = stream_get_meta_data($this->socket);
        if (! $meta['timed_out']) {
            // Read any immediately available data (non-blocking peek)
            $data = (string) @fread($this->socket, 65536);
        }

        // Update session_id if first connect
        if ($unpacked['command'] === self::CMD_ACK_OK && $this->sessionId === 0) {
            $this->sessionId = $unpacked['session_id'];
        }

        return array_merge($unpacked, ['data' => $data]);
    }

    /**
     * Read large data split across CMD_PREPARE_DATA + multiple CMD_DATA packets.
     */
    private function readLargeData(array $firstResponse): string
    {
        $command = $firstResponse['command'];

        // Short response — data inline
        if ($command !== self::CMD_PREPARE_DATA) {
            return $firstResponse['data'] ?? '';
        }

        // PREPARE_DATA: first 4 bytes of data = total expected size
        $totalSize = isset($firstResponse['data']) && strlen($firstResponse['data']) >= 4
            ? unpack('V', substr($firstResponse['data'], 0, 4))[1]
            : 0;

        $buffer = '';
        while (strlen($buffer) < $totalSize) {
            $packet = $this->readPacket();

            if ($packet === false) {
                break;
            }

            if ($packet['command'] === self::CMD_DATA) {
                $buffer .= $packet['data'];
            } elseif ($packet['command'] === self::CMD_ACK_OK) {
                break;
            }
        }

        return $buffer;
    }

    /**
     * @return array<int, array{user_id: string, timestamp: Carbon, verify_type: int, direction: int}>
     */
    private function parseAttendanceLogs(string $raw): array
    {
        $records = [];
        $len     = strlen($raw);
        $offset  = 0;

        while ($offset + self::ATTLOG_REC_SIZE <= $len) {
            $chunk = substr($raw, $offset, self::ATTLOG_REC_SIZE);
            $offset += self::ATTLOG_REC_SIZE;

            // user_id: 9 bytes, null-terminated ASCII
            $userId = rtrim(substr($chunk, 0, 9), "\x00");
            if ($userId === '') {
                continue;
            }

            $verifyType = ord($chunk[9]);

            // ZKTeco 4-byte time (little-endian)
            $zkTime    = unpack('V', substr($chunk, 10, 4))[1];
            $timestamp = $this->decodeZKTime($zkTime);
            if ($timestamp === null) {
                continue;
            }

            $direction = ord($chunk[14]);

            $records[] = [
                'user_id'     => $userId,
                'timestamp'   => $timestamp,
                'verify_type' => $verifyType,
                'direction'   => $direction,
            ];
        }

        return $records;
    }

    /**
     * Decode ZKTeco proprietary 32-bit timestamp.
     *
     * Bit layout (LSB → MSB):
     *   [4:0]  second / 2
     *   [10:5] minute
     *   [15:11] hour
     *   [20:16] day
     *   [24:21] month
     *   [30:25] year − 2000
     */
    private function decodeZKTime(int $t): ?Carbon
    {
        $second = ($t & 0x1F) * 2;
        $minute = ($t >> 5) & 0x3F;
        $hour   = ($t >> 11) & 0x1F;
        $day    = ($t >> 16) & 0x1F;
        $month  = ($t >> 21) & 0x0F;
        $year   = (($t >> 26) & 0x3F) + 2000;

        if ($year < 2000 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day, $hour, $minute, $second);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Read exactly $length bytes from the socket, waiting as needed.
     */
    private function readBytes(int $length): string|false
    {
        $buffer = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                return $buffer === '' ? false : $buffer;
            }
            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $buffer;
    }

    /**
     * ZKTeco one's-complement checksum over pairs of bytes.
     */
    private function calcChecksum(string $data): int
    {
        $sum = 0;
        $len = strlen($data);
        $i   = 0;

        while ($i < $len - 1) {
            $sum += unpack('v', $data[$i] . $data[$i + 1])[1];
            $i += 2;
        }

        if ($i < $len) {
            $sum += ord($data[$i]);
        }

        // Fold carry bits
        while ($sum >> 16) {
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        return (~$sum) & 0xFFFF;
    }
}
