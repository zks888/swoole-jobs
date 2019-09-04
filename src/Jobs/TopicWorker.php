<?php
/**
 * 队列消费的Worker
 *
 * 业务方通过api同步写入的原始消息，根据后台配置的解析规则，按照消费者的TAG复制成对应多个消息，放入LogicWorker队列
 *
 * 这里为了保证主线队列不堆积消息，不做任何业务逻辑
 */
namespace Kcloze\Jobs\Jobs;

class TopicWorker
{
    public function test1(...$args)
    {
        echo __CLASS__, '->', __FUNCTION__, '(' . var_export($args, true) . ')', PHP_EOL;
    }
}
