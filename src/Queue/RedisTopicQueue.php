<?php

/*
 * This file is part of Swoole-jobs
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs\Queue;

use Kcloze\Jobs\JobObject;
use Kcloze\Jobs\Logs;
use Kcloze\Jobs\Serialize;
use Kcloze\Jobs\Utils;

class RedisTopicQueue extends BaseTopicQueue
{
    /**
     * @var Logs|null
     */
    private $logger = null;

    /**
     * RedisTopicQueue constructor.
     * 使用依赖注入的方式.
     *
     * @param \Redis $redis
     * @param Logs $logger
     */
    public function __construct(\Redis $redis, Logs $logger)
    {
        $this->queue = $redis;
        $this->logger = $logger;
    }

    /**
     * @param array $config
     * @param Logs $logger
     * @return bool|RedisTopicQueue
     */
    public static function getConnection(array $config, Logs $logger)
    {
        try {
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port']);
            if (isset($config['password']) && !empty($config['password'])) {
                $redis->auth($config['password']);
            }
            $connection = new self($redis, $logger);

            return $connection;
        } catch (\Throwable $e) {
            Utils::catchError($logger, $e);

            return false;
        } finally {
            Utils::catchError($logger, $e);

            return false;
        }
    }

    /**
     * 给队列尾部插入一个元素
     * @param string $topic
     * @param JobObject $job
     * @param int $delayStrategy redis这个参数没有用
     * @param string $serializeFunc
     * @return string
     */
    public function push($topic, JobObject $job, $delayStrategy = 1, $serializeFunc = 'php'): string
    {
        if (!$this->isConnected()) {
            return '';
        }

        $this->queue->rPush($topic, Serialize::serialize($job, $serializeFunc));

        return $job->uuid ?? '';
    }

    /**
     * 移除并返回列表的第一个元素
     * @param string $topic
     * @param string $unSerializeFunc
     * @return array|mixed|null
     */
    public function pop($topic, $unSerializeFunc = 'php')
    {
        if (!$this->isConnected()) {
            return null;
        }

        $result = $this->queue->lPop($topic);

        //判断字符串是否是php序列化的字符串，目前只允许serialize和json两种
        $unSerializeFunc = Serialize::isSerial($result) ? 'php' : 'json';

        return !empty($result) ? Serialize::unSerialize($result, $unSerializeFunc) : null;
    }

    /**
     * redis不支持ack功能
     * @return bool
     */
    public function ack(): bool
    {
        return true;
    }

    public function len($topic): int
    {
        if (!$this->isConnected()) {
            return 0;
        }

        return (int)$this->queue->lSize($topic) ?? 0;
    }

    public function purge($topic)
    {
        if (!$this->isConnected()) {
            return 0;
        }

        return (int)$this->queue->ltrim($topic, 1, 0) ?? 0;
    }

    public function delete($topic)
    {
        if (!$this->isConnected()) {
            return 0;
        }

        return (int)$this->queue->delete($topic) ?? 0;
    }

    public function close()
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->queue->close();
    }

    public function isConnected()
    {
        try {
            $this->queue->ping();

            return true;
        } catch (\Throwable $e) {
            Utils::catchError($this->logger, $e);

            return false;
        } finally {
            Utils::catchError($this->logger, $e);

            return false;
        }
    }
}
