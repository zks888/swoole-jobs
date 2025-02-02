<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs\Queue;

use Kcloze\Jobs\Logs;

class Queue
{
    public static $_instance = [];

    /**
     * @param array $config
     * @param Logs $logger
     * @return RedisTopicQueue|bool
     * @throws \Exception
     */
    public static function getQueue(array $config, Logs $logger)
    {
        $classQueue = $config['class'] ?? '\Kcloze\Jobs\Queue\RedisTopicQueue';
        if (is_callable([$classQueue, 'getConnection'])) {
            $connection = false;
            //最多尝试连接3次
            for ($i = 0; $i < 3; ++$i) {
                $connection = static::getInstance($classQueue, $config, $logger);
                if ($connection && is_object($connection)) {
                    // $logger->log('connect...,retry=' . ($i + 1), 'info');
                    break;
                }
                $logger->log('connect...,retry=' . ($i + 1), 'error', 'error');
            }
            return $connection;
        }
        $logger->log('queue connection is lost', 'error', 'error');

        return false;
    }

    /**
     * queue连接实体 单例模式.
     * @param string $class
     * @param array $config
     * @param Logs $logger
     * @return bool|RedisTopicQueue
     * @throws \Exception
     */
    public static function getInstance($class, $config, $logger)
    {
        $pid = getmypid();
        $key = md5($pid . $class . serialize($config));
        if (!isset(static::$_instance[$key])) {
            $connection = $class::getConnection($config, $logger);
            if (!is_object($connection)) {
                throw new \Exception('class name:' . $class . ' lost connection, please check config!');
            }
            static::$_instance[$key] = $connection;
        }
        if (static::$_instance[$key]->isConnected()) {
            return static::$_instance[$key];
        }
        static::$_instance[$key] = null;
        $logger->log('queue instance is null', 'error', 'error');

        return false;
    }

}
