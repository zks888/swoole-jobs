<?php
/**
 * 死信队列的Worker
 */
namespace Kcloze\Jobs\Jobs;

class DeathWorker
{
    public function test1(...$args)
    {
        echo __CLASS__, '->', __FUNCTION__, '(' . var_export($args, true) . ')', PHP_EOL;
    }
}
