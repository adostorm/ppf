<?php
/**
 * User: Jerry.Lee
 * Date: 14-4-29
 * Time: 上午11:22
 */

class UserRelationModel extends \HsMysql\Model
{

    public $dbname = 'userstate';

    public $tbname = 'user_relation';

    public $index = 'idx0';

    public $redis = null;

    public $cache_follow_key = '';

    public $cache_fans_key = '';

    public function __construct($di) {
        parent::__construct($di, '');
        $this->redis = \Util\RedisClient::getInstance($di);
        $this->cache_follow_key = \Util\ReadConfig::get('redis_cache_keys.follow_uid_list', $di);
        $this->cache_fans_key = \Util\ReadConfig::get('redis_cache_keys.fans_uid_list', $di);
    }

    public function checkRelation($friend_uid, $uid)
    {
        $this->setIsAssociate(false);
        $result = $this->field('status')->filter(array(
            array('friend_uid', '=', $friend_uid)
        ))->find($uid);
        return isset($result[0]) ? intval($result[0]) : -99;
    }

    public function createRelation($uid, $friend_uid)
    {
        $status = $this->checkRelation($friend_uid, $uid);

        $tempStatus = $status;

        switch($status) {
            case -99:
                $status = 2;
                $time = time();
                $this->insert(array(
                    'uid'=>$uid,
                    'friend_uid'=>$friend_uid,
                    'status'=>0,
                    'create_at'=>$time,
                ));
                $this->insert(array(
                    'uid'=>$friend_uid,
                    'friend_uid'=>$uid,
                    'status'=>$status,
                    'create_at'=>$time,
                ));
                break;

            case -1:
                $status = 2;
                $this->filter(array(
                    array('friend_uid', '=', $friend_uid),
                ))->update($uid, array(
                        'status'=>0
                    ));
                $this->filter(array(
                    array('friend_uid', '=', $uid),
                ))->update($friend_uid, array(
                        'status'=>$status
                    ));
                break;

            case 0:
                $status = 1;
                $this->filter(array(
                    array('friend_uid', '=', $friend_uid),
                ))->update($uid, array(
                        'status'=>1
                    ));
                $this->filter(array(
                    array('friend_uid', '=', $uid),
                ))->update($friend_uid, array(
                        'status'=>$status
                    ));
                break;
        }


        if(in_array($tempStatus, array(-99, -1, 0))) {
            $countModel = new UserCountModel($this->getDi());
            $countModel->updateCount($uid, 'follow_count', 1);
            $countModel->updateCount($friend_uid, 'fans_count', 1);
        }

        return $status;
    }

    public function removeRelation($uid, $friend_uid)
    {
        $status = $this->checkRelation($friend_uid, $uid);

        $tempStatus = $status;

        switch($status) {
            case 2:
                $status = -1;
                $this->filter(array(
                    array('friend_uid', '=', $friend_uid),
                ))->update($uid, array(
                        'status'=>-1
                    ));
                $this->filter(array(
                    array('friend_uid', '=', $uid),
                ))->update($friend_uid, array(
                        'status'=>-$status
                    ));
                break;

            case 1:
                $status = 0;
                $this->filter(array(
                    array('friend_uid', '=', $friend_uid),
                ))->update($uid, array(
                        'status'=>2
                    ));
                $this->filter(array(
                    array('friend_uid', '=', $uid),
                ))->update($friend_uid, array(
                        'status'=>$status
                    ));
                break;
        }

        if(in_array($tempStatus, array(2, 1))) {
            $countModel = new UserCountModel($this->getDi());
            $countModel->updateCount($uid, 'follow_count', 1, false);
            $countModel->updateCount($friend_uid, 'fans_count', 1, false);
        }

        return $status;
    }

    public function getRelationList()
    {

    }

    public function getFollowList($uid, $offset=0, $limit=15)
    {
        $result = $this->field('uid,friend_uid,status,create_at,weight')->filter(array(
            array('status', '>=', 0),
            array('status', '<=', 1),
        ))->limit($offset, $limit)->find($uid);
        return $result;
    }

    public function getFansList($uid, $offset=0, $limit=15)
    {

        $result = $this->field('uid,friend_uid,status,create_at,weight')->filter(array(
            array('status', '>', 0),
        ))->limit($offset, $limit)->find($uid);
        return $result;
    }

}