<?php  // -*- tab-width: 4; c-basic-offset: 4; coding: utf-8; -*-
defined('ABSPATH') or die("Hello?");

/****************************************************************
 * 管理画面用関数
 ****************************************************************/
/**
 * 管理メニュー
 */
function nn4_event_menu()
{
	add_menu_page('NN4 イベント情報', 'NN4 イベント情報',
				  'edit_files',
				  'nn4-event');
	add_submenu_page('nn4-event',
					 'NN4 イベント情報', 'イベント情報',
					 'edit_files',
					 'nn4-event', 'nn4_event_admin_list');
	add_submenu_page('nn4-event',
					 'NN4 イベント参加管理', 'イベント参加管理',
					 'edit_files',
					 'nn4-event-attend', 'nn4_event_admin_attend');
}
?>
