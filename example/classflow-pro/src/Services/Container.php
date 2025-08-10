<?php
declare(strict_types=1);

namespace ClassFlowPro\Services;

class Container {
    private array $services = [];
    private array $instances = [];

    public function register(string $name, callable $factory): void {
        $this->services[$name] = $factory;
    }

    public function get(string $name) {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service '{$name}' not found in container.");
        }

        if (!isset($this->instances[$name])) {
            $this->instances[$name] = ($this->services[$name])();
        }

        return $this->instances[$name];
    }

    public function has(string $name): bool {
        return isset($this->services[$name]);
    }

    public function factory(string $name) {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service '{$name}' not found in container.");
        }

        return ($this->services[$name])();
    }
}