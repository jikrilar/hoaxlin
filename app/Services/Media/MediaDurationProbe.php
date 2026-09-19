<?php

namespace App\Services\Media;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class MediaDurationProbe
{
    public function probePath(string $path): ?float
    {
        $binary = $this->executable();
        if ($binary === null || ! is_file($path)) {
            return null;
        }

        $process = new Process([
            $binary,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);
        $process->setTimeout(10);
        try {
            $process->run();
        } catch (Throwable) {
            return null;
        }
        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return is_numeric($output) ? (float) $output : null;
    }

    public function probeBytes(string $bytes): ?float
    {
        $path = tempnam(sys_get_temp_dir(), 'hoaxlin-media-');
        if ($path === false) {
            return null;
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                return null;
            }

            return $this->probePath($path);
        } finally {
            @unlink($path);
        }
    }

    private function executable(): ?string
    {
        $configured = trim((string) config('media.transcription.ffprobe_binary', 'ffprobe'));
        if ($configured === '') {
            return null;
        }

        if (is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($configured);
    }
}
