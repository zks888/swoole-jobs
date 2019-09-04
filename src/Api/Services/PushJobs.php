<?php

namespace Kcloze\Jobs\Api\Services;

use Kcloze\Jobs\JobObject;
use Kcloze\Jobs\Logs;
use Kcloze\Jobs\Queue\Queue;

class PushJobs
{
    /**
     * @param string $json
     * @return false|string
     */
    public function pushSimple(string $json = '')
    {
        if (!$json) {
            return $this->output(-3, 'job params can not empty.', $json);
        }

        $data = json_decode($json, true);
        if (!$data) {
            return $this->output(-4, 'job params is not json.', $json);
        }

        $data['topic'] = $data['topic'] ?? '';
        $data['jobClass'] = $data['jobClass'] ?? '';
        $data['jobMethod'] = $data['jobMethod'] ?? '';
        $data['jobParams'] = $data['jobParams'] ?? '';
        $data['jobExtras'] = $data['jobExtras'] ?? '';
        $data['serializeFunc'] = $data['serializeFunc'] ?? 'php';

        //检查参数是否有误
        if (!$data['topic'] || !$data['jobClass'] || !$data['jobMethod'] || !$data['jobParams']) {
            return $this->output(-2, 'job params is wrong.', $data);
        }

        $worker = new PushJobs();
        $result = $worker->push($data['topic'], $data['jobClass'], $data['jobMethod'], $data['jobParams'], $data['jobExtras'], $data['serializeFunc']);
        $data['uuid'] = $result;
        if ($result) {
            return $this->output(100, 'job has been pushed success.', $data);
        } else {
            return $this->output(-1, 'job has been pushed fail.', $data);
        }
    }

    public function push($topic, $jobClass, $jobMethod, $jobParams = [], $jobExtras = [], $serializeFunc = 'php')
    {
        $config = require SWOOLE_JOBS_ROOT_PATH . '/config.php';
        $logger = Logs::getLogger($config['logPath'] ?? '', $config['logSaveFileApp'] ?? '');
        $queue = Queue::getQueue($config['job']['queue'], $logger);
        $queue->setTopics($config['job']['topics']);

        // $jobExtras['delay']    =$delay;
        // $jobExtras['priority'] =BaseTopicQueue::HIGH_LEVEL_1;
        $job = new JobObject($topic, $jobClass, $jobMethod, $jobParams, $jobExtras);
        $result = $queue->push($topic, $job, 1, $serializeFunc);
        return $result;
    }

    /**
     * @param int $code
     * @param string $message
     * @param mixed $content
     * @return false|string
     */
    public function output($code = 0, $message = '', $content = '')
    {
        return json_encode(['code' => $code, 'message' => $message, 'content' => $content]);
    }
}
