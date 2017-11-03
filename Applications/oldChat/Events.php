<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

// declare(ticks=1);

use \Workerman\Lib\Sudo;
use \GatewayWorker\Lib\Gateway;

class Events {

    public static function onMessage($client_id, $message) {

        $msg = json_decode($message, true);
        if(!$msg) return;

        switch($msg['eventName']) {
            case '__join':
                if(!isset($msg['room'])||!isset($msg['user'])) return;
                $room = $msg['room'];
                $user = $msg['user'];
                $token = $msg['token'];
                Gateway::bindUid($client_id,$user['uid']);
                self::setCurrentSession($room,$user,$token);
                // 传送当前客户端
                $log_add = self::getLogLive(0,5,'add');
                if(!empty($log_add))
                    Gateway::sendToCurrentClient(json_encode(array('eventName'=>'logs','data'=>$log_add)));
                $log = self::getLogLive();
                if(!empty($log))
                    Gateway::sendToCurrentClient(json_encode(array('eventName'=>'logs','data'=>$log)));
                // 加入房间
                Gateway::joinGroup($client_id, $room);
                self::sendAll($user,'add');
                return;
            case '__msg':
                if(!isset($_SESSION['room_id'])) return;
                if(self::isBanner()) return;
                $msg = $msg['data'];
                $data = array('from_uid'=>$_SESSION['uid'],'to_uid'=>'all','type'=>$msg['type'],'content'=>$msg['content']);
                self::sendAll($data,'broadcast');
                return;
            case '__favor':
                if(!isset($_SESSION['room_id'])) return;
                $msg = $msg['data'];
                $data = array('from_uid'=>$_SESSION['uid'],'to_uid'=>'all','id'=>$msg['id']);
                self::sendAll($data,'favor');
                return;
            case '__banner':
                if(!isset($_SESSION['room_id'])) return;
                if(!isset($_SESSION['auth'])) return;
                $msg = $msg['data'];
                $res = self::getLocked();
                if(is_array($msg)&&$msg['uid']) {
                    $res['banner'] = true;
                    if(isset($res['blacklist']) AND in_array($msg['uid'],$res['blacklist'])){
                        foreach ($res['blacklist'] as $k=>$v) {if($v==$msg['uid'])unset($res['blacklist'][$k]);}
                        $pb = false;
                    } else {
                        $res['blacklist'][]=$msg['uid'];
                        $pb = true;
                    }
                    Gateway::sendToUid($msg['uid'],json_encode(array('eventName'=>'banner','data'=>array(
                        'from_uid'=>$_SESSION['uid'],'to_uid'=>$msg['uid'],'status'=>$pb,'time'=>date('Y-m-d H:i:s',time())
                    ))));
                    Gateway::sendToCurrentClient(json_encode(array('eventName'=>'banner','data'=>array(
                        'from_uid'=>$_SESSION['uid'],'to_uid'=>$msg['uid'],'status'=>$pb,'time'=>date('Y-m-d H:i:s',time())
                    ))));
                }elseif($msg=='all'){
                    if(isset($res['banner']) AND $res['banner']) $res['banner'] = false;
                    else $res['banner'] = true;
                    if(isset($res['blacklist'])) unset($res['blacklist']);
                    self::sendAll(array('from_uid'=>$_SESSION['uid'],'to_uid'=>'all','status'=>$res['banner']),'banner');
                }
                self::setLocked($res,true);
                return;
            case '__end':
                if(!isset($_SESSION['room_id'])) return;
                self::sendAll(array(),'end');
                return;
            case '__ping':
                Gateway::sendToCurrentClient(json_encode(array('eventName'=>'pong')));
                return;
            default:
                if(!isset($_SESSION['room_id'])) return;
                return Gateway::closeClient($client_id);
        }
    }

    public static function onClose($client_id) {
        if(isset($_SESSION['room_id'])) {
            self::sendAll(array('from_uid'=>$_SESSION['uid']),'remove');
        }
    }

