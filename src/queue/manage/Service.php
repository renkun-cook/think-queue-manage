<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: huangweijie <1539369355@qq.com>
// +----------------------------------------------------------------------

namespace huangweijie\queue\manage;

use huangweijie\queue\manage\command\Handle;

class Service extends \think\Service
{

    public function boot()
    {
        $this->commands([
            Handle::class,
        ]);
    }
}