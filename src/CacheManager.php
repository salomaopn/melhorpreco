<?php

class CacheManager
{
    private string $dir;
    private int $ttl;

    public function __construct(string $dir, int $ttl)
    {
        $this->dir = $dir;
        $this->ttl = $ttl;

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
            chmod($this->dir, 0777);
        }
    }

    private function getFilePath(string $termo): string
    {
        return $this->dir . '/' . md5(mb_strtolower(trim($termo))) . '.json';
    }

    public function get(string $termo): ?string
    {
        $arquivo = $this->getFilePath($termo);
        if (file_exists($arquivo) && (time() - filemtime($arquivo) < $this->ttl)) {
            return file_get_contents($arquivo);
        }
        return null;
    }

    public function set(string $termo, string $jsonContent): void
    {
        $arquivo = $this->getFilePath($termo);
        file_put_contents($arquivo, $jsonContent);
    }
}