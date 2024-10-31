<?php  // -*- tab-width: 4; c-basic-offset: 4; coding: utf-8; -*-
defined('ABSPATH') or die("Hello?");

/****************************************************************
 * 管理画面用関数
 ****************************************************************/
/**
 * 管理メニュー
 */
function nn4_location_menu()
{
	add_menu_page('NN4 店舗・施設情報', 'NN4 店舗・施設情報',
				  'edit_files',
				  'nn4-location');
	add_submenu_page('nn4-location',
					 'NN4 店舗・施設情報', '店舗・施設情報',
					 'edit_files',
					 'nn4-location', 'nn4_location_admin_list');
}
?>
