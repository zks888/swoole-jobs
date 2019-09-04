<?php

/*
 * This file is part of Swoole-jobs
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs\Queue;

use Enqueue\AmqpExt\AmqpConnectionFactory;
use Enqueue\AmqpExt\AmqpContext;
use Enqueue\AmqpTools\RabbitMqDelayPluginDelayStrategy;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Kcloze\Jobs\JobObject;
use Kcloze\Jobs\Logs;
use Kcloze\Jobs\Serialize;
use Kcloze\Jobs\Utils;

class RabbitmqTopicQueue extends BaseTopicQueue
{
    const EXCHANGE = 'php.amqp.ext';

    /**
     * @var Logs|null
     */
    private $logger = null;

    /**
     * @var \Interop\Amqp\AmqpConsumer
     */
    private $consumer = null;

    /**
     * @var null
     */
    private $message = null;

    /**
     * @var AmqpContext|null
     */
    public $context = null;

    /**
     * @param AmqpContext $context
     * @param $exchange
     * @param Logs $logger
     */
    public function __construct(AmqpContext $context, $exchange, Logs $logger)
    {
        $this->logger = $logger;
        $rabbitTopic = $context->createTopic($exchange ?? self::EXCHANGE);
        $rabbitTopic->addFlag(AmqpTopic::FLAG_DURABLE);
        //$rabbitTopic->setType(AmqpTopic::TYPE_FANOUT);
        $context->declareTopic($rabbitTopic);
        $this->context = $context;
    }

    /**
     * @param array $config
     * @param Logs $logger
     * @return bool|RabbitmqTopicQueue
     */
    public static function getConnection(array $config, Logs $logger)
    {
        try {
            $factory = new AmqpConnectionFactory($config);
            $context = $factory->createContext();
            $connection = new self($context, $config['exchange'] ?? null, $logger);

            return $connection;
        } catch (\AMQPConnectionException $e) {
            Utils::catchError($logger, $e);

            return false;
        } catch (\Exception $e) {
            Utils::catchError($logger, $e);

            return false;
        }
    }

    /**
     * @param string $topic
     * @param JobObject $job
     * @param int $delayStrategy
     * @param string $serializeFunc
     * @return string
     * @throws \Interop\Queue\DeliveryDelayNotSupportedException
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\InvalidDestinationException
     * @throws \Interop\Queue\InvalidMessageException
     * @throws \Interop\Queue\PriorityNotSupportedException
     * @throws \Interop\Queue\TimeToLiveNotSupportedException
     */
    public function push($topic, JobObject $job, $delayStrategy = 1, $serializeFunc = 'php'): string
    {
        if (!$this->isConnected()) {
            return '';
        }

        $queue = $this->createQueue($topic);
        if (!is_object($queue)) {
            //对象有误 则直接返回空
            return '';
        }
        $message = $this->context->createMessage(Serialize::serialize($job, $serializeFunc));
        $producer = $this->context->createProducer();
        $delay = $job->jobExtras['delay'] ?? 0;
        $priority = $job->jobExtras['priority'] ?? BaseTopicQueue::HIGH_LEVEL_1;
        $expiration = $job->jobExtras['expiration'] ?? 0;
        if ($delay > 0) {
            //RabbitMQ插件,对消息创建延迟队列
            if (1 == $delayStrategy) {
                $delayStrategyObj = new RabbitMqDelayPluginDelayStrategy();
            }
            //自带队列延迟，变相实现，每个不同的过期时间都会创建队列(不推荐)
            else {
                $delayStrategyObj = new RabbitMqDlxDelayStrategy();
            }
            $producer->setDelayStrategy($delayStrategyObj);
            $producer->setDeliveryDelay($delay);
        }
        if ($priority) {
            $producer->setPriority($priority);
        }
        if ($expiration > 0) {
            $producer->setTimeToLive($expiration);
        }
        $producer->send($queue, $message);

        return $job->uuid ?? '';
    }

    /**
     * @param $topic
     * @param string $unSerializeFunc
     * @return array|mixed|null
     */
    public function pop($topic, $unSerializeFunc = 'php')
    {
        if (!$this->isConnected()) {
            return null;
        }
        //reset consumer and message properties
        $this->consumer = null;
        $this->message = null;

        $queue = $this->createQueue($topic);
        $consumer = $this->context->createConsumer($queue);

        if ($m = $consumer->receive(1)) {
            $result = $m->getBody();
            $this->consumer = $consumer;
            $this->message = $m;
            //判断字符串是否是php序列化的字符串，目前只允许serialzie和json两种
            $unSerializeFunc = Serialize::isSerial($result) ? 'php' : 'json';

            return !empty($result) ? Serialize::unserialize($result, $unSerializeFunc) : null;
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function ack(): bool
    {
        if ($this->consumer && $this->message) {
            $this->consumer->acknowledge($this->message);

            return true;
        }
        throw new \Exception(__CLASS__ . ' properties consumer or message is null !');
    }

    /**
     * 这里的topic跟rabbitmq不一样，其实就是队列名字
     *
     * @param $topic
     * @return int
     */
    public function len($topic): int
    {
        if (!$this->isConnected()) {
            return 0;
        }

        $queue = $this->createQueue($topic);
        if (!is_object($queue)) {
            //对象有误 则直接返回空
            return -1;
        }
        $len = $this->context->declareQueue($queue);

        return $len ?? 0;
    }

    public function purge($topic)
    {
        if (!$this->isConnected()) {
            return 0;
        }
        $queue = $this->createQueue($topic);
        $this->context->purgeQueue($queue);

        return 1;
    }

    public function delete($topic)
    {
        if (!$this->isConnected()) {
            return 0;
        }
        $queue = $this->createQueue($topic);
        $this->context->deleteQueue($queue);

        return 1;
    }

    public function close()
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->context->close();
    }

    public function isConnected()
    {
        return $this->context->getExtChannel()->getConnection()->isConnected();
    }

    private function createQueue($topic)
    {
        try {
            $i = 0;
            do {
                $queue = $this->context->createQueue($topic);
                ++$i;
                if (($queue && $this->isConnected()) || $i >= 3) {
                    //成功 或 链接超过3次则跳出
                    break;
                }
                sleep(1); //延迟1秒
            } while (!$queue);
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
            //$len = $this->context->declareQueue($queue);

            return $queue;
        } catch (\Exception $e) {
            Utils::catchError($this->logger, $e);

            return false;
        }
    }
}
