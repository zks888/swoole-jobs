<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs;

class JobObject
{
    public $uuid = '';      //job uuid
    public $topic = '';     //job 队列名
    public $class = '';     //job 执行类
    public $method = '';    //job 执行方法
    public $params = [];    //job参数
    public $extras = [];    //附件信息，delay/expiration/priority等

    public function __construct(string $topic, string $class, string $method, array $params = [], array $extras = [], $uuid = '')
    {
        $this->uuid = empty($uuid) ? uniqid($topic) . '.' . Utils::getMillisecond() : $uuid;
        $this->topic = $topic;
        $this->class = $class;
        $this->method = $method;
        $this->params = $params;
        $this->extras = $extras;
    }
}
