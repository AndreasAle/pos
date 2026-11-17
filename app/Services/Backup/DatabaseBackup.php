<?php

namespace App\Services\Backup;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Encrypted MySQL backups for yokapos_app.
 *
 * Format: mysqldump → gzip → libsodium secretstream (XChaCha20-Poly1305), so
 * any tampering or truncation fails on decrypt. The key lives only in the
 * server .env (YOKA_BACKUP_KEY, base64 32 bytes); without it a backup file is
 * useless, so keep a copy of the key outside the server.
 */
class DatabaseBackup
{
    private const CHUNK = 65536;
    private const MAGIC = "YOKABK1\n";

    public function __construct(private array $db, private string $key, private string $dumpBinary = 'mysqldump', private string $clientBinary = 'mysql') {}

    public static function fromConfig(?string $connection = null): self
    {
        $connection ??= config('database.default');
        $db = config("database.connections.$connection");
        $key = base64_decode((string) config('backup.key'), true);
        if (! is_array($db) || ($db['driver'] ?? null) !== 'mysql' && ($db['driver'] ?? null) !== 'mariadb') {
            throw new RuntimeException('Backup only supports mysql/mariadb connections.');
        }
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('YOKA_BACKUP_KEY missing or not base64 of 32 bytes.');
        }

        return new self($db, $key, (string) config('backup.mysqldump'), (string) config('backup.mysql'));
    }

    /** @return array{path:string,bytes:int,sha256:string} */
    public function create(string $dir): array
    {
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create backup dir $dir");
        }
        @chmod($dir, 0700);

        $base = $dir.DIRECTORY_SEPARATOR.$this->db['database'].'_'.date('Ymd_His');
        $plain = $base.'.sql.gz.tmp';
        $final = $base.'.sql.gz.enc';

        try {
            $this->dumpTo($plain);
            $this->encrypt($plain, $final);
        } finally {
            if (is_file($plain)) {
                unlink($plain);
            }
        }
        @chmod($final, 0600);
        $sha = hash_file('sha256', $final);
        file_put_contents($final.'.sha256', $sha.'  '.basename($final)."\n");

        return ['path' => $final, 'bytes' => filesize($final), 'sha256' => $sha];
    }

    /** Decrypts fully, checks checksum and the dump trailer. Returns SQL byte count. */
    public function verify(string $file): int
    {
        $sumFile = $file.'.sha256';
        if (is_file($sumFile) && strtok((string) file_get_contents($sumFile), ' ') !== hash_file('sha256', $file)) {
            throw new RuntimeException('Checksum mismatch.');
        }
        $bytes = 0;
        $tail = '';
        $this->decryptStream($file, function (string $sql) use (&$bytes, &$tail) {
            $bytes += strlen($sql);
            $tail = substr($tail.$sql, -256);
        });
        if (! str_contains($tail, '-- Dump completed')) {
            throw new RuntimeException('Dump trailer missing: backup is incomplete.');
        }

        return $bytes;
    }

    /** Restores into $targetDb. Caller must ensure it is not the live database. */
    public function restore(string $file, string $targetDb): void
    {
        $cnf = $this->credentialsFile();
        try {
            $proc = new Process([$this->clientBinary, '--defaults-extra-file='.$cnf, $targetDb]);
            $proc->setTimeout(3600);
            $proc->setInput($this->sqlGenerator($file));
            $proc->run();
            if (! $proc->isSuccessful()) {
                throw new RuntimeException('mysql restore failed: '.trim($proc->getErrorOutput()));
            }
        } finally {
            unlink($cnf);
        }
    }

    /** @return list<string> deleted files */
    public function prune(string $dir, int $keepDays): array
    {
        $deleted = [];
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz.enc') ?: [] as $f) {
            if (filemtime($f) < time() - $keepDays * 86400) {
                unlink($f);
                @unlink($f.'.sha256');
                $deleted[] = basename($f);
            }
        }

        return $deleted;
    }

    private function dumpTo(string $gzPath): void
    {
        $cnf = $this->credentialsFile();
        $gz = gzopen($gzPath, 'wb6');
        try {
            $proc = new Process([$this->dumpBinary, '--defaults-extra-file='.$cnf, '--single-transaction', '--quick',
                '--routines', '--no-tablespaces', '--default-character-set=utf8mb4', $this->db['database']]);
            $proc->setTimeout(3600);
            $proc->run(function ($type, $buffer) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
            if (! $proc->isSuccessful()) {
                throw new RuntimeException('mysqldump failed: '.trim($proc->getErrorOutput()));
            }
        } finally {
            gzclose($gz);
            unlink($cnf);
        }
    }

    /** Password goes in a 0600 temp file, never on the command line. */
    private function credentialsFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ykbk');
        chmod($path, 0600);
        $q = fn ($v) => '"'.addcslashes((string) $v, '"\\').'"';
        file_put_contents($path, "[client]\nuser={$q($this->db['username'])}\npassword={$q($this->db['password'] ?? '')}\nhost={$q($this->db['host'] ?? '127.0.0.1')}\nport=".(int) ($this->db['port'] ?? 3306)."\n");

        return $path;
    }

    private function encrypt(string $in, string $out): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);
        $r = fopen($in, 'rb');
        $w = fopen($out, 'wb');
        fwrite($w, self::MAGIC.$header);
        $chunk = fread($r, self::CHUNK);
        do {
            $next = fread($r, self::CHUNK);
            $tag = ($next === '' || $next === false) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $chunk, '', $tag);
            fwrite($w, pack('N', strlen($c)).$c);
            $chunk = $next;
        } while ($tag === 0);
        fclose($r);
        fclose($w);
    }

    /** Streams decrypted, gunzipped SQL to $sink. */
    private function decryptStream(string $file, callable $sink): void
    {
        foreach ($this->sqlGenerator($file) as $sql) {
            $sink($sql);
        }
    }

    private function sqlGenerator(string $file): \Generator
    {
        $r = fopen($file, 'rb');
        if (fread($r, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('Not a Yoka backup file.');
        }
        $header = fread($r, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);
        $inflate = inflate_init(ZLIB_ENCODING_GZIP);
        $final = false;
        while (! $final) {
            $len = fread($r, 4);
            if ($len === false || strlen($len) !== 4) {
                throw new RuntimeException('Backup truncated.');
            }
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, fread($r, unpack('N', $len)[1]));
            if ($res === false) {
                throw new RuntimeException('Decryption failed (wrong key or tampered file).');
            }
            [$plain, $tag] = $res;
            $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            $out = inflate_add($inflate, $plain, $final ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
            if ($out === false) {
                throw new RuntimeException('Decompression failed.');
            }
            if ($out !== '') {
                yield $out;
            }
        }
        fclose($r);
    }
}