    // $event:[remove banner broadcast add]
    private static function sendAll($data,$event){
        $time = time();
        $new_message['eventName'] = $event;
        $new_message['data'] = $data;
        $new_message['data']['time'] = date('Y-m-d H:i:s',$time);
        if($event=='broadcast') $new_message['data']['uniqid'] = uniqid();
        Gateway::sendToGroup($_SESSION['room_id'], json_encode($new_message));
        if(in_array($event,array('broadcast','add','favor'))) self::logLive($new_message,$event);
    }

    private static function isBanner(){
        if(isset($_SESSION['auth'])) return false;
        $res = self::getLocked();
        if(!isset($res['banner']) OR !$res['banner']) return false;
        if(isset($res['blacklist'])){
            if(!is_array($res['blacklist']) OR !in_array($_SESSION['uid'],$res['blacklist'])) return false;
            Gateway::sendToCurrentClient(json_encode(array(
                'eventName'=>'banner',
                'data'=>array('from_uid'=>$res['uid'],'to_uid'=>$_SESSION['uid'],'status'=>$res['banner'],'time'=>date('Y-m-d H:i:s'))
            )));
        } else {
            Gateway::sendToCurrentClient(json_encode(array(
                'eventName'=>'banner',
                'data'=>array('from_uid'=>$res['uid'],'to_uid'=>'all','status'=>$res['banner'],'time'=>date('Y-m-d H:i:s'))
            )));
        }
        return true;
    }

    private static function setCurrentSession($room,$user,$token){
        $_SESSION = $user;
        $_SESSION['room_id'] = $room;
        if($token){
            $s2 = Sudo::decrypt($token);
            if($s2) $arr = explode("\t", $s2); else return;
            if($arr[1]!='tea') return;
            $_SESSION['auth'] = true;
            self::setLocked(array('uid'=>$arr[0]),true);
        };
    }

    private static function setFavor(){

    }
    private static function getFavor(){

    }

    private static function setLocked($arr,$sign=null){
        if(empty($sign)) {
            $res = self::getLocked();
            if(is_array($res)) array_merge($res,$arr);
        } else $res = $arr;
        $r = Sudo::xn_json_encode($res);
//        $path = 'Log/'.$_SESSION['room_id'].date('-Ymd').'locked';
        $path = 'Log/'.$_SESSION['room_id'].'locked';
        file_put_contents($path, $r);
    }
    private static function getLocked(){
//        $path = 'Log/'.$_SESSION['room_id'].date('-Ymd').'locked';
        $path = 'Log/'.$_SESSION['room_id'].'locked';
        if (!file_exists($path)) return false;
        $read = file_get_contents($path);
        $res = json_decode($read,1);
        return $res;
    }

    private static function logLive($arr,$sign=''){
        if($sign=='add'){
            $logs = self::getLogLive(0,5,'add');
            if(is_array($logs)) foreach($logs as $v) if($v['data']['uid']==$arr['data']['uid']) return;
        } elseif(in_array($sign,array('broadcast','favor'))){
            $sign = '';
        }
        $arr['timestamp'] = time();
        $r = Sudo::xn_json_encode($arr);
//        $path = 'Log/'.$_SESSION['room_id'].date('-Ymd').$sign;
        $path = 'Log/'.$_SESSION['room_id'].$sign;
        Sudo::log_save($path,$r);
    }
    private static function getLogLive($offset=0,$size=19,$sign=''){
//        $path = 'Log/'.$_SESSION['room_id'].date('-Ymd').$sign;
        $path = 'Log/'.$_SESSION['room_id'].$sign;
        if (!file_exists($path)) return '';
        $logs = file_get_contents($path);
        $split = '},{';
        $count = mb_substr_count($logs,$split);
        if($sign=='add' || $count<$size-1) {
            $res = '['.$logs.']';
        } else {
//            $length = mb_strlen($logs);
//            $pos = $offset;
//            for($i = 1;$i <= $size;$i++) {
//                if($pos) $pos = mb_strrpos($logs,$split,$pos-$length-1);
//                else $pos = mb_strrpos($logs,$split);
//            }
//            $res = '['.mb_substr($logs,$pos+2).']';
            $res = '['.$logs.']';
        }
        $res = json_decode($res,1);
        return $res;
    }
}
