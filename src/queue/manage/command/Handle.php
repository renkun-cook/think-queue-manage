<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: huangweijie <1539369355@qq.com>
// +----------------------------------------------------------------------

namespace huangweijie\queue\manage\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;

class Handle extends Command
{
    /** @var null  */
    private $think          = null;

    /** @var int  */
    private $handleStatus   = 0;

    /** @var array  */
    private $runQueues      = [];

    /** @var array  */
    private $stopQueues     = [];

    /** @var array  */
    private $startQueues    = [];

    /** @var null  */
    private $display        = null;

    /** @var array  */
    private $queues         = [];

    /** @var bool  */
    private $able           = true;

    protected function configure()
    {
        $this->setName('think-queue-manage:handle')
            ->addArgument('connection', Argument::OPTIONAL, 'The name of the queue connection to work', null)
            ->addOption('process', null, Option::VALUE_OPTIONAL, 'display All runing queue', 'false')
            ->addOption('display', null, Option::VALUE_OPTIONAL, 'Display information or not', 'true')
            ->setDescription('think queue manage:handle');
    }

    /**
     * @param Input $input
     * @param Output $output
     */
    protected function initialize(Input $input, Output $output)
    {

        if (strtoupper(PHP_OS ) == 'LINUX') {
            $this->think = $this->app->getRootPath() . 'think';
            return;
        }

        $this->able = false;
        $this->writeMessage(100,'[Warning]:Windows is not supported');
    }

    /**
     * Execute the console command.
     * @param Input $input
     * @param Output $output
     * @return bool|int|null
     */
    protected function execute(Input $input, Output $output)
    {
        if (!$this->able)
            return;

        $connection = $input->getArgument('connection') ?: $this->app->config->get('queue.default');
        $this->queues = $queues = $this->app->config->get("queue.connections.{$connection}.queues", []);
        $displayRunting = (String)$input->getOption('process');
        $this->display = (String)$input->getOption('display');

        if ($displayRunting == 'true') {
            $messages = $this->getRunQueueDetails();
            $messages = empty($messages)? 'No running queue': $messages;
            $this->writeMessage(100, $messages);
            return true;
        }

        if (empty($connection))
            return true;

        $this->cheakOptions();
        $this->runQueues = $this->getRunQueue();

        foreach ($this->runQueues as $runQueue) {
            $processNum = $this->queueProcessNum($runQueue)?? 0;
            $processNum = intval($processNum);
            $pids = $this->getProcessPids($runQueue);

            if (array_key_exists($runQueue, $queues)) {
                $setprocessNum = empty($queues[$runQueue]['processNum'])? 0: intval($queues[$runQueue]['processNum']);
                if ($processNum != $setprocessNum) {
                    $diffNum = abs($setprocessNum - $processNum);
                    $processNum > $setprocessNum? $this->addStopQueues($pids, $diffNum, $runQueue): $this->addStartQueues($runQueue, $diffNum);
                }

                unset($queues[$runQueue]);
                continue;
            }

            $this->addStopQueues($pids, $processNum, $runQueue);
        }

        foreach ($queues as $queue => $parameter) {
            if (empty($queue) || !is_string($queue))
                continue;

            $setprocessNum = empty($parameter['processNum'])? 0: intval($parameter['processNum']);
            $this->addStartQueues($queue, $setprocessNum);
        }

        $this->startMore($this->startQueues);
        $this->stopMore($this->stopQueues);
        $this->writeMessage($this->handleStatus);

        return true;
    }

    /**
     * 输出消息
     * @param $status
     * @param string $messages
     */
    private function writeMessage($status, $messages = '')
    {
        if ($this->display == 'false')
            return;

        switch($status){
            case 200:
                if (!empty($this->startQueues)) {
                    $this->output->writeln('[Success]:start queue list:');
                    $this->output->writeln('queue number');
                    foreach ($this->startQueues as $item){
                        $this->output->writeln($item['queue'] . ' ' . $item['startNum']);
                    }
                }

                if (!empty($this->stopQueues)) {
                    $this->output->writeln('[Success]:stop queue list:');
                    $this->output->writeln('queue number pids');
                    foreach ($this->stopQueues as $item){
                        $this->output->writeln($item['queue'] . ' ' . $item['stopNum'] . ' ' .join(' ', $item['pids']));
                    }
                }
                break;
            case 100:
                $this->output->writeln($messages);
                break;
            default:
                $this->output->writeln('[Info]:nothing');
                break;
        }
    }

    /**
     * 获取指定进程运行的时间(秒)
     * @param $pid
     * @return int
     */
    private function getProcessExeTime($pid)
    {
        $userHz = (int)shell_exec("getconf CLK_TCK");
        $sysUptime = (int)shell_exec("cat /proc/uptime | cut -d\" \" -f1");
        $pidUptime = (int)shell_exec("cat /proc/{$pid}/stat | cut -d\" \" -f22");
        return (int)($sysUptime - ($pidUptime / $userHz));
    }

    /**
     * 获取正在运行的队列进程详情
     * @return string
     */
    private function getRunQueueDetails()
    {
        $processList = shell_exec("ps -eo pid,etime,stat,cmd | grep 'think queue:work --queue='|grep -v 'grep'|grep -v $$");
        $processList = explode(PHP_EOL, $processList);
        $processList = is_array($processList)? $processList: [];
        foreach ($processList as $process) {
            if (empty($process)) {
                continue;
            }

            $processAttr = explode(' ', preg_replace("/\s+/"," ", trim($process)));
            $processStat = strtoupper($processAttr[2]{0});
            $pid = $processAttr[0];

            // T:停止,Z:僵尸,X:死掉 || 超时
            if (in_array($processStat, ['T', 'Z', 'X'])) {
                $this->stopOnce($pid);
            }
        }

        return shell_exec("ps -ef|grep 'think queue:work --queue='|grep -v 'grep'|grep -v $$");
    }

