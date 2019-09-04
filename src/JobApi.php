<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs;

class JobApi
{
    public $type = 'api';
    public $uuid = '';      //uuid
    public $topic = '';     //队列名
    public $url = '';       //调用api的url
    public $method = '';    //调用API的方式
    public $params = [];    //参数绑定对照关系
    public $extras = [];    //附件信息，delay/expiration/priority等

    public function __construct(string $topic, string $url, string $method, array $params = [], array $extras = [], $uuid = '')
    {
        $this->uuid = empty($uuid) ? uniqid($topic) . '.' . Utils::getMillisecond() : $uuid;
        $this->topic = $topic;
        $this->url = $url;
        $this->method = $method;
        $this->params = $params;
        $this->extras = $extras;
    }
}
