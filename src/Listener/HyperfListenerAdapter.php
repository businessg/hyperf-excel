<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\AbstractBaseListener;
use Hyperf\Event\Contract\ListenerInterface;

class HyperfListenerAdapter implements ListenerInterface
{
    public function __construct(private AbstractBaseListener $listener)
    {
    }

    public function listen(): array
    {
        return $this->listener->listen();
    }

    public function process(object $event): void
    {
        $this->listener->process($event);
    }
}