    /**
     * 检查队列选项是否有变化
     * @return bool
     */
    private function cheakOptions()
    {
        $queues = $this->getRunQueueDetails();
        $queues = explode(PHP_EOL, $queues);

        reset($queues);
        while($queue = current($queues)) {
            next($queues);
            $queueOptions = explode(' ', preg_replace("/\s+/"," ", trim($queue)));

            if (count($queueOptions) > 10) {
                $currentQueue = str_replace('--queue=', '', $queueOptions[10]);
                $currentPid = $queueOptions[1];
                $setOptions = $this->queues[$currentQueue]?? [];

                $queueOptions = array_slice($queueOptions,11);
                foreach ($queueOptions as $index => $option) {
                    $currentOption = explode('=', str_replace('--', '', $option));
                    unset($queueOptions[$index]);
                    $queueOptions[$currentOption[0]] = (int)$currentOption[1];
                }

                $allowOption = ['delay' => 0, 'sleep' => 3, 'tries' => 0, 'memory' => 128, 'timeout' => 60];
                $setOptions = array_merge($allowOption, $setOptions);

                // 判断进程是否超时
                $processTimeout = empty($setOptions['processTimeout'])? 3600: (int)$setOptions['processTimeout'];
                $costTime = $this->getProcessExeTime($currentPid);
                if ($costTime >= $processTimeout) {
                    $this->stopOnce($currentPid);
                } else {
                    // 判断队列设置选项是否发生改变
                    foreach ($setOptions as $setOption => $optionValue) {
                        if (array_key_exists($setOption, $allowOption) && (!array_key_exists($setOption, $queueOptions) || $queueOptions[$setOption] != $optionValue)) {
                            $this->stopOnce($currentPid);
                            break;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * 获取正在运行的队列名称
     * @return array
     */
    private function getRunQueue()
    {
        $queues = shell_exec("ps -ef|grep 'think queue:work --queue='|grep -v 'grep'|grep -v $$|awk '{print $11}'");
        $queues = explode(PHP_EOL, $queues);

        foreach ($queues as $index => &$queue) {
            if (empty($queue)) {
                unset($queues[$index]);
                continue;
            }

            $queue = str_replace('--queue=', '', $queue);
            unset($queue);
        }

        return array_unique($queues);
    }

    /**
     * 获取指定队列名称的进程pid
     * @param $queue
     * @return array|string
     */
    private function getProcessPids($queue)
    {
        $pids = shell_exec("ps -ef|grep 'think queue:work --queue={$queue}'|grep -v 'grep'|grep -v $$|awk '{print $2}'");
        $pids = explode(PHP_EOL, $pids);

        foreach ($pids as $index => $pid) {
            if (empty($pid))
                unset($pids[$index]);
        }

        return $pids;
    }

    /**
     * 获取指定队列名称的进程数
     * @param $queue
     * @return string
     */
    private function queueProcessNum($queue)
    {
        return shell_exec("ps -ef|grep 'think queue:work --queue={$queue}'|grep -v grep|wc -l");
    }

    /**
     * 开启一个队列进程
     * @param $queue
     */
    private function startOnce($queue)
    {
        $queueOption = $this->queues[$queue]?? [];
        $command = "nohup php {$this->think} queue:work --queue={$queue}";
        $allowOption = ['delay' => 0, 'sleep' => 3, 'tries' => 0, 'memory' => 128, 'timeout' => 60];

        $queueOption = array_merge($allowOption, $queueOption);
        foreach ($queueOption as $option => $optionValue) {
            if (array_key_exists($option, $allowOption)) {
                $optionValue = (int)$optionValue;
                $command .= " --{$option}={$optionValue}";
            }
        }

        $command = trim($command);
        shell_exec("{$command} >/dev/null 2>&1 &");
    }

    /**
     * 开启多个队列进程
     * @param array $startList
     */
    private function startMore(Array $startList)
    {
        foreach ($startList as $item) {
            $queue = $item['queue'];
            $number = empty($item['startNum'])? 0: intval($item['startNum']);

            while($number != 0) {
                $this->startOnce($queue);
                --$number;
            }
        }
    }

    /**
     * kill一个队列进程
     * @param $pid
     */
    private function stopOnce($pid)
    {
        shell_exec("kill -9 $pid");
    }

    private function stopMore(Array $stopList)
    {
        foreach ($stopList as $item) {
            $pids = $item['pids']?: [];
            $stopNum = empty($item['stopNum'])? 0: intval($item['stopNum']);

            reset($pids);
            while($stopNum && $pid = current($pids)) {
                $this->stopOnce($pid);
                next($pids);
                --$stopNum;
            }
        }
    }

    /**
     * @param $queue
     * @param $startNum
     */
    private function addStartQueues($queue, $startNum)
    {
        if ($startNum > 0) {
            $this->handleStatus = 200;
            $this->startQueues[] = [
                'queue'  => $queue,
                'startNum' => intval($startNum)
            ];
        }
    }

    /**
     * @param array $pids
     * @param int $stopNum
     * @param string $queue
     */
    private function addStopQueues(Array $pids, $stopNum = 0, $queue = '')
    {
        if ($stopNum > 0) {
            $this->handleStatus = 200;
            $this->stopQueues[] = [
                'pids'    => $pids,
                'stopNum' => intval($stopNum),
                'queue'   => $queue
            ];
        }
    }
}