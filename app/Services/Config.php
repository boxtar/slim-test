<?php

namespace App\Services;

use App\Contracts\ConfigInterface;
use Psr\Container\ContainerInterface;

class Config implements ConfigInterface
{

    protected $container = [];

    protected $configKey = 'settings';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get($key)
    {
        if (!$key) return;

        $result = $this->container->get($this->configKey);

        foreach (explode('.', $key) as $k) {
            $result = $result[$k];
        }

        return $result;
    }
}
