<?php
/**
 * 业务逻辑处理的worker
 *
 * 根据消息的具体内容，调用第三方模块，失败会重试，失败次数超过后台设定上限的，将打入死信队列
 */
namespace Kcloze\Jobs\Jobs;

class LogicWorker
{
    public function test1(...$args)
    {
        echo __CLASS__, '->', __FUNCTION__, '(' . var_export($args, true) . ')', PHP_EOL;
    }

    //调用API
    private function api()
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
        echo $res->getStatusCode();
        echo $res->getBody();
    }

    //写入QUEUE
    private function queue()
    {

    }
}
