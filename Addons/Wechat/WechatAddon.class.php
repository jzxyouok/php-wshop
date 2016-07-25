<?php

namespace Addons\Wechat;
use Common\Controller\Addon;

/**
 * 微信插件
 * @author huay1
 */

    class WechatAddon extends Addon{
    	public function __construct(){
           parent::__construct();
        }

        public $info = array(
            'name'=>'Wechat',
            'title'=>'微信',
            'description'=>'<a href="#"><i class="icon-info-sign"></i></a>微信插件',
            'status'=>1,
            'author'=>'huay1',
            'version'=>'1.0'
        );

        public $custom_config = 'Wechat_config.html';

        public $admin_list = array(
            'model'=>'WechatMessage',		//要查的表
			'fields'=>'*',			//要查的字段
			'map'=>'',				//查询条件, 如果需要可以再插件类的构造方法里动态重置这个属性
			'order'=>'id desc',		//排序,
			'listKey' => array(
				'user'=>'用户名&标识',
				'msgid'=>'信息ID',
				'type'=>'信息类型',
				'content'=>'信息内容',
				'time'=>'接收时间'
			),
        );
        //public $custom_adminlist = 'Wechat_list.html';
		//缓存变量 - 用于更新配置时清空微信缓存
		public $saveconfig_cache_list = array('WECHATADDONS_MENU','WECHATADDONS_TOKEN','WECHATADDONS_GROUPS');

        public function install(){
			//添加钩子
			$Hooks = M("Hooks");
			$WechatHooksList = array(array(
				'name' => 'WechatAdminLogin',
				'description' => '后台登陆页面钩子，用于微信二维码登陆',
				'type' => 1,
				'update_time' => NOW_TIME,
				'addons' => 'Wechat'
			),array(
				'name' => 'WechatIndexLogin',
				'description' => '前台登陆页面钩子，用于微信二维码登陆',
				'type' => 1,
				'update_time' => NOW_TIME,
				'addons' => 'Wechat'
			));
			$Hooks->addAll($WechatHooksList,array(),true);
			if ( $Hooks->getDbError() ) {
				session('addons_install_error',$Hooks->getError());
				return false;
			}
			//清空缓存
			S('WECHATADDONS_CONF',null);
			if(is_array($this->saveconfig_cache_list)){
				foreach ($this->saveconfig_cache_list as $_v) {
					S($_v,null);
				}
			}else{
				S($this->saveconfig_cache_list,null);
			}
			return true;          
        }

        public function uninstall(){
			$Hooks = M("Hooks");
			$map['name']  = array('in','WechatAdminLogin,WechatIndexLogin');
			$res = $Hooks->where($map)->delete();
			if($res == false){
				session('addons_install_error',$Hooks->getError());
				return false;
			}
			//清空缓存
			S('WECHATADDONS_CONF',null);
			if(is_array($this->saveconfig_cache_list)){
				foreach ($this->saveconfig_cache_list as $_v) {
					S($_v,null);
				}
			}else{
				S($this->saveconfig_cache_list,null);
			}
			return true;
        }

        //实现的WechatAdminLogin钩子方法
        public function WechatAdminLogin($param){
			$this->assign('addons_Wechatconfig', $this->getConfig());
			$this->display(T('Addons://Wechat@Admin/login'));
        }
		//实现的WechatIndexLogin钩子方法
        public function WechatIndexLogin($param){
        	print_r($this->getConfig());
			$this->assign('addons_Wechatconfig', $this->getConfig());
			$this->display(T('Addons://Wechat@Home/login'));
        }
    }