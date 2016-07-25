CREATE TABLE IF NOT EXISTS `eca_wechat_message` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT '编号',
  `msgid` int(64) unsigned NOT NULL,
  `type` varchar(100) NOT NULL COMMENT '类型',
  `content` text NOT NULL COMMENT '内容',
  `user` varchar(250) NOT NULL COMMENT '用户',
  `time` int(10) unsigned NOT NULL COMMENT '时间',
  `is_reply` tinyint(1) NOT NULL default '0' COMMENT '是否回复',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='微信插件消息数据表' AUTO_INCREMENT=1 ;
