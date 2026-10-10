<?php
declare(strict_types=1);

function readAdminInput(string $prompt, bool $secret = false): string
{
    fwrite(STDOUT, $prompt);
    $terminal = null;
    if ($secret) {
        if (!stream_isatty(STDIN)) {
            throw new RuntimeException('Запустите команду в интерактивном терминале.');
        }
        $terminal = trim((string) shell_exec('stty -g'));
        if ($terminal === '') {
            throw new RuntimeException('Не удалось отключить отображение пароля.');
        }
        shell_exec('stty -echo');
    }
    try {
        $line = fgets(STDIN);
        if ($line === false) {
            throw new RuntimeException('Ввод прерван.');
        }
        return rtrim($line, "\r\n");
    } finally {
        if ($terminal !== null) {
            shell_exec('stty ' . escapeshellarg($terminal));
            fwrite(STDOUT, "\n");
        }
    }
}

