<?php

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Browser;

class ChromeManager
{
    private array $config;
    private ?Browser $browser = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $profileDir = $this->config['profile_dir'];

        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0777, true);
            chmod($profileDir, 0777);
        }

        foreach (['SingletonLock', 'SingletonSocket', 'SingletonCookie'] as $lockFile) {
            $path = $profileDir . '/' . $lockFile;
            if (file_exists($path) || is_link($path)) {
                @unlink($path);
            }
        }
    }

    public function getBrowser(): Browser
    {
        if ($this->browser === null) {
            $profileDir = $this->config['profile_dir'];
            $factory = new BrowserFactory($this->config['chrome_binary']);

            $flags = [
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-crash-reporter'
            ];

            if (!empty($this->config['headless'])) {
                $flags[] = '--headless=new';
            }

            $this->browser = $factory->createBrowser([
                'headless'    => false, // Controlado via --headless=new acima
                'userDataDir' => $profileDir,
                'noSandbox'   => true,
                'windowSize'  => [1366, 768],
                'userAgent'   => $this->config['user_agent'],
                'customFlags' => $flags
            ]);
        }
        return $this->browser;
    }

    public function close(): void
    {
        if ($this->browser !== null) {
            $this->browser->close();
            $this->browser = null;
        }
    }
}