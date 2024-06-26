<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * hook::add('function','function')
 * hook::add('class:function','class.function')
 *
 * hook::run('class.function',param)
 * hook::run('function',param)
 * 
 */

class Hook{
	static private $events = array();
	static public function get($event=false){
		if(!$event){
			return self::$events;
		}else{
			return self::$events[$event];
		}
	}
	static public function apply($action,$args=array()) {
		$result = ActionApply($action,$args);
		if(is_string($action)){
			Hook::trigger($action); // 调用某个事件后触发的动作继续触发;
		}
		return $result;
	}
	
	/**
	 * 绑定事件到方法;$action 为可调用内容;
	 */
	static public function bind($event,$action,$once=false) {
		if(!is_string($event)) return false;
		if(!isset(self::$events[$event])){
			self::$events[$event] = array();
		}
		self::$events[$event][] = array(
			'action' => $action,
			'once' 	 => $once,
			'times'	 => 0
		);
	}
	static public function once($event,$action) {
		self::bind($event,$action,true);
	}
	static public function unbind($event,$action = false) {
		if(!is_string($event)) return false;
		//解绑所有;
		if(!$action){
			self::$events[$event] = array();
			return;
		}
		// 解绑指定事件;
		$eventsMatch = self::$events[$event];
		self::$events[$event] = array();
		if(!is_array($eventsMatch)) return;

		for ($i=0; $i < count($eventsMatch); $i++){
			if($eventsMatch[$i]['action'] == $action) continue;
			self::$events[$event][] = $eventsMatch[$i];
		}
	}
	
	//数据处理;只支持传入一个参数
	static public function filter($event,$param) {
		$events = self::$events;
		if(!is_string($event)) return false;
		if(!isset($events[$event])) return $param;
		if(count(self::$events[$event]) == 0) return $param;

		$result  = $param;
		$actions = self::$events[$event];
		$actionsCount = count($actions);
		for ($i=0; $i < $actionsCount; $i++) {
			$action = $actions[$i];
			if($action['once'] && $action['times'] > 1) continue;
			
			self::$events[$event][$i]['times'] = $action['times'] + 1;
			$temp = ActionApply($action['action'],array($result));
			Hook::trigger($action['action']);
			
			// 类型相同才替换;
			// pr($action['action'],gettype($result),gettype($temp));
			if(gettype($temp) == gettype($result)){$result = $temp;}
		}
		return $result;
	}
	
	static public function trigger($event) {
		static $_logHook = 100;
		if($_logHook === 100){$_logHook = defined("GLOBAL_LOG_HOOK") && GLOBAL_LOG_HOOK;}
		if(!is_string($event)) return false;
		if(!isset(self::$events[$event])) return false;
		if(count(self::$events[$event]) == 0) return false;
		
		$result  = false;
		$actions = self::$events[$event];
		$actionsCount = count($actions);
		$args = func_get_args();
		array_shift($args);
		for ($i=0; $i < $actionsCount; $i++) {
			$action = $actions[$i];
			if($action['once'] && $action['times'] > 1) continue;
			if($_logHook){write_log($event.'==>start: '.$action['action'],'hook-trigger');}

			self::$events[$event][$i]['times'] = $action['times'] + 1;
			$res = ActionApply($action['action'],$args);
			if(is_string($action['action'])){Hook::trigger($action['action']);}
			
			if($_logHook){
				write_log(get_caller_info(),'hook-trigger');
				if($action['times'] == 200){//避免循环调用
					$msg = is_array($action['action']) ? json_encode_force($action['action']):$action['action'];
					write_log("Warning,Too many trigger on:".$event.'==>'.$msg,'warning');
				}
			}
			$result = is_null($res) ? $result:$res;
		}
		return $result;
	}
}
