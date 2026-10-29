<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Lock;

final class AcquiredIntegrationLock
{
    private bool $released = false;

    /** @param resource $handle */
    public function __construct(private $handle) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->released = true;
    }

    public function __destruct()
    {
        $this->release();
    }
}

