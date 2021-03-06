<?php
/**
 * User: Jerry.Lee
 * Date: 14-5-4
 * Time: 下午5:09
 */

class PushTask extends \Phalcon\CLI\Task
{

    private $bigv_key = '';
    private $k = '';
    private $q = null;
    private $redis = null;
    private $num = 0;

    /**
     * php cli.php Push run 1
     * @param $num
     */
    public function runAction($num)
    {
        $this->num = $num[0];
        $this->_init();
        $this->_processQueue();
    }

    private function _init()
    {
        $di = $this->getDI();
        $this->k = sprintf(\Util\ReadConfig::get('queue_keys.pushfeeds', $di), $this->num);
        $this->q = \Util\BStalkClient::getInstance($di, 'link_queue1');
        $this->redis = \Util\RedisClient::getInstance($di);
        $this->bigv_key = \Util\ReadConfig::get('setting.big_v_level', $di);
    }

    /**
     * 推送给粉丝，
     *  如果是当前用户是大V则不推送，
     *  直接将数据保存到用户与Feed的关系表
     */
    private function _processQueue()
    {
        $this->q->choose($this->k);
        $this->q->watch($this->k);

        $userRelation = new UserRelationModel($this->getDI());
        $feedRelation = new FeedRelationModel($this->getDI());
        $countRelation = new UserCountModel($this->getDI());
        $userFeedCountModel = new UserFeedCountModel($this->getDI());


        while (false !== $this->q->peekReady()) {
            $job = $this->q->reserve();
            $data = $job->getBody();

            list($app_id, $uid, $feed_id, $time) = explode('|', $data);
            if ($countRelation->setBigv($uid)) {
                $job->delete();
                continue;
            }
            $results = $userRelation->getFansList($uid, 0, $this->bigv_key);

            if ($results) {
                foreach ($results as $result) {
                    $feedRelation->create(array(
                        'app_id' => $app_id,
                        'uid' => $result['friend_uid'],
                        'feed_id' => $feed_id,
                        'create_at' => $time,
                    ));
                    $userFeedCountModel->updateCount(
                        $data['app_id']
                        , $result['friend_uid']
                        , 'unread_count'
                        , 1, true);
                }

            }

            $job->delete();
        }

        if ($this->q) {
            $this->q->disconnect();
        }

        exit;


        try {
            while (1) {
                while (false !== $this->q->peekReady()) {
                    $job = $this->q->reserve();
                    $data = $job->getBody();

                    list($app_id, $uid, $feed_id, $time) = explode('|', $data);
                    if ($countRelation->setBigv($uid)) {
                        $job->delete();
                        continue;
                    }
                    $results = $userRelation->getFansList($uid, 0, $this->bigv_key);

                    if ($results) {
                        foreach ($results as $result) {
                            $feedRelation->create(array(
                                'app_id' => $app_id,
                                'uid' => $result['friend_uid'],
                                'feed_id' => $feed_id,
                                'create_at' => $time,
                            ));
                            $userFeedCountModel->updateCount(
                                $data['app_id']
                                , $result['friend_uid']
                                , 'unread_count'
                                , 1, true);
                        }

                    }

                    $job->delete();
                }

                sleep(3);
            }
        } catch (\Phalcon\Exception $e) {
            if ($this->q) {
                $this->q->disconnect();
            }
            echo $e->getMessage();

            sleep(3);
            $this->runAction(array($this->num));
        }
    }


}