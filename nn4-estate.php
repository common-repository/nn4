<?php  // -*- tab-width: 4; c-basic-offset: 4; coding: utf-8; -*-
defined('ABSPATH') or die("Hello?");

// 定数定義
@include_once 'estate-site_config.inc';

if (!defined('NN4_ESATE_DEFAULT_IMAGE_KEY_FORMAT')) {
	define('NN4_ESATE_DEFAULT_IMAGE_KEY_FORMAT', 'image%02d');
}

// 最後のエラーメッセージを格納
$nn4_estate_last_err_msg = array();

// 検索実行時の該当数を格納
$nn4_estate_num_total = 0;

// 物件種目一覧
$nn4_estate_arr_type = array(
	"11" => "一戸建て",
	"21" => "マンション",
	"31" => "土地",
	"41" => "賃貸一戸建て",
	"42" => "賃貸マンション",
	"43" => "賃貸アパート",
	"51" => "賃貸土地",
);

// 不動産業者一覧
$nn4_estate_arr_agent = array(
	"53a7159069d57c6451d88fdd" =>  "秋場不動産 株式会社",
	"53a7160269d57c6451d88fde" =>  "株式会社 埼玉土地",
	"53a7165b69d57c6451d88fdf" =>  "有限会社 鈴建ホーム",
	"53a716c269d57c6451d88fe0" =>  "大賀建設株式会社",
	"53a7173469d57c6451d88fe1" =>  "太平ホーム株式会社",
	"53a7179969d57c6451d88fe2" =>  "株式会社 東武ニューハウス",
	"53a7189869d57c6451d88fe3" =>  "株式会社 東洋不動産",
	"53a7191669d57c6451d88fe4" =>  "株式会社中央住宅 戸建分譲 越谷事業所",
	"53a7197269d57c6451d88fe5" =>  "明光建設 株式会社",
);

// 取引態様一覧
$nn4_estate_arr_agent_position = array(
	"53f199f4be010502770d5565" => "売主",
	"53f19b44be010502770d5566" => "代理",
	"53f19b44be010502770d5567" => "専属専任媒介",
	"53f19b44be010502770d5568" => "専任媒介",
	"53f19b44be010502770d5569" => "一般媒介",
);

// 土地の所有権一覧
$nn4_estate_arr_land_ownership = array(
	"53f401e9f149dc70e2fe5275" => '所有権',
	"53f401e9f149dc70e2fe5276" => '普通借地権 (地上権)',
	"53f401e9f149dc70e2fe5277" => '普通借地権 (賃貸権)',
	"53f401e9f149dc70e2fe5278" => '一般定期借地権',
	"53f401e9f149dc70e2fe5279" => '建物譲渡特約付借地権',
	"53f401e9f149dc70e2fe527a" => '事業用借地権',
);

/****************************************************************
 * 初期化処理
 ****************************************************************/
function nn4_estate_init()
{
	register_post_type('nn4_estate',
					   array('labels' => array('name' => 'NN4 不動産情報'),
							 'public' => TRUE,
							 'has_archive' => TRUE,
							 'show_ui' => FALSE));
}

/****************************************************************
 * 管理画面用関数
 ****************************************************************/
/**
 * 管理メニュー
 */
function nn4_estate_menu()
{
	add_menu_page('NN4 不動産一覧', 'NN4 不動産情報',
				  'edit_posts',
				  'nn4-estate');
	add_submenu_page('nn4-estate',
					 'NN4 不動産一覧', '不動産一覧',
					 'edit_posts',
					 'nn4-estate', 'nn4_estate_admin_list');
	$hook_add_page = add_submenu_page('nn4-estate',
									  'NN4 不動産情報 新規追加', '新規追加',
									  'edit_posts',
									  'nn4-estate-add',
									  'nn4_estate_admin_add');
	add_submenu_page('nn4-estate',
					 'NN4 操作ログダウンロード', '操作ログダウンロード',
					 'export',
					 'nn4-estate-actionlog', 'nn4_estate_admin_actionlog');
	add_submenu_page('nn4-estate',
					 'NN4 サーバーとの同期', 'サーバー同期',
					 'edit_posts',
					 'nn4-estate-sync', 'nn4_estate_admin_sync');
	add_submenu_page('nn4-estate',
					 'NN4 不動産情報設定', '設定',
					 'edit_themes',
					 'nn4-estate-settings', 'nn4_estate_admin_settings');

	add_action('admin_print_scripts-' . $hook_add_page,
			   'nn4_estate_admin_scripts');
}

/**
 * JavaScript ファイルのロード
 */
function nn4_estate_admin_scripts()
{
	$apid = get_option('nn4_apid');

	// メディアアップローダー用スクリプトをロード
	wp_enqueue_media();
	wp_enqueue_script('sprintf',
					  plugins_url('sprintf.js', __FILE__),
					  array(),
					  filemtime(dirname(__FILE__) . '/sprintf.js'),
					  FALSE);
	wp_enqueue_script('my-media-uploader',
					  plugins_url('media-uploader.php?apid=' . $apid, __FILE__),
					  array('jquery', 'sprintf'),
					  filemtime(dirname(__FILE__) . '/media-uploader.php'),
					  FALSE);
}

/**
 * WordPress データベースと NN4 サーバーのデータを同期
 */
function nn4_estate_sync($server_data = NULL) {
	global $nn4_estate_num_total;

	if (is_null($server_data)) {
		$flg_all = TRUE;
		$server_data = array();

		// 全データを取得
		$arr_cond = array('offset' => 0,
						  'limit' => 100);
		do {
			$server_data = array_merge($server_data,
									   nn4_estate_search($arr_cond));
			$arr_cond['offset'] += 100;
		} while ($nn4_estate_num_total
				 > $arr_cond['offset'] + $arr_cond['limit']);
	}

	// WordPress データベースに存在するデータ一覧取得
	$wp_data = array();
	$arr_cond = array('post_type' => 'nn4_estate',
					  'offset' => 0,
					  'numberposts' => 100);
	do {
		$wp_data = array_merge($wp_data, $tmp_data = get_posts($arr_cond));
		$arr_cond['offset'] += 100;
	} while (count($tmp_data) >= $arr_cond['numberposts']);
	$wp_id_data = array();
	foreach ($wp_data as $obj_post) {
		$wp_id_data[$obj_post->ID] = $obj_post;
	}

	// データを追加・更新
	$apk = nn4_auth();
	foreach ($server_data as $s_data) {
		$flg_server_post_id = isset($s_data['ap_aux']['post_id']);
		if ($flg_server_post_id) {
			if (in_array($s_data['ap_aux']['post_id'],
						 array_keys($wp_id_data))) {
				// カスタム投稿タイプ nn4_estate で既存なのでサーバーの記事 ID を使用
				$post_ID = $s_data['ap_aux']['post_id'];
			} else {
				$flg_server_post_id = FALSE;
			}
		}

		// WordPress の記事追加・更新
		($post_title = $s_data['ch_serial'])
			|| ($post_title = $post_ID)
			|| ($post_title = $s_data['name']);
		if ($flg_server_post_id) {
			edit_post(array('post_ID' => $post_ID,
							'post_type' => 'nn4_estate',
							'post_status' => 'publish',
							'post_title' => $post_title));
		} else {
			$post_ID = wp_insert_post(array('post_type' => 'nn4_estate',
											'post_status' => 'publish',
											'post_title' => $post_title));
		}
		delete_metadata('post', $post_ID, 'nn4_estate_id');
		add_metadata('post', $post_ID, 'nn4_estate_id', $s_data['id']);

		// WordPress 側の削除対象から除外
		unset($wp_id_data[$post_ID]);

		if (!$flg_server_post_id) {
			// サーバーの記事ID更新
			$ap_aux = $s_data['ap_aux'];
			$ap_aux['post_id'] = $post_ID;
			nn4_post('estate/update',
					 array('apk' => $apk,
						   'id' => $s_data['id'],
						   'ap_aux' => json_encode($ap_aux)));
		}
	}

	if ($flg_all) {
		// 存在しないデータを削除
		foreach (array_keys($wp_id_data) as $p_id) {
			wp_delete_post($p_id, TRUE);
		}
	}
}

/**
 * 一覧
 */
function nn4_estate_admin_list()
{
	global $nn4_arr_station;
	global $nn4_estate_list;
	global $nn4_estate_num_total;
	global $nn4_estate_arr_type;
	global $nn4_estate_last_err_msg;

	// 入力パラメータ 絞り込み条件 (種目)
	if (isset($_REQUEST['type']) && $_REQUEST['type'] != '') {
		$q_type = stripslashes($_REQUEST['type']);
	} else {
		$q_type = '';
	}

	// 入力パラメータ 絞り込み条件 (最寄り駅)
	if (isset($_REQUEST['station']) && $_REQUEST['station'] != '') {
		$q_station_id = stripslashes($_REQUEST['station']);
	} else {
		$q_station_id = '';
	}

	// 入力パラメータ ソート順
	if (isset($_REQUEST['sort'])
		&& preg_match('/^[\-\+](?:channel|name|type|station|address)$/',
					  $_REQUEST['sort'],
					  $matches)) {
		$sort = stripslashes($_REQUEST['sort']);
	} else {
		$sort = '+name';
	}

	// 入力パラメータ ページ (1, 2, ...)
	if (isset($_REQUEST['paged']) && is_numeric($_REQUEST['paged'])
		&& $_REQUEST['paged'] > 0) {
		$page = (int)$_REQUEST['paged'];
	} else {
		$page = 1;
	}

	// 入力パラメータ 取得数
	if (isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit'])
		&& $_REQUEST['limit'] > 0 && $_REQUEST['limit'] <= 100) {
		$limit = (int)$_REQUEST['limit'];
	} else {
		$limit = 20;
	}

	// オフセット計算
	$offset = $limit * ($page - 1);

	// 検索
	$arr_cond = array('sort' => $sort,
					  'offset' => $offset,
					  'limit' => $limit);
	if ($q_type != '') {
		$arr_cond['q_type[0]'] = $q_type;
	}
	if ($q_station_id != '') {
		$arr_cond['q_station_id[0]'] = $q_station_id;
	}
	$nn4_estate_list = nn4_estate_search($arr_cond);
	nn4_estate_sync($nn4_estate_list);

	require 'nn4-estate-admin-list.tmpl';
}

/**
 * 新規追加・編集・削除
 */
function nn4_estate_admin_add()
{
	global $nn4_estate_last_err_msg;
	global $nn4_arr_pref;
	global $nn4_arr_station;
	global $nn4_estate_arr_type;
	global $nn4_estate_arr_agent;
	global $nn4_estate_arr_agent_position;
	global $nn4_estate_arr_land_ownership;

	$apid = get_option('nn4_apid');

	$nn4_estate_last_err_msg = array();

	// モード決定
	//   typesel:     種目選択 (デフォルト)
	//   input:       新規追加入力
	//   confirm:     新規追加確認
	//   exec:        新規追加実行
	//   editinput:   編集入力
	//   editconfirm: 新規追加確認
	//   editexec:    新規追加実行
	//   delete:      削除実行
	$mode = 'typesel';
	if (isset($_REQUEST['mode'])
		&& ($_REQUEST['mode'] == 'input' || $_REQUEST['mode'] == 'confirm'
			|| $_REQUEST['mode'] == 'exec'
			|| $_REQUEST['mode'] == 'editinput'
			|| $_REQUEST['mode'] == 'editconfirm'
			|| $_REQUEST['mode'] == 'editexec'
			|| $_REQUEST['mode'] == 'delete')) {
		$mode = stripslashes($_REQUEST['mode']);
	}

	// 入力パラメータ検証
	if ($mode == 'delete') {
		// 投稿ID
		if (nn4_param_chk_required('entry_post_id', '不動産情報ID',
								   $nn4_estate_last_err_msg)) {
			$entry_post_id = stripslashes($_REQUEST['entry_post_id']);
		} else {
			require 'nn4-estate-admin-add-error.tmpl';
			exit;
		}

		// WordPress 記事から NN4 サーバーでの ID を取得
		$arr_entry_id = array();
		foreach (explode(',', $entry_post_id) as $post_id) {
			$arr_nn4_estate_id
				= get_metadata('post', $post_id, 'nn4_estate_id');
			$entry_id = $arr_nn4_estate_id[0];
			if (!$entry_id) {
				$nn4_estate_last_err_msg[] = '不動産情報IDを取得できません。';
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
			$arr_entry_id[] = $entry_id;
		}
		$entry_id = implode(',', $arr_entry_id);
	} else if ($mode != 'typesel') {
		// チェックトークン
		if ($mode != 'editinput') {
			if (!nn4_chktoken_check('chktoken')) {
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('画面遷移が正しくありません');
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
		}

		// 投稿ID
		if (preg_match('/^edit/', $mode)) {
			if (nn4_param_chk_required('entry_post_id', '投稿ID',
									   $nn4_estate_last_err_msg)) {
				$entry_post_id = stripslashes($_REQUEST['entry_post_id']);
			} else {
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}

			// WordPress 記事から NN4 サーバーでの ID を取得
			$arr_nn4_estate_id
				= get_metadata('post', $entry_post_id, 'nn4_estate_id');
			$entry_id = $arr_nn4_estate_id[0];
			if (!$entry_id) {
				$nn4_estate_last_err_msg[]
					= '不動産情報IDを取得できません。';
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
		}

		// ID
		if ($mode == 'editinput') {
			// NN4サーバーから不動産情報取得
			$apk = nn4_auth();
			if (!$apk) {
				$nn4_estate_last_err_msg[] = $nn4_last_err_msg;
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
			$ret = nn4_post('estate/get',
							array('apk' => $apk,
								  'id' => $entry_id));
			if (!$ret) {
				$nn4_estate_last_err_msg[] = nn4_errmsg('Get API failed.');
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
			$r = json_decode($ret, TRUE);
			if (!$r) {
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('API returned invalid data.');
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
			if (!$r['stat']) {
				$nn4_estate_last_err_msg[] = nn4_errmsg('Get failed', $r);
				require 'nn4-estate-admin-add-error.tmpl';
				exit;
			}
			$nn4_estate_data = $r['data'];
		}

		// 種目
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_type'])) {
				$_REQUEST['entry_type'] = $nn4_estate_data['type'];
			}
		}
		if (nn4_param_chk_required('entry_type', '種目',
								   $nn4_estate_last_err_msg)) {
			if (nn4_param_chk_match_collection('entry_type', '種目',
											   array_keys($nn4_estate_arr_type),
											   $nn4_estate_last_err_msg)) {
				$entry_type = stripslashes($_REQUEST['entry_type']);
			} else {
				$entry_type = '';
				$mode = 'typesel';
			}
		} else {
			$entry_type = '';
			$mode = 'typesel';
		}

		// 物件名
		if ($mode == 'input') {
			if (isset($_REQUEST['entry_name'])) {
				$entry_name = stripslashes($_REQUEST['entry_name']);
			} else {
				$entry_name = '';
			}
		} else {
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_name'])) {
					$_REQUEST['entry_name'] = $nn4_estate_data['name'];
				}
			}

			if (nn4_param_chk_required('entry_name', '物件名',
									   $nn4_estate_last_err_msg)) {
				$entry_name = stripslashes($_REQUEST['entry_name']);
			} else {
				$entry_name = '';
				$mode = 'input';
			}
		}

		// チャネル固有の物件番号
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_serial'])) {
				$_REQUEST['entry_serial'] = $nn4_estate_data['ch_serial'];
			}
		}
		if (isset($_REQUEST['entry_serial'])) {
			$entry_serial = stripslashes($_REQUEST['entry_serial']);
		} else {
			$entry_serial = '';
		}

		// AP固有の追加情報
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_aux'])) {
				$_REQUEST['entry_aux'] = $nn4_estate_data['ap_aux'];
			}
		}
		if (isset($_REQUEST['entry_aux'])) {
			$entry_aux = $_REQUEST['entry_aux'];
		} else {
			$entry_aux = '';
		}

		// 所在地・都道府県コード
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_pref_code'])) {
				$_REQUEST['entry_pref_code'] = $nn4_estate_data['pref_code'];
			}
		}
		if (isset($_REQUEST['entry_pref_code'])
			&& $_REQUEST['entry_pref_code'] != '') {
			if (nn4_param_chk_match_collection('entry_pref_code',
											   '所在地・都道府県',
											   array_keys($nn4_arr_pref),
											   $nn4_estate_last_err_msg)) {
				$entry_pref_code
					= stripslashes($_REQUEST['entry_pref_code']);
			} else {
				$entry_pref_code = '';
				$mode = 'input';
			}
		} else {
			$entry_pref_code = '';
		}

		// 所在地・住所1
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_address1'])) {
				$_REQUEST['entry_address1'] = $nn4_estate_data['address1'];
			}
		}
		if (isset($_REQUEST['entry_address1'])) {
			$entry_address1 = stripslashes($_REQUEST['entry_address1']);
		} else {
			$entry_address1 = '';
		}

		// 所在地・住所2
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_address2'])) {
				$_REQUEST['entry_address2'] = $nn4_estate_data['address2'];
			}
		}
		if (isset($_REQUEST['entry_address2'])) {
			$entry_address2 = stripslashes($_REQUEST['entry_address2']);
		} else {
			$entry_address2 = '';
		}

		// 所在地・情報
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_address_info'])) {
				$_REQUEST['entry_address_info']
					= $nn4_estate_data['address_info'];
			}
		}
		if (isset($_REQUEST['entry_address_info'])) {
			$entry_address_info = $_REQUEST['entry_address_info'];
			$entry_address_info_decoded
				= json_decode($entry_address_info, TRUE);
		} else {
			$entry_address_info = '[]';
			$entry_address_info_decoded = array();
		}

		// 所在地 (補足)
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_address_memo'])) {
				$_REQUEST['entry_address_memo']
					= $nn4_estate_data['address_memo'];
			}
		}
		if (isset($_REQUEST['entry_address_memo'])) {
			$entry_address_memo
				= stripslashes($_REQUEST['entry_address_memo']);
		} else {
			$entry_address_memo = '';
		}

		// 最寄り駅
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_transport'])) {
				$_REQUEST['entry_transport']
					= json_encode($nn4_estate_data['transport']);
			}
		}
		if (isset($_REQUEST['entry_transport'])
			&& $_REQUEST['entry_transport'] != '') {
			$entry_transport = stripslashes($_REQUEST['entry_transport']);
			$entry_transport_decoded = json_decode($entry_transport, TRUE);
			foreach ($entry_transport_decoded as $transport) {
				if (($transport['railway_station_id'] == ''
					 && $transport['minutes'] != '')
					|| ($transport['railway_station_id'] != ''
						&& $transport['minutes'] == '')) {
					$nn4_estate_last_err_msg[]
						= '最寄り駅と徒歩分数の片方のみ指定されている項目があります。';
					$entry_transport = '[]';
					$entry_transport_decoded = array();
					$mode = 'input';
					break;
				} else if ($transport['minutes'] != ''
						   && !preg_match('/^\d+$/', $transport['minutes'])) {
					$nn4_estate_last_err_msg[]
						= '徒歩分数が正しくない項目があります。';
					$entry_transport = '[]';
					$entry_transport_decoded = array();
					$mode = 'input';
					break;
				}
			}
		} else {
			$entry_transport = '[]';
			$entry_transport_decoded = array();
		}

		// 現況
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_current_state'])) {
				$_REQUEST['entry_current_state']
					= $nn4_estate_data['current_state'];
			}
		}
		if (isset($_REQUEST['entry_current_state'])) {
			$entry_current_state
				= stripslashes($_REQUEST['entry_current_state']);
		} else {
			$entry_current_state = '';
		}

		// 設備
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_equipments'])) {
				$_REQUEST['entry_equipments'] = $nn4_estate_data['equipments'];
			}
		}
		if (isset($_REQUEST['entry_equipments'])) {
			$entry_equipments = stripslashes($_REQUEST['entry_equipments']);
		} else {
			$entry_equipments = '';
		}

		// 特記事項
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_special_instr'])) {
				$_REQUEST['entry_special_instr']
					= $nn4_estate_data['special_instr'];
			}
		}
		if (isset($_REQUEST['entry_special_instr'])) {
			$entry_special_instr
				= stripslashes($_REQUEST['entry_special_instr']);
		} else {
			$entry_special_instr = '';
		}

		// 備考
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_note'])) {
				$_REQUEST['entry_note'] = $nn4_estate_data['note'];
			}
		}
		if (isset($_REQUEST['entry_note'])) {
			$entry_note = stripslashes($_REQUEST['entry_note']);
		} else {
			$entry_note = '';
		}

		// 取扱業者
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_agent'])) {
				$_REQUEST['entry_agent']
					= json_encode($nn4_estate_data['agent']);
			}
		}
		if (isset($_REQUEST['entry_agent']) && $_REQUEST['entry_agent'] != '') {
			$entry_agent = stripslashes($_REQUEST['entry_agent']);
			$entry_agent_decoded = json_decode($entry_agent, TRUE);
			foreach ($entry_agent_decoded as $agent) {
				if (($agent['id'] == '' && $agent['position_id'] != '')
					|| ($agent['id'] != '' && $agent['position_id'] == '')) {
					$nn4_estate_last_err_msg[]
						= '取扱業者と取引態様の片方のみ指定されている項目があります。';
					$entry_agent = '[]';
					$entry_agent_decoded = array();
					$mode = 'input';
					break;
				}
			}
		} else {
			$entry_agent = '[]';
			$entry_agent_decoded = array();
		}

		// 画像
		if ($mode == 'editinput') {
			if (!isset($_REQUEST['entry_image'])) {
				$_REQUEST['entry_image']
					= json_encode($nn4_estate_data['image']);
			}
		}
		if (isset($_REQUEST['entry_image'])
			&& $_REQUEST['entry_image'] != '') {
			$entry_image = stripslashes($_REQUEST['entry_image']);
			$entry_image_decoded = json_decode($entry_image, TRUE);
		} else {
			$entry_image = '[]';
			$entry_image_decoded = array();
		}

		if (preg_match('/^1/', $entry_type)) {
			/* 一戸建て固有のパラメータ */
			// 価格
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price'])) {
					$_REQUEST['entry_price'] = $nn4_estate_data['price'];
				}
			}
			if (isset($_REQUEST['entry_price'])
				&& $_REQUEST['entry_price'] != '') {
				if (nn4_param_chk_valid_string('entry_price',
											   '価格',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_price = stripslashes($_REQUEST['entry_price']);
				} else {
					$entry_price = '';
					$mode = 'input';
				}
			} else {
				$entry_price = '';
			}

			// 価格 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_memo'])) {
					$_REQUEST['entry_price_memo']
						= $nn4_estate_data['price_memo'];
				}
			}
			if (isset($_REQUEST['entry_price_memo'])) {
				$entry_price_memo
					= stripslashes($_REQUEST['entry_price_memo']);
			} else {
				$entry_price_memo = '';
			}

			// 価格(上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_max'])) {
					$_REQUEST['entry_price_max']
						= $nn4_estate_data['price_max'];
				}
			}
			if (isset($_REQUEST['entry_price_max'])
				&& $_REQUEST['entry_price_max'] != '') {
				if (nn4_param_chk_valid_string('entry_price_max',
											   '価格 (上限)',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_price != ''
						&& !nn4_param_chk_numeric_min(
							'entry_price_max',
							'価格 (上限)',
							$entry_price,
							$nn4_estate_last_err_msg
						)) {
						$entry_price_max = '';
						$mode = 'input';
					} else {
						$entry_price_max
							= stripslashes($_REQUEST['entry_price_max']);
					}
				} else {
					$entry_price_max = '';
					$mode = 'input';
				}
			} else {
				$entry_price_max = '';
			}

			// 新築フラグ
			if ($mode == 'input') {
				if (isset($_REQUEST['entry_flg_new'])) {
					$entry_flg_new = stripslashes($_REQUEST['entry_flg_new']);
				} else {
					$entry_flg_new = '';
				}
			} else {
				if ($mode == 'editinput') {
					if (!isset($_REQUEST['entry_flg_new'])) {
						$_REQUEST['entry_flg_new']
							= $nn4_estate_data['flg_new'];
					}
				}
				if (nn4_param_chk_required('entry_flg_new', '新築/中古',
										   $nn4_estate_last_err_msg)) {
					if (nn4_param_chk_match_collection(
							'entry_flg_new',
							'新築/中古',
							array('0', '1'),
							$nn4_estate_last_err_msg
						)) {
						$entry_flg_new
							= stripslashes($_REQUEST['entry_flg_new']);
					} else {
						$entry_flg_new = '';
					}
				} else {
					$entry_flg_new = '';
				}
			}

			// 築年月
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_built_on'])) {
					$_REQUEST['entry_built_on']
						= $nn4_estate_data['built_on'];
				}
			}
			if (isset($_REQUEST['entry_built_on'])
				&& $_REQUEST['entry_built_on'] != '') {
				if (!preg_match('/^\d{4}(?:\-\d{2})?$/',
							   $_REQUEST['entry_built_on'])) {
					$nn4_estate_last_err_msg[]
						= '築年月日が正しくありません (YYYY または YYYY-MM)';
					$entry_built_on = '';
					$mode = 'input';
				} else {
					$entry_built_on
						= stripslashes($_REQUEST['entry_built_on']);
				}
			} else {
				$entry_built_on = '';
			}

			// 間取り
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_room_layout'])) {
					$_REQUEST['entry_room_layout']
						= $nn4_estate_data['room_layout'];
				}
			}
			if (isset($_REQUEST['entry_room_layout'])) {
				$entry_room_layout
					= stripslashes($_REQUEST['entry_room_layout']);
			} else {
				$entry_room_layout = '';
			}

			// 建物構造
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_structure'])) {
					$_REQUEST['entry_bldg_structure']
						= $nn4_estate_data['bldg_structure'];
				}
			}
			if (isset($_REQUEST['entry_bldg_structure'])) {
				$entry_bldg_structure
					= stripslashes($_REQUEST['entry_bldg_structure']);
			} else {
				$entry_bldg_structure = '';
			}

			// 階建
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_num_stairs'])) {
					$_REQUEST['entry_num_stairs']
						= $nn4_estate_data['num_stairs'];
				}
			}
			if (isset($_REQUEST['entry_num_stairs'])
				&& $_REQUEST['entry_num_stairs'] != '') {
				if (nn4_param_chk_valid_string('entry_num_stairs',
											   '階建',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_num_stairs
						= stripslashes($_REQUEST['entry_num_stairs']);
				} else {
					$entry_num_stairs = '';
					$mode = 'input';
				}
			} else {
				$entry_num_stairs = '';
			}

			// 階建 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_num_stairs_memo'])) {
					$_REQUEST['entry_num_stairs_memo']
						= $nn4_estate_data['num_stairs_memo'];
				}
			}
			if (isset($_REQUEST['entry_num_stairs_memo'])) {
				$entry_num_stairs_memo
					= stripslashes($_REQUEST['entry_num_stairs_memo']);
			} else {
				$entry_num_stairs_memo = '';
			}

			// 駐車スペース
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_parking_space'])) {
					$_REQUEST['entry_parking_space']
						= $nn4_estate_data['parking_space'];
				}
			}
			if (isset($_REQUEST['entry_parking_space'])) {
				$entry_parking_space
					= stripslashes($_REQUEST['entry_parking_space']);
			} else {
				$entry_parking_space = '';
			}

			// 建物面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_dim'])) {
					$_REQUEST['entry_bldg_dim']
						= $nn4_estate_data['bldg_dim'];
				}
			}
			if (isset($_REQUEST['entry_bldg_dim'])
				&& $_REQUEST['entry_bldg_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_bldg_dim',
											   '建物面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_bldg_dim
						= stripslashes($_REQUEST['entry_bldg_dim']);
				} else {
					$entry_bldg_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_bldg_dim = '';
			}

			// 建物面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_dim_memo'])) {
					$_REQUEST['entry_bldg_dim_memo']
						= $nn4_estate_data['bldg_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_bldg_dim_memo'])) {
				$entry_bldg_dim_memo
					= stripslashes($_REQUEST['entry_bldg_dim_memo']);
			} else {
				$entry_bldg_dim_memo = '';
			}

			// 建物面積 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_dim_max'])) {
					$_REQUEST['entry_bldg_dim_max']
						= $nn4_estate_data['bldg_dim_max'];
				}
			}
			if (isset($_REQUEST['entry_bldg_dim_max'])
				&& $_REQUEST['entry_bldg_dim_max'] != '') {
				if (nn4_param_chk_valid_string('entry_bldg_dim_max',
											   '建物面積 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_bldg_dim != ''
						&& !nn4_param_chk_numeric_min(
							'entry_bldg_dim_max',
							'建物面積 (上限)',
							$entry_bldg_dim,
							$nn4_estate_last_err_msg
						)) {
						$entry_bldg_dim_max = '';
						$mode = 'input';
					} else {
						$entry_bldg_dim_max
							= stripslashes($_REQUEST['entry_bldg_dim_max']);
					}
				} else {
					$entry_bldg_dim_max = '';
					$mode = 'input';
				}
			} else {
				$entry_bldg_dim_max = '';
			}

			// 土地面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim'])) {
					$_REQUEST['entry_land_dim']
						= $nn4_estate_data['land_dim'];
				}
			}
			if (isset($_REQUEST['entry_land_dim'])
				&& $_REQUEST['entry_land_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim',
											   '土地面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_land_dim
						= stripslashes($_REQUEST['entry_land_dim']);
				} else {
					$entry_land_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim = '';
			}

			// 土地面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_memo'])) {
					$_REQUEST['entry_land_dim_memo']
						= $nn4_estate_data['land_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_memo'])) {
				$entry_land_dim_memo
					= stripslashes($_REQUEST['entry_land_dim_memo']);
			} else {
				$entry_land_dim_memo = '';
			}

			// 土地面積 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_max'])) {
					$_REQUEST['entry_land_dim_max']
						= $nn4_estate_data['land_dim_max'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_max'])
				&& $_REQUEST['entry_land_dim_max'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim_max',
											   '土地面積 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_land_dim != ''
						&& !nn4_param_chk_numeric_min(
							'entry_land_dim_max',
							'土地面積 (上限)',
							$entry_land_dim,
							$nn4_estate_last_err_msg
						)) {
						$entry_land_dim_max = '';
						$mode = 'input';
					} else {
						$entry_land_dim_max
							= stripslashes($_REQUEST['entry_land_dim_max']);
					}
				} else {
					$entry_land_dim_max = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim_max = '';
			}

			// 坪数
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong'])) {
					$_REQUEST['entry_pyeong']
						= $nn4_estate_data['pyeong'];
				}
			}
			if (isset($_REQUEST['entry_pyeong'])
				&& $_REQUEST['entry_pyeong'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong',
											   '坪数',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_pyeong
						= stripslashes($_REQUEST['entry_pyeong']);
				} else {
					$entry_pyeong = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong = '';
			}

			// 坪数 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_memo'])) {
					$_REQUEST['entry_pyeong_memo']
						= $nn4_estate_data['pyeong_memo'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_memo'])) {
				$entry_pyeong_memo
					= stripslashes($_REQUEST['entry_pyeong_memo']);
			} else {
				$entry_pyeong_memo = '';
			}

			// 坪数 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_max'])) {
					$_REQUEST['entry_pyeong_max']
						= $nn4_estate_data['pyeong_max'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_max'])
				&& $_REQUEST['entry_pyeong_max'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong_max',
											   '坪数 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_pyeong != ''
						&& !nn4_param_chk_numeric_min(
							'entry_pyeong_max',
							'坪数 (上限)',
							$entry_pyeong,
							$nn4_estate_last_err_msg
						)) {
						$entry_pyeong_max = '';
						$mode = 'input';
					} else {
						$entry_pyeong_max
							= stripslashes($_REQUEST['entry_pyeong_max']);
					}
				} else {
					$entry_pyeong_max = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong_max = '';
			}

			// 私道負担面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim'])) {
					$_REQUEST['entry_driveway_dim']
						= $nn4_estate_data['driveway_dim'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim'])
				&& $_REQUEST['entry_driveway_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_driveway_dim',
											   '私道負担面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_driveway_dim
						= stripslashes($_REQUEST['entry_driveway_dim']);
				} else {
					$entry_driveway_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_driveway_dim = '';
			}

			// 私道負担面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim_memo'])) {
					$_REQUEST['entry_driveway_dim_memo']
						= $nn4_estate_data['driveway_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim_memo'])) {
				$entry_driveway_dim_memo
					= stripslashes($_REQUEST['entry_driveway_dim_memo']);
			} else {
				$entry_driveway_dim_memo = '';
			}

			// セットバック
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_setback'])) {
					$_REQUEST['entry_setback']
						= $nn4_estate_data['setback'];
				}
			}
			if (isset($_REQUEST['entry_setback'])) {
				$entry_setback
					= stripslashes($_REQUEST['entry_setback']);
			} else {
				$entry_setback = '';
			}

			// 建ぺい率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage'])) {
					$_REQUEST['entry_bldg_coverage']
						= $nn4_estate_data['bldg_coverage'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage'])
				&& $_REQUEST['entry_bldg_coverage'] != '') {
				if (nn4_param_chk_valid_string('entry_bldg_coverage',
											   '建ぺい率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_bldg_coverage
						= stripslashes($_REQUEST['entry_bldg_coverage']);
				} else {
					$entry_bldg_coverage = '';
					$mode = 'input';
				}
			} else {
				$entry_bldg_coverage = '';
			}

			// 建ぺい率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage_memo'])) {
					$_REQUEST['entry_bldg_coverage_memo']
						= $nn4_estate_data['bldg_coverage_memo'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage_memo'])) {
				$entry_bldg_coverage_memo
					= stripslashes($_REQUEST['entry_bldg_coverage_memo']);
			} else {
				$entry_bldg_coverage_memo = '';
			}

			// 容積率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio'])) {
					$_REQUEST['entry_plot_ratio']
						= $nn4_estate_data['plot_ratio'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio'])
				&& $_REQUEST['entry_plot_ratio'] != '') {
				if (nn4_param_chk_valid_string('entry_plot_ratio',
											   '容積率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_plot_ratio
						= stripslashes($_REQUEST['entry_plot_ratio']);
				} else {
					$entry_plot_ratio = '';
					$mode = 'input';
				}
			} else {
				$entry_plot_ratio = '';
			}

			// 容積率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio_memo'])) {
					$_REQUEST['entry_plot_ratio_memo']
						= $nn4_estate_data['plot_ratio_memo'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio_memo'])) {
				$entry_plot_ratio_memo
					= stripslashes($_REQUEST['entry_plot_ratio_memo']);
			} else {
				$entry_plot_ratio_memo = '';
			}

			// 土地権利
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_ownership_id'])) {
					$_REQUEST['entry_land_ownership_id']
						= $nn4_estate_data['land_ownership_id'];
				}
			}
			if (isset($_REQUEST['entry_land_ownership_id'])
				&& $_REQUEST['entry_land_ownership_id'] != '') {
				if (nn4_param_chk_match_collection(
						'entry_land_ownership_id',
						'土地権利',
						array_keys($nn4_estate_arr_land_ownership),
						$nn4_estate_last_err_msg)) {
					$entry_land_ownership_id
						= stripslashes(
							$_REQUEST['entry_land_ownership_id']
						);
				} else {
					$entry_land_ownership_id = '';
					$mode = 'input';
				}
			} else {
				$entry_land_ownership_id = '';
			}

			// 借地権 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_lease_memo'])) {
					$_REQUEST['entry_land_lease_memo']
						= $nn4_estate_data['land_lease_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_lease_memo'])) {
				$entry_land_lease_memo
					= stripslashes($_REQUEST['entry_land_lease_memo']);
			} else {
				$entry_land_lease_memo = '';
			}

			// 地目
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_category'])) {
					$_REQUEST['entry_land_category']
						= $nn4_estate_data['land_category'];
				}
			}
			if (isset($_REQUEST['entry_land_category'])) {
				$entry_land_category
					= stripslashes($_REQUEST['entry_land_category']);
			} else {
				$entry_land_category = '';
			}

			// 用途地域
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_zoning'])) {
					$_REQUEST['entry_zoning']
						= $nn4_estate_data['zoning'];
				}
			}
			if (isset($_REQUEST['entry_zoning'])) {
				$entry_zoning
					= stripslashes($_REQUEST['entry_zoning']);
			} else {
				$entry_zoning = '';
			}

			// 都市計画
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_area_control'])) {
					$_REQUEST['entry_area_control']
						= $nn4_estate_data['area_control'];
				}
			}
			if (isset($_REQUEST['entry_area_control'])) {
				$entry_area_control
					= stripslashes($_REQUEST['entry_area_control']);
			} else {
				$entry_area_control = '';
			}

			// 法令上の制限
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_restriction'])) {
					$_REQUEST['entry_restriction']
						= $nn4_estate_data['restriction'];
				}
			}
			if (isset($_REQUEST['entry_restriction'])) {
				$entry_restriction
					= stripslashes($_REQUEST['entry_restriction']);
			} else {
				$entry_restriction = '';
			}

			// 国土法届出
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_mlit_nmbr'])) {
					$_REQUEST['entry_mlit_nmbr']
						= $nn4_estate_data['mlit_nmbr'];
				}
			}
			if (isset($_REQUEST['entry_mlit_nmbr'])) {
				$entry_mlit_nmbr
					= stripslashes($_REQUEST['entry_mlit_nmbr']);
			} else {
				$entry_mlit_nmbr = '';
			}

			// 接道状況
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_abutting_road'])) {
					$_REQUEST['entry_abutting_road']
						= $nn4_estate_data['abutting_road'];
				}
			}
			if (isset($_REQUEST['entry_abutting_road'])) {
				$entry_abutting_road
					= stripslashes($_REQUEST['entry_abutting_road']);
			} else {
				$entry_abutting_road = '';
			}

			// 地勢
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_landscape'])) {
					$_REQUEST['entry_landscape']
						= $nn4_estate_data['landscape'];
				}
			}
			if (isset($_REQUEST['entry_landscape'])) {
				$entry_landscape
					= stripslashes($_REQUEST['entry_landscape']);
			} else {
				$entry_landscape = '';
			}

			// 引渡し
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery'])) {
					$_REQUEST['entry_delivery']
						= $nn4_estate_data['delivery'];
				}
			}
			if (isset($_REQUEST['entry_delivery'])) {
				$entry_delivery
					= stripslashes($_REQUEST['entry_delivery']);
			} else {
				$entry_delivery = '';
			}

			// 引渡し条件
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery_cond'])) {
					$_REQUEST['entry_delivery_cond']
						= $nn4_estate_data['delivery_cond'];
				}
			}
			if (isset($_REQUEST['entry_delivery_cond'])) {
				$entry_delivery_cond
					= stripslashes($_REQUEST['entry_delivery_cond']);
			} else {
				$entry_delivery_cond = '';
			}

			// 入居可能日
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_available_from'])) {
					$_REQUEST['entry_available_from']
						= $nn4_estate_data['available_from'];
				}
			}
			if (isset($_REQUEST['entry_available_from'])) {
				$entry_available_from
					= stripslashes($_REQUEST['entry_available_from']);
			} else {
				$entry_available_from = '';
			}

			// 建築確認番号
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_check_nmbr'])) {
					$_REQUEST['entry_bldg_check_nmbr']
						= $nn4_estate_data['bldg_check_nmbr'];
				}
			}
			if (isset($_REQUEST['entry_bldg_check_nmbr'])) {
				$entry_bldg_check_nmbr
					= stripslashes($_REQUEST['entry_bldg_check_nmbr']);
			} else {
				$entry_bldg_check_nmbr = '';
			}
		} else if (preg_match('/^2/', $entry_type)) {
			/* マンション固有のパラメータ */
			// 価格
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price'])) {
					$_REQUEST['entry_price'] = $nn4_estate_data['price'];
				}
			}
			if (isset($_REQUEST['entry_price'])
				&& $_REQUEST['entry_price'] != '') {
				if (nn4_param_chk_valid_string('entry_price',
											   '価格',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_price = stripslashes($_REQUEST['entry_price']);
				} else {
					$entry_price = '';
					$mode = 'input';
				}
			} else {
				$entry_price = '';
			}

			// 価格 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_memo'])) {
					$_REQUEST['entry_price_memo']
						= $nn4_estate_data['price_memo'];
				}
			}
			if (isset($_REQUEST['entry_price_memo'])) {
				$entry_price_memo
					= stripslashes($_REQUEST['entry_price_memo']);
			} else {
				$entry_price_memo = '';
			}

			// 価格(上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_max'])) {
					$_REQUEST['entry_price_max']
						= $nn4_estate_data['price_max'];
				}
			}
			if (isset($_REQUEST['entry_price_max'])
				&& $_REQUEST['entry_price_max'] != '') {
				if (nn4_param_chk_valid_string('entry_price_max',
											   '価格 (上限)',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_price != ''
						&& !nn4_param_chk_numeric_min(
							'entry_price_max',
							'価格 (上限)',
							$entry_price,
							$nn4_estate_last_err_msg
						)) {
						$entry_price_max = '';
						$mode = 'input';
					} else {
						$entry_price_max
							= stripslashes($_REQUEST['entry_price_max']);
					}
				} else {
					$entry_price_max = '';
					$mode = 'input';
				}
			} else {
				$entry_price_max = '';
			}

			// 管理費
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_service_charge'])) {
					$_REQUEST['entry_service_charge']
						= $nn4_estate_data['service_charge'];
				}
			}
			if (isset($_REQUEST['entry_service_charge'])
				&& $_REQUEST['entry_service_charge'] != '') {
				if (nn4_param_chk_valid_string('entry_service_charge',
											   '管理費',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_service_charge
						= stripslashes($_REQUEST['entry_service_charge']);
				} else {
					$entry_service_charge = '';
					$mode = 'input';
				}
			} else {
				$entry_service_charge = '';
			}

			// 修繕積立金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_maintenance_kitty'])) {
					$_REQUEST['entry_maintenance_kitty']
						= $nn4_estate_data['maintenance_kitty'];
				}
			}
			if (isset($_REQUEST['entry_maintenance_kitty'])
				&& $_REQUEST['entry_maintenance_kitty'] != '') {
				if (nn4_param_chk_valid_string('entry_maintenance_kitty',
											   '修繕積立金',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_maintenance_kitty
						= stripslashes($_REQUEST['entry_maintenance_kitty']);
				} else {
					$entry_maintenance_kitty = '';
					$mode = 'input';
				}
			} else {
				$entry_maintenance_kitty = '';
			}

			// 新築フラグ
			if ($mode == 'input') {
				if (isset($_REQUEST['entry_flg_new'])) {
					$entry_flg_new = stripslashes($_REQUEST['entry_flg_new']);
				} else {
					$entry_flg_new = '';
				}
			} else {
				if ($mode == 'editinput') {
					if (!isset($_REQUEST['entry_flg_new'])) {
						$_REQUEST['entry_flg_new']
							= $nn4_estate_data['flg_new'];
					}
				}
				if (nn4_param_chk_required('entry_flg_new', '新築/中古',
										   $nn4_estate_last_err_msg)) {
					if (nn4_param_chk_match_collection(
							'entry_flg_new',
							'新築/中古',
							array('0', '1'),
							$nn4_estate_last_err_msg
						)) {
						$entry_flg_new
							= stripslashes($_REQUEST['entry_flg_new']);
					} else {
						$entry_flg_new = '';
					}
				} else {
					$entry_flg_new = '';
				}
			}

			// 築年月
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_built_on'])) {
					$_REQUEST['entry_built_on']
						= $nn4_estate_data['built_on'];
				}
			}
			if (isset($_REQUEST['entry_built_on'])
				&& $_REQUEST['entry_built_on'] != '') {
				if (!preg_match('/^\d{4}(?:\-\d{2})?$/',
							   $_REQUEST['entry_built_on'])) {
					$nn4_estate_last_err_msg[]
						= '築年月日が正しくありません (YYYY または YYYY-MM)';
					$entry_built_on = '';
					$mode = 'input';
				} else {
					$entry_built_on
						= stripslashes($_REQUEST['entry_built_on']);
				}
			} else {
				$entry_built_on = '';
			}

			// 間取り
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_room_layout'])) {
					$_REQUEST['entry_room_layout']
						= $nn4_estate_data['room_layout'];
				}
			}
			if (isset($_REQUEST['entry_room_layout'])) {
				$entry_room_layout
					= stripslashes($_REQUEST['entry_room_layout']);
			} else {
				$entry_room_layout = '';
			}

			// 建物構造
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_structure'])) {
					$_REQUEST['entry_bldg_structure']
						= $nn4_estate_data['bldg_structure'];
				}
			}
			if (isset($_REQUEST['entry_bldg_structure'])) {
				$entry_bldg_structure
					= stripslashes($_REQUEST['entry_bldg_structure']);
			} else {
				$entry_bldg_structure = '';
			}

			// 所在階/階建
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_floor'])) {
					$_REQUEST['entry_floor']
						= $nn4_estate_data['floor'];
				}
			}
			if (isset($_REQUEST['entry_floor'])) {
				$entry_floor
					= stripslashes($_REQUEST['entry_floor']);
			} else {
				$entry_floor = '';
			}

			// 総戸数
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_family_num'])) {
					$_REQUEST['entry_family_num']
						= $nn4_estate_data['family_num'];
				}
			}
			if (isset($_REQUEST['entry_family_num'])
				&& $_REQUEST['entry_family_num'] != '') {
				if (nn4_param_chk_valid_string('entry_family_num',
											   '総戸数',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_family_num
						= stripslashes($_REQUEST['entry_family_num']);
				} else {
					$entry_family_num = '';
					$mode = 'input';
				}
			} else {
				$entry_family_num = '';
			}

			// 駐車スペース
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_parking_space'])) {
					$_REQUEST['entry_parking_space']
						= $nn4_estate_data['parking_space'];
				}
			}
			if (isset($_REQUEST['entry_parking_space'])) {
				$entry_parking_space
					= stripslashes($_REQUEST['entry_parking_space']);
			} else {
				$entry_parking_space = '';
			}

			// マンション名
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_name'])) {
					$_REQUEST['entry_bldg_name']
						= $nn4_estate_data['bldg_name'];
				}
			}
			if (isset($_REQUEST['entry_bldg_name'])) {
				$entry_bldg_name
					= stripslashes($_REQUEST['entry_bldg_name']);
			} else {
				$entry_bldg_name = '';
			}

			// 管理形態
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_management_form'])) {
					$_REQUEST['entry_management_form']
						= $nn4_estate_data['management_form'];
				}
			}
			if (isset($_REQUEST['entry_management_form'])) {
				$entry_management_form
					= stripslashes($_REQUEST['entry_management_form']);
			} else {
				$entry_management_form = '';
			}

			// 専有面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_exclusive_area'])) {
					$_REQUEST['entry_exclusive_area']
						= $nn4_estate_data['exclusive_area'];
				}
			}
			if (isset($_REQUEST['entry_exclusive_area'])
				&& $_REQUEST['entry_exclusive_area'] != '') {
				if (nn4_param_chk_valid_string('entry_exclusive_area',
											   '専有面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_exclusive_area
						= stripslashes($_REQUEST['entry_exclusive_area']);
				} else {
					$entry_exclusive_area = '';
					$mode = 'input';
				}
			} else {
				$entry_exclusive_area = '';
			}

			// 専有面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_exclusive_area_memo'])) {
					$_REQUEST['entry_exclusive_area_memo']
						= $nn4_estate_data['exclusive_area_memo'];
				}
			}
			if (isset($_REQUEST['entry_exclusive_area_memo'])) {
				$entry_exclusive_area_memo
					= stripslashes($_REQUEST['entry_exclusive_area_memo']);
			} else {
				$entry_exclusive_area_memo = '';
			}

			// バルコニー面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_balcony_dim'])) {
					$_REQUEST['entry_balcony_dim']
						= $nn4_estate_data['balcony_dim'];
				}
			}
			if (isset($_REQUEST['entry_balcony_dim'])
				&& $_REQUEST['entry_balcony_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_balcony_dim',
											   'バルコニー面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_balcony_dim
						= stripslashes($_REQUEST['entry_balcony_dim']);
				} else {
					$entry_balcony_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_balcony_dim = '';
			}

			// バルコニー面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_balcony_dim_memo'])) {
					$_REQUEST['entry_balcony_dim_memo']
						= $nn4_estate_data['balcony_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_balcony_dim_memo'])) {
				$entry_balcony_dim_memo
					= stripslashes($_REQUEST['entry_balcony_dim_memo']);
			} else {
				$entry_balcony_dim_memo = '';
			}

			// 土地権利
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_ownership_id'])) {
					$_REQUEST['entry_land_ownership_id']
						= $nn4_estate_data['land_ownership_id'];
				}
			}
			if (isset($_REQUEST['entry_land_ownership_id'])
				&& $_REQUEST['entry_land_ownership_id'] != '') {
				if (nn4_param_chk_match_collection(
						'entry_land_ownership_id',
						'土地権利',
						array_keys($nn4_estate_arr_land_ownership),
						$nn4_estate_last_err_msg)) {
					$entry_land_ownership_id
						= stripslashes(
							$_REQUEST['entry_land_ownership_id']
						);
				} else {
					$entry_land_ownership_id = '';
					$mode = 'input';
				}
			} else {
				$entry_land_ownership_id = '';
			}

			// 借地権 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_lease_memo'])) {
					$_REQUEST['entry_land_lease_memo']
						= $nn4_estate_data['land_lease_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_lease_memo'])) {
				$entry_land_lease_memo
					= stripslashes($_REQUEST['entry_land_lease_memo']);
			} else {
				$entry_land_lease_memo = '';
			}

			// 国土法届出
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_mlit_nmbr'])) {
					$_REQUEST['entry_mlit_nmbr']
						= $nn4_estate_data['mlit_nmbr'];
				}
			}
			if (isset($_REQUEST['entry_mlit_nmbr'])) {
				$entry_mlit_nmbr
					= stripslashes($_REQUEST['entry_mlit_nmbr']);
			} else {
				$entry_mlit_nmbr = '';
			}

			// 引渡し
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery'])) {
					$_REQUEST['entry_delivery']
						= $nn4_estate_data['delivery'];
				}
			}
			if (isset($_REQUEST['entry_delivery'])) {
				$entry_delivery
					= stripslashes($_REQUEST['entry_delivery']);
			} else {
				$entry_delivery = '';
			}

			// ペット
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pets'])) {
					$_REQUEST['entry_pets']
						= $nn4_estate_data['pets'];
				}
			}
			if (isset($_REQUEST['entry_pets'])) {
				$entry_pets
					= stripslashes($_REQUEST['entry_pets']);
			} else {
				$entry_pets = '';
			}
		} else if (preg_match('/^3/', $entry_type)) {
			/* 土地固有のパラメータ */
			// 街区・画地
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_city_block'])) {
					$_REQUEST['entry_city_block']
						= $nn4_estate_data['city_block'];
				}
			}
			if (isset($_REQUEST['entry_city_block'])) {
				$entry_city_block
					= stripslashes($_REQUEST['entry_city_block']);
			} else {
				$entry_city_block = '';
			}

			// 販売区画
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_block_num'])) {
					$_REQUEST['entry_block_num']
						= $nn4_estate_data['block_num'];
				}
			}
			if (isset($_REQUEST['entry_block_num'])) {
				$entry_block_num
					= stripslashes($_REQUEST['entry_block_num']);
			} else {
				$entry_block_num = '';
			}

			// 価格
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price'])) {
					$_REQUEST['entry_price'] = $nn4_estate_data['price'];
				}
			}
			if (isset($_REQUEST['entry_price'])
				&& $_REQUEST['entry_price'] != '') {
				if (nn4_param_chk_valid_string('entry_price',
											   '価格',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_price = stripslashes($_REQUEST['entry_price']);
				} else {
					$entry_price = '';
					$mode = 'input';
				}
			} else {
				$entry_price = '';
			}

			// 価格 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_memo'])) {
					$_REQUEST['entry_price_memo']
						= $nn4_estate_data['price_memo'];
				}
			}
			if (isset($_REQUEST['entry_price_memo'])) {
				$entry_price_memo
					= stripslashes($_REQUEST['entry_price_memo']);
			} else {
				$entry_price_memo = '';
			}

			// 価格(上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_price_max'])) {
					$_REQUEST['entry_price_max']
						= $nn4_estate_data['price_max'];
				}
			}
			if (isset($_REQUEST['entry_price_max'])
				&& $_REQUEST['entry_price_max'] != '') {
				if (nn4_param_chk_valid_string('entry_price_max',
											   '価格 (上限)',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_price != ''
						&& !nn4_param_chk_numeric_min(
							'entry_price_max',
							'価格 (上限)',
							$entry_price,
							$nn4_estate_last_err_msg
						)) {
						$entry_price_max = '';
						$mode = 'input';
					} else {
						$entry_price_max
							= stripslashes($_REQUEST['entry_price_max']);
					}
				} else {
					$entry_price_max = '';
					$mode = 'input';
				}
			} else {
				$entry_price_max = '';
			}

			// 土地面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim'])) {
					$_REQUEST['entry_land_dim']
						= $nn4_estate_data['land_dim'];
				}
			}
			if (isset($_REQUEST['entry_land_dim'])
				&& $_REQUEST['entry_land_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim',
											   '土地面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_land_dim
						= stripslashes($_REQUEST['entry_land_dim']);
				} else {
					$entry_land_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim = '';
			}

			// 土地面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_memo'])) {
					$_REQUEST['entry_land_dim_memo']
						= $nn4_estate_data['land_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_memo'])) {
				$entry_land_dim_memo
					= stripslashes($_REQUEST['entry_land_dim_memo']);
			} else {
				$entry_land_dim_memo = '';
			}

			// 土地面積 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_max'])) {
					$_REQUEST['entry_land_dim_max']
						= $nn4_estate_data['land_dim_max'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_max'])
				&& $_REQUEST['entry_land_dim_max'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim_max',
											   '土地面積 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_land_dim != ''
						&& !nn4_param_chk_numeric_min(
							'entry_land_dim_max',
							'土地面積 (上限)',
							$entry_land_dim,
							$nn4_estate_last_err_msg
						)) {
						$entry_land_dim_max = '';
						$mode = 'input';
					} else {
						$entry_land_dim_max
							= stripslashes($_REQUEST['entry_land_dim_max']);
					}
				} else {
					$entry_land_dim_max = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim_max = '';
			}

			// 坪数
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong'])) {
					$_REQUEST['entry_pyeong'] = $nn4_estate_data['pyeong'];
				}
			}
			if (isset($_REQUEST['entry_pyeong'])
				&& $_REQUEST['entry_pyeong'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong',
											   '坪数',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_pyeong
						= stripslashes($_REQUEST['entry_pyeong']);
				} else {
					$entry_pyeong = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong = '';
			}

			// 坪数 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_memo'])) {
					$_REQUEST['entry_pyeong_memo']
						= $nn4_estate_data['pyeong_memo'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_memo'])) {
				$entry_pyeong_memo
					= stripslashes($_REQUEST['entry_pyeong_memo']);
			} else {
				$entry_pyeong_memo = '';
			}

			// 坪数 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_max'])) {
					$_REQUEST['entry_pyeong_max']
						= $nn4_estate_data['pyeong_max'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_max'])
				&& $_REQUEST['entry_pyeong_max'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong_max',
											   '坪数 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_pyeong != ''
						&& !nn4_param_chk_numeric_min(
							'entry_pyeong_max',
							'坪数 (上限)',
							$entry_pyeong,
							$nn4_estate_last_err_msg
						)) {
						$entry_pyeong_max = '';
						$mode = 'input';
					} else {
						$entry_pyeong_max
							= stripslashes($_REQUEST['entry_pyeong_max']);
					}
				} else {
					$entry_pyeong_max = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong_max = '';
			}

			// 私道負担面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim'])) {
					$_REQUEST['entry_driveway_dim']
						= $nn4_estate_data['driveway_dim'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim'])
				&& $_REQUEST['entry_driveway_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_driveway_dim',
											   '私道負担面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_driveway_dim
						= stripslashes($_REQUEST['entry_driveway_dim']);
				} else {
					$entry_driveway_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_driveway_dim = '';
			}

			// 私道負担面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim_memo'])) {
					$_REQUEST['entry_driveway_dim_memo']
						= $nn4_estate_data['driveway_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim_memo'])) {
				$entry_driveway_dim_memo
					= stripslashes($_REQUEST['entry_driveway_dim_memo']);
			} else {
				$entry_driveway_dim_memo = '';
			}

			// セットバック
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_setback'])) {
					$_REQUEST['entry_setback'] = $nn4_estate_data['setback'];
				}
			}
			if (isset($_REQUEST['entry_setback'])) {
				$entry_setback = stripslashes($_REQUEST['entry_setback']);
			} else {
				$entry_setback = '';
			}

			// 建ぺい率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage'])) {
					$_REQUEST['entry_bldg_coverage']
						= $nn4_estate_data['bldg_coverage'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage'])
				&& $_REQUEST['entry_bldg_coverage'] != '') {
				if (nn4_param_chk_valid_string('entry_bldg_coverage',
											   '建ぺい率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if (nn4_param_chk_numeric_max(
							'entry_bldg_coverage',
							'建ぺい率',
							100,
							$nn4_estate_last_err_msg
						)) {
						$entry_bldg_coverage
							= stripslashes(
								$_REQUEST['entry_bldg_coverage']
							);
					} else {
						$entry_bldg_coverage = '';
						$mode = 'input';
					}
				} else {
					$entry_bldg_coverage = '';
					$mode = 'input';
				}
			} else {
				$entry_bldg_coverage = '';
			}

			// 建ぺい率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage_memo'])) {
					$_REQUEST['entry_bldg_coverage_memo']
						= $nn4_estate_data['bldg_coverage_memo'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage_memo'])) {
				$entry_bldg_coverage_memo
					= stripslashes($_REQUEST['entry_bldg_coverage_memo']);
			} else {
				$entry_bldg_coverage_memo = '';
			}

			// 容積率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio'])) {
					$_REQUEST['entry_plot_ratio']
						= $nn4_estate_data['plot_ratio'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio'])
				&& $_REQUEST['entry_plot_ratio'] != '') {
				if (nn4_param_chk_valid_string('entry_plot_ratio',
											   '容積率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_plot_ratio
						= stripslashes($_REQUEST['entry_plot_ratio']);
				} else {
					$entry_plot_ratio = '';
					$mode = 'input';
				}
			} else {
				$entry_plot_ratio = '';
			}

			// 容積率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio_memo'])) {
					$_REQUEST['entry_plot_ratio_memo']
						= $nn4_estate_data['plot_ratio_memo'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio_memo'])) {
				$entry_plot_ratio_memo
					= stripslashes($_REQUEST['entry_plot_ratio_memo']);
			} else {
				$entry_plot_ratio_memo = '';
			}

			// 開発面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_development_area'])) {
					$_REQUEST['entry_development_area']
						= $nn4_estate_data['development_area'];
				}
			}
			if (isset($_REQUEST['entry_development_area'])) {
				$entry_development_area
					= stripslashes($_REQUEST['entry_development_area']);
			} else {
				$entry_development_area = '';
			}

			// 土地権利
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_ownership_id'])) {
					$_REQUEST['entry_land_ownership_id']
						= $nn4_estate_data['land_ownership_id'];
				}
			}
			if (isset($_REQUEST['entry_land_ownership_id'])
				&& $_REQUEST['entry_land_ownership_id'] != '') {
				if (nn4_param_chk_match_collection(
						'entry_land_ownership_id',
						'土地権利',
						array_keys($nn4_estate_arr_land_ownership),
						$nn4_estate_last_err_msg)) {
					$entry_land_ownership_id
						= stripslashes(
							$_REQUEST['entry_land_ownership_id']
						);
				} else {
					$entry_land_ownership_id = '';
					$mode = 'input';
				}
			} else {
				$entry_land_ownership_id = '';
			}

			// 借地権 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_lease_memo'])) {
					$_REQUEST['entry_land_lease_memo']
						= $nn4_estate_data['land_lease_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_lease_memo'])) {
				$entry_land_lease_memo
					= stripslashes($_REQUEST['entry_land_lease_memo']);
			} else {
				$entry_land_lease_memo = '';
			}

			// 地目
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_category'])) {
					$_REQUEST['entry_land_category']
						= $nn4_estate_data['land_category'];
				}
			}
			if (isset($_REQUEST['entry_land_category'])) {
				$entry_land_category
					= stripslashes($_REQUEST['entry_land_category']);
			} else {
				$entry_land_category = '';
			}

			// 最適用途
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_preferred_usage'])) {
					$_REQUEST['entry_preferred_usage']
						= $nn4_estate_data['preferred_usage'];
				}
			}
			if (isset($_REQUEST['entry_preferred_usage'])) {
				$entry_preferred_usage
					= stripslashes($_REQUEST['entry_preferred_usage']);
			} else {
				$entry_preferred_usage = '';
			}

			// 用途地域
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_zoning'])) {
					$_REQUEST['entry_zoning'] = $nn4_estate_data['zoning'];
				}
			}
			if (isset($_REQUEST['entry_zoning'])) {
				$entry_zoning
					= stripslashes($_REQUEST['entry_zoning']);
			} else {
				$entry_zoning = '';
			}

			// 都市計画
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_area_control'])) {
					$_REQUEST['entry_area_control']
						= $nn4_estate_data['area_control'];
				}
			}
			if (isset($_REQUEST['entry_area_control'])) {
				$entry_area_control
					= stripslashes($_REQUEST['entry_area_control']);
			} else {
				$entry_area_control = '';
			}

			// 法令上の制限
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_restriction'])) {
					$_REQUEST['entry_restriction']
						= $nn4_estate_data['restriction'];
				}
			}
			if (isset($_REQUEST['entry_restriction'])) {
				$entry_restriction
					= stripslashes($_REQUEST['entry_restriction']);
			} else {
				$entry_restriction = '';
			}

			// 国土法届出
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_mlit_nmbr'])) {
					$_REQUEST['entry_mlit_nmbr']
						= $nn4_estate_data['mlit_nmbr'];
				}
			}
			if (isset($_REQUEST['entry_mlit_nmbr'])) {
				$entry_mlit_nmbr
					= stripslashes($_REQUEST['entry_mlit_nmbr']);
			} else {
				$entry_mlit_nmbr = '';
			}

			// 接道状況
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_abutting_road'])) {
					$_REQUEST['entry_abutting_road']
						= $nn4_estate_data['abutting_road'];
				}
			}
			if (isset($_REQUEST['entry_abutting_road'])) {
				$entry_abutting_road
					= stripslashes($_REQUEST['entry_abutting_road']);
			} else {
				$entry_abutting_road = '';
			}

			// 地勢
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_landscape'])) {
					$_REQUEST['entry_landscape']
						= $nn4_estate_data['landscape'];
				}
			}
			if (isset($_REQUEST['entry_landscape'])) {
				$entry_landscape
					= stripslashes($_REQUEST['entry_landscape']);
			} else {
				$entry_landscape = '';
			}

			// 引渡し
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery'])) {
					$_REQUEST['entry_delivery']
						= $nn4_estate_data['delivery'];
				}
			}
			if (isset($_REQUEST['entry_delivery'])) {
				$entry_delivery
					= stripslashes($_REQUEST['entry_delivery']);
			} else {
				$entry_delivery = '';
			}

			// 引渡し条件
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery_cond'])) {
					$_REQUEST['entry_delivery_cond']
						= $nn4_estate_data['delivery_cond'];
				}
			}
			if (isset($_REQUEST['entry_delivery_cond'])) {
				$entry_delivery_cond
					= stripslashes($_REQUEST['entry_delivery_cond']);
			} else {
				$entry_delivery_cond = '';
			}

			// 引渡し日
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery_date'])) {
					$_REQUEST['entry_delivery_date']
						= $nn4_estate_data['delivery_date'];
				}
			}
			if (isset($_REQUEST['entry_delivery_date'])) {
				$entry_delivery_date
					= stripslashes($_REQUEST['entry_delivery_date']);
			} else {
				$entry_delivery_date = '';
			}
		} else if (preg_match('/^4/', $entry_type)) {
			/* 賃貸住居固有のパラメータ */
			// 敷金/保証金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_deposit_rate'])) {
					$_REQUEST['entry_deposit_rate']
						= $nn4_estate_data['deposit_rate'];
				}
			}
			if (isset($_REQUEST['entry_deposit_rate'])
				&& $_REQUEST['entry_deposit_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_deposit_rate',
											   '敷金/保証金',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_deposit_rate
						= stripslashes($_REQUEST['entry_deposit_rate']);
				} else {
					$entry_deposit_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_deposit_rate = '';
			}

			// 礼金/権利金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_key_money_rate'])) {
					$_REQUEST['entry_key_money_rate']
						= $nn4_estate_data['key_money_rate'];
				}
			}
			if (isset($_REQUEST['entry_key_money_rate'])
				&& $_REQUEST['entry_key_money_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_key_money_rate',
											   '礼金/権利金',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_key_money_rate
						= stripslashes($_REQUEST['entry_key_money_rate']);
				} else {
					$entry_key_money_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_key_money_rate = '';
			}

			// 仲介手数料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_agent_charge_rate'])) {
					$_REQUEST['entry_agent_charge_rate']
						= $nn4_estate_data['agent_charge_rate'];
				}
			}
			if (isset($_REQUEST['entry_agent_charge_rate'])
				&& $_REQUEST['entry_agent_charge_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_agent_charge_rate',
											   '仲介手数料',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_agent_charge_rate
						= stripslashes($_REQUEST['entry_agent_charge_rate']);
				} else {
					$entry_agent_charge_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_agent_charge_rate = '';
			}

			// 敷引/保証金償却
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_amoritization_rate'])) {
					$_REQUEST['entry_amoritization_rate']
						= $nn4_estate_data['amoritization_rate'];
				}
			}
			if (isset($_REQUEST['entry_amoritization_rate'])
				&& $_REQUEST['entry_amoritization_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_amoritization_rate',
											   '敷引/保証金償却',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_amoritization_rate
						= stripslashes($_REQUEST['entry_amoritization_rate']);
				} else {
					$entry_amoritization_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_amoritization_rate = '';
			}

			// その他一時金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_lump_sum'])) {
					$_REQUEST['entry_lump_sum'] = $nn4_estate_data['lump_sum'];
				}
			}
			if (isset($_REQUEST['entry_lump_sum'])
				&& $_REQUEST['entry_lump_sum'] != '') {
				if (nn4_param_chk_valid_string('entry_lump_sum',
											   '価格',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_lump_sum = stripslashes($_REQUEST['entry_lump_sum']);
				} else {
					$entry_lump_sum = '';
					$mode = 'input';
				}
			} else {
				$entry_lump_sum = '';
			}

			// その他一時金 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_lump_sum_memo'])) {
					$_REQUEST['entry_lump_sum_memo']
						= $nn4_estate_data['lump_sum_memo'];
				}
			}
			if (isset($_REQUEST['entry_lump_sum_memo'])) {
				$entry_lump_sum_memo
					= stripslashes($_REQUEST['entry_lump_sum_memo']);
			} else {
				$entry_lump_sum_memo = '';
			}

			// 契約期間
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_contract_period'])) {
					$_REQUEST['entry_contract_period']
						= $nn4_estate_data['contract_period'];
				}
			}
			if (isset($_REQUEST['entry_contract_period'])) {
				$entry_contract_period
					= stripslashes($_REQUEST['entry_contract_period']);
			} else {
				$entry_contract_period = '';
			}

			// 月額賃貸料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent'])) {
					$_REQUEST['entry_rent'] = $nn4_estate_data['rent'];
				}
			}
			if (isset($_REQUEST['entry_rent'])
				&& $_REQUEST['entry_rent'] != '') {
				if (nn4_param_chk_valid_string('entry_rent',
											   '月額賃貸料',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_rent = stripslashes($_REQUEST['entry_rent']);
				} else {
					$entry_rent = '';
					$mode = 'input';
				}
			} else {
				$entry_rent = '';
			}

			// 月額賃貸料 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent_memo'])) {
					$_REQUEST['entry_rent_memo']
						= $nn4_estate_data['rent_memo'];
				}
			}
			if (isset($_REQUEST['entry_rent_memo'])) {
				$entry_rent_memo
					= stripslashes($_REQUEST['entry_rent_memo']);
			} else {
				$entry_rent_memo = '';
			}

			// 月額賃貸料(上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent_max'])) {
					$_REQUEST['entry_rent_max']
						= $nn4_estate_data['rent_max'];
				}
			}
			if (isset($_REQUEST['entry_rent_max'])
				&& $_REQUEST['entry_rent_max'] != '') {
				if (nn4_param_chk_valid_string('entry_rent_max',
											   '月額賃貸料 (上限)',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_rent != ''
						&& !nn4_param_chk_numeric_min(
							'entry_rent_max',
							'月額賃貸料 (上限)',
							$entry_rent,
							$nn4_estate_last_err_msg
						)) {
						$entry_rent_max = '';
						$mode = 'input';
					} else {
						$entry_rent_max
							= stripslashes($_REQUEST['entry_rent_max']);
					}
				} else {
					$entry_rent_max = '';
					$mode = 'input';
				}
			} else {
				$entry_rent_max = '';
			}

			// 月額共益費
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_common_area_charge'])) {
					$_REQUEST['entry_common_area_charge']
						= $nn4_estate_data['common_area_charge'];
				}
			}
			if (isset($_REQUEST['entry_common_area_charge'])
				&& $_REQUEST['entry_common_area_charge'] != '') {
				if (nn4_param_chk_valid_string('entry_common_area_charge',
											   '月額共益費',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_common_area_charge
						= stripslashes($_REQUEST['entry_common_area_charge']);
				} else {
					$entry_common_area_charge = '';
					$mode = 'input';
				}
			} else {
				$entry_common_area_charge = '';
			}

			// 月額管理費
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_service_charge'])) {
					$_REQUEST['entry_service_charge']
						= $nn4_estate_data['service_charge'];
				}
			}
			if (isset($_REQUEST['entry_service_charge'])
				&& $_REQUEST['entry_service_charge'] != '') {
				if (nn4_param_chk_valid_string('entry_service_charge',
											   '月額管理費',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_service_charge
						= stripslashes($_REQUEST['entry_service_charge']);
				} else {
					$entry_service_charge = '';
					$mode = 'input';
				}
			} else {
				$entry_service_charge = '';
			}

			// 更新料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_renewal_charge_rate'])) {
					$_REQUEST['entry_renewal_charge_rate']
						= $nn4_estate_data['renewal_charge_rate'];
				}
			}
			if (isset($_REQUEST['entry_renewal_charge_rate'])
				&& $_REQUEST['entry_renewal_charge_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_renewal_charge_rate',
											   '更新料',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_renewal_charge_rate
						= stripslashes($_REQUEST['entry_renewal_charge_rate']);
				} else {
					$entry_renewal_charge_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_renewal_charge_rate = '';
			}

			// 保険内容
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_insurance'])) {
					$_REQUEST['entry_insurance']
						= $nn4_estate_data['insurance'];
				}
			}
			if (isset($_REQUEST['entry_insurance'])) {
				$entry_insurance
					= stripslashes($_REQUEST['entry_insurance']);
			} else {
				$entry_insurance = '';
			}

			// 築年月
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_built_on'])) {
					$_REQUEST['entry_built_on']
						= $nn4_estate_data['built_on'];
				}
			}
			if (isset($_REQUEST['entry_built_on'])
				&& $_REQUEST['entry_built_on'] != '') {
				if (!preg_match('/^\d{4}(?:\-\d{2})?$/',
							   $_REQUEST['entry_built_on'])) {
					$nn4_estate_last_err_msg[]
						= '築年月日が正しくありません (YYYY または YYYY-MM)';
					$mode = 'input';
					$entry_built_on = '';
				} else {
					$entry_built_on
						= stripslashes($_REQUEST['entry_built_on']);
				}
			} else {
				$entry_built_on = '';
			}

			// 間取り
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_room_layout'])) {
					$_REQUEST['entry_room_layout']
						= $nn4_estate_data['room_layout'];
				}
			}
			if (isset($_REQUEST['entry_room_layout'])) {
				$entry_room_layout
					= stripslashes($_REQUEST['entry_room_layout']);
			} else {
				$entry_room_layout = '';
			}

			// 面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_floor_space'])) {
					$_REQUEST['entry_floor_space']
						= $nn4_estate_data['floor_space'];
				}
			}
			if (isset($_REQUEST['entry_floor_space'])
				&& $_REQUEST['entry_floor_space'] != '') {
				if (nn4_param_chk_valid_string('entry_floor_space',
											   '面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_floor_space
						= stripslashes($_REQUEST['entry_floor_space']);
				} else {
					$entry_floor_space = '';
					$mode = 'input';
				}
			} else {
				$entry_floor_space = '';
			}

			// 面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_floor_space_memo'])) {
					$_REQUEST['entry_floor_space_memo']
						= $nn4_estate_data['floor_space_memo'];
				}
			}
			if (isset($_REQUEST['entry_floor_space_memo'])) {
				$entry_floor_space_memo
					= stripslashes($_REQUEST['entry_floor_space_memo']);
			} else {
				$entry_floor_space_memo = '';
			}

			// 建物構造
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_structure'])) {
					$_REQUEST['entry_bldg_structure']
						= $nn4_estate_data['bldg_structure'];
				}
			}
			if (isset($_REQUEST['entry_bldg_structure'])) {
				$entry_bldg_structure
					= stripslashes($_REQUEST['entry_bldg_structure']);
			} else {
				$entry_bldg_structure = '';
			}

			// 所在階/階建
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_floor'])) {
					$_REQUEST['entry_floor']
						= $nn4_estate_data['floor'];
				}
			}
			if (isset($_REQUEST['entry_floor'])) {
				$entry_floor
					= stripslashes($_REQUEST['entry_floor']);
			} else {
				$entry_floor = '';
			}

			// 駐車スペース
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_parking_space'])) {
					$_REQUEST['entry_parking_space']
						= $nn4_estate_data['parking_space'];
				}
			}
			if (isset($_REQUEST['entry_parking_space'])) {
				$entry_parking_space
					= stripslashes($_REQUEST['entry_parking_space']);
			} else {
				$entry_parking_space = '';
			}

			// 名称等
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_name'])) {
					$_REQUEST['entry_bldg_name']
						= $nn4_estate_data['bldg_name'];
				}
			}
			if (isset($_REQUEST['entry_bldg_name'])) {
				$entry_bldg_name
					= stripslashes($_REQUEST['entry_bldg_name']);
			} else {
				$entry_bldg_name = '';
			}

			// 入居日
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_available_from'])) {
					$_REQUEST['entry_available_from']
						= $nn4_estate_data['available_from'];
				}
			}
			if (isset($_REQUEST['entry_available_from'])) {
				$entry_available_from
					= stripslashes($_REQUEST['entry_available_from']);
			} else {
				$entry_available_from = '';
			}

			// ペット
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pets'])) {
					$_REQUEST['entry_pets']
						= $nn4_estate_data['pets'];
				}
			}
			if (isset($_REQUEST['entry_pets'])) {
				$entry_pets
					= stripslashes($_REQUEST['entry_pets']);
			} else {
				$entry_pets = '';
			}
		} else {  // preg_match('/^5/', $entry_type)) {
			/* 賃貸土地固有のパラメータ */
			// 街区・画地
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_city_block'])) {
					$_REQUEST['entry_city_block']
						= $nn4_estate_data['city_block'];
				}
			}
			if (isset($_REQUEST['entry_city_block'])) {
				$entry_city_block
					= stripslashes($_REQUEST['entry_city_block']);
			} else {
				$entry_city_block = '';
			}

			// 販売区画
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_block_num'])) {
					$_REQUEST['entry_block_num']
						= $nn4_estate_data['block_num'];
				}
			}
			if (isset($_REQUEST['entry_block_num'])) {
				$entry_block_num
					= stripslashes($_REQUEST['entry_block_num']);
			} else {
				$entry_block_num = '';
			}

			// 敷金/保証金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_deposit_rate'])) {
					$_REQUEST['entry_deposit_rate']
						= $nn4_estate_data['deposit_rate'];
				}
			}
			if (isset($_REQUEST['entry_deposit_rate'])
				&& $_REQUEST['entry_deposit_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_deposit_rate',
											   '敷金/保証金',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_deposit_rate
						= stripslashes($_REQUEST['entry_deposit_rate']);
				} else {
					$entry_deposit_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_deposit_rate = '';
			}

			// 礼金/権利金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_key_money_rate'])) {
					$_REQUEST['entry_key_money_rate']
						= $nn4_estate_data['key_money_rate'];
				}
			}
			if (isset($_REQUEST['entry_key_money_rate'])
				&& $_REQUEST['entry_key_money_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_key_money_rate',
											   '礼金/権利金',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_key_money_rate
						= stripslashes($_REQUEST['entry_key_money_rate']);
				} else {
					$entry_key_money_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_key_money_rate = '';
			}

			// 仲介手数料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_agent_charge_rate'])) {
					$_REQUEST['entry_agent_charge_rate']
						= $nn4_estate_data['agent_charge_rate'];
				}
			}
			if (isset($_REQUEST['entry_agent_charge_rate'])
				&& $_REQUEST['entry_agent_charge_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_agent_charge_rate',
											   '仲介手数料',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_agent_charge_rate
						= stripslashes($_REQUEST['entry_agent_charge_rate']);
				} else {
					$entry_agent_charge_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_agent_charge_rate = '';
			}

			// 敷引/保証金償却
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_amoritization_rate'])) {
					$_REQUEST['entry_amoritization_rate']
						= $nn4_estate_data['amoritization_rate'];
				}
			}
			if (isset($_REQUEST['entry_amoritization_rate'])
				&& $_REQUEST['entry_amoritization_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_amoritization_rate',
											   '敷引/保証金償却',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_amoritization_rate
						= stripslashes($_REQUEST['entry_amoritization_rate']);
				} else {
					$entry_amoritization_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_amoritization_rate = '';
			}

			// その他一時金
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_lump_sum'])) {
					$_REQUEST['entry_lump_sum'] = $nn4_estate_data['lump_sum'];
				}
			}
			if (isset($_REQUEST['entry_lump_sum'])
				&& $_REQUEST['entry_lump_sum'] != '') {
				if (nn4_param_chk_valid_string('entry_lump_sum',
											   '価格',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_lump_sum = stripslashes($_REQUEST['entry_lump_sum']);
				} else {
					$entry_lump_sum = '';
					$mode = 'input';
				}
			} else {
				$entry_lump_sum = '';
			}

			// その他一時金 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_lump_sum_memo'])) {
					$_REQUEST['entry_lump_sum_memo']
						= $nn4_estate_data['lump_sum_memo'];
				}
			}
			if (isset($_REQUEST['entry_lump_sum_memo'])) {
				$entry_lump_sum_memo
					= stripslashes($_REQUEST['entry_lump_sum_memo']);
			} else {
				$entry_lump_sum_memo = '';
			}

			// 契約期間
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_contract_period'])) {
					$_REQUEST['entry_contract_period']
						= $nn4_estate_data['contract_period'];
				}
			}
			if (isset($_REQUEST['entry_contract_period'])) {
				$entry_contract_period
					= stripslashes($_REQUEST['entry_contract_period']);
			} else {
				$entry_contract_period = '';
			}

			// 月額賃貸料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent'])) {
					$_REQUEST['entry_rent'] = $nn4_estate_data['rent'];
				}
			}
			if (isset($_REQUEST['entry_rent'])
				&& $_REQUEST['entry_rent'] != '') {
				if (nn4_param_chk_valid_string('entry_rent',
											   '月額賃貸料',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_rent = stripslashes($_REQUEST['entry_rent']);
				} else {
					$entry_rent = '';
					$mode = 'input';
				}
			} else {
				$entry_rent = '';
			}

			// 月額賃貸料 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent_memo'])) {
					$_REQUEST['entry_rent_memo']
						= $nn4_estate_data['rent_memo'];
				}
			}
			if (isset($_REQUEST['entry_rent_memo'])) {
				$entry_rent_memo
					= stripslashes($_REQUEST['entry_rent_memo']);
			} else {
				$entry_rent_memo = '';
			}

			// 月額賃貸料(上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_rent_max'])) {
					$_REQUEST['entry_rent_max']
						= $nn4_estate_data['rent_max'];
				}
			}
			if (isset($_REQUEST['entry_rent_max'])
				&& $_REQUEST['entry_rent_max'] != '') {
				if (nn4_param_chk_valid_string('entry_rent_max',
											   '月額賃貸料 (上限)',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_rent != ''
						&& !nn4_param_chk_numeric_min(
							'entry_rent_max',
							'月額賃貸料 (上限)',
							$entry_rent,
							$nn4_estate_last_err_msg
						)) {
						$entry_rent_max = '';
						$mode = 'input';
					} else {
						$entry_rent_max
							= stripslashes($_REQUEST['entry_rent_max']);
					}
				} else {
					$entry_rent_max = '';
					$mode = 'input';
				}
			} else {
				$entry_rent_max = '';
			}

			// 月額管理費
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_service_charge'])) {
					$_REQUEST['entry_service_charge']
						= $nn4_estate_data['service_charge'];
				}
			}
			if (isset($_REQUEST['entry_service_charge'])
				&& $_REQUEST['entry_service_charge'] != '') {
				if (nn4_param_chk_valid_string('entry_service_charge',
											   '月額管理費',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_service_charge
						= stripslashes($_REQUEST['entry_service_charge']);
				} else {
					$entry_service_charge = '';
					$mode = 'input';
				}
			} else {
				$entry_service_charge = '';
			}

			// 更新料
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_renewal_charge_rate'])) {
					$_REQUEST['entry_renewal_charge_rate']
						= $nn4_estate_data['renewal_charge_rate'];
				}
			}
			if (isset($_REQUEST['entry_renewal_charge_rate'])
				&& $_REQUEST['entry_renewal_charge_rate'] != '') {
				if (nn4_param_chk_valid_string('entry_renewal_charge_rate',
											   '更新料',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_renewal_charge_rate
						= stripslashes($_REQUEST['entry_renewal_charge_rate']);
				} else {
					$entry_renewal_charge_rate = '';
					$mode = 'input';
				}
			} else {
				$entry_renewal_charge_rate = '';
			}

			// 保険内容
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_insurance'])) {
					$_REQUEST['entry_insurance']
						= $nn4_estate_data['insurance'];
				}
			}
			if (isset($_REQUEST['entry_insurance'])) {
				$entry_insurance
					= stripslashes($_REQUEST['entry_insurance']);
			} else {
				$entry_insurance = '';
			}

			// 土地面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim'])) {
					$_REQUEST['entry_land_dim']
						= $nn4_estate_data['land_dim'];
				}
			}
			if (isset($_REQUEST['entry_land_dim'])
				&& $_REQUEST['entry_land_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim',
											   '土地面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_land_dim
						= stripslashes($_REQUEST['entry_land_dim']);
				} else {
					$entry_land_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim = '';
			}

			// 土地面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_memo'])) {
					$_REQUEST['entry_land_dim_memo']
						= $nn4_estate_data['land_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_memo'])) {
				$entry_land_dim_memo
					= stripslashes($_REQUEST['entry_land_dim_memo']);
			} else {
				$entry_land_dim_memo = '';
			}

			// 土地面積 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_dim_max'])) {
					$_REQUEST['entry_land_dim_max']
						= $nn4_estate_data['land_dim_max'];
				}
			}
			if (isset($_REQUEST['entry_land_dim_max'])
				&& $_REQUEST['entry_land_dim_max'] != '') {
				if (nn4_param_chk_valid_string('entry_land_dim_max',
											   '土地面積 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_land_dim != ''
						&& !nn4_param_chk_numeric_min(
							'entry_land_dim_max',
							'土地面積 (上限)',
							$entry_land_dim,
							$nn4_estate_last_err_msg
						)) {
						$entry_land_dim_max = '';
						$mode = 'input';
					} else {
						$entry_land_dim_max
							= stripslashes($_REQUEST['entry_land_dim_max']);
					}
				} else {
					$entry_land_dim_max = '';
					$mode = 'input';
				}
			} else {
				$entry_land_dim_max = '';
			}

			// 坪数
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong'])) {
					$_REQUEST['entry_pyeong'] = $nn4_estate_data['pyeong'];
				}
			}
			if (isset($_REQUEST['entry_pyeong'])
				&& $_REQUEST['entry_pyeong'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong',
											   '坪数',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_pyeong
						= stripslashes($_REQUEST['entry_pyeong']);
				} else {
					$entry_pyeong = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong = '';
			}

			// 坪数 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_memo'])) {
					$_REQUEST['entry_pyeong_memo']
						= $nn4_estate_data['pyeong_memo'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_memo'])) {
				$entry_pyeong_memo
					= stripslashes($_REQUEST['entry_pyeong_memo']);
			} else {
				$entry_pyeong_memo = '';
			}

			// 坪数 (上限)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_pyeong_max'])) {
					$_REQUEST['entry_pyeong_max']
						= $nn4_estate_data['pyeong_max'];
				}
			}
			if (isset($_REQUEST['entry_pyeong_max'])
				&& $_REQUEST['entry_pyeong_max'] != '') {
				if (nn4_param_chk_valid_string('entry_pyeong_max',
											   '坪数 (上限)',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					if ($entry_pyeong != ''
						&& !nn4_param_chk_numeric_min(
							'entry_pyeong_max',
							'坪数 (上限)',
							$entry_pyeong,
							$nn4_estate_last_err_msg
						)) {
						$entry_pyeong_max = '';
						$mode = 'input';
					} else {
						$entry_pyeong_max
							= stripslashes($_REQUEST['entry_pyeong_max']);
					}
				} else {
					$entry_pyeong_max = '';
					$mode = 'input';
				}
			} else {
				$entry_pyeong_max = '';
			}

			// 私道負担面積
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim'])) {
					$_REQUEST['entry_driveway_dim']
						= $nn4_estate_data['driveway_dim'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim'])
				&& $_REQUEST['entry_driveway_dim'] != '') {
				if (nn4_param_chk_valid_string('entry_driveway_dim',
											   '私道負担面積',
											   array('numeric', 'dots'),
											   $nn4_estate_last_err_msg)) {
					$entry_driveway_dim
						= stripslashes($_REQUEST['entry_driveway_dim']);
				} else {
					$entry_driveway_dim = '';
					$mode = 'input';
				}
			} else {
				$entry_driveway_dim = '';
			}

			// 私道負担面積 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_driveway_dim_memo'])) {
					$_REQUEST['entry_driveway_dim_memo']
						= $nn4_estate_data['driveway_dim_memo'];
				}
			}
			if (isset($_REQUEST['entry_driveway_dim_memo'])) {
				$entry_driveway_dim_memo
					= stripslashes($_REQUEST['entry_driveway_dim_memo']);
			} else {
				$entry_driveway_dim_memo = '';
			}

			// セットバック
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_setback'])) {
					$_REQUEST['entry_setback'] = $nn4_estate_data['setback'];
				}
			}
			if (isset($_REQUEST['entry_setback'])) {
				$entry_setback = stripslashes($_REQUEST['entry_setback']);
			} else {
				$entry_setback = '';
			}

			// 建ぺい率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage'])) {
					$_REQUEST['entry_bldg_coverage']
						= $nn4_estate_data['bldg_coverage'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage'])
				&& $_REQUEST['entry_bldg_coverage'] != '') {
				if (nn4_param_chk_valid_string('entry_bldg_coverage',
											   '建ぺい率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					if (nn4_param_chk_numeric_max(
							'entry_bldg_coverage',
							'建ぺい率',
							100,
							$nn4_estate_last_err_msg
						)) {
						$entry_bldg_coverage
							= stripslashes(
								$_REQUEST['entry_bldg_coverage']
							);
					} else {
						$entry_bldg_coverage = '';
						$mode = 'input';
					}
				} else {
					$entry_bldg_coverage = '';
					$mode = 'input';
				}
			} else {
				$entry_bldg_coverage = '';
			}

			// 建ぺい率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_bldg_coverage_memo'])) {
					$_REQUEST['entry_bldg_coverage_memo']
						= $nn4_estate_data['bldg_coverage_memo'];
				}
			}
			if (isset($_REQUEST['entry_bldg_coverage_memo'])) {
				$entry_bldg_coverage_memo
					= stripslashes($_REQUEST['entry_bldg_coverage_memo']);
			} else {
				$entry_bldg_coverage_memo = '';
			}

			// 容積率
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio'])) {
					$_REQUEST['entry_plot_ratio']
						= $nn4_estate_data['plot_ratio'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio'])
				&& $_REQUEST['entry_plot_ratio'] != '') {
				if (nn4_param_chk_valid_string('entry_plot_ratio',
											   '容積率',
											   array('numeric'),
											   $nn4_estate_last_err_msg)) {
					$entry_plot_ratio
						= stripslashes($_REQUEST['entry_plot_ratio']);
				} else {
					$entry_plot_ratio = '';
					$mode = 'input';
				}
			} else {
				$entry_plot_ratio = '';
			}

			// 容積率 (補足)
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_plot_ratio_memo'])) {
					$_REQUEST['entry_plot_ratio_memo']
						= $nn4_estate_data['plot_ratio_memo'];
				}
			}
			if (isset($_REQUEST['entry_plot_ratio_memo'])) {
				$entry_plot_ratio_memo
					= stripslashes($_REQUEST['entry_plot_ratio_memo']);
			} else {
				$entry_plot_ratio_memo = '';
			}

			// 地目
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_land_category'])) {
					$_REQUEST['entry_land_category']
						= $nn4_estate_data['land_category'];
				}
			}
			if (isset($_REQUEST['entry_land_category'])) {
				$entry_land_category
					= stripslashes($_REQUEST['entry_land_category']);
			} else {
				$entry_land_category = '';
			}

			// 最適用途
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_preferred_usage'])) {
					$_REQUEST['entry_preferred_usage']
						= $nn4_estate_data['preferred_usage'];
				}
			}
			if (isset($_REQUEST['entry_preferred_usage'])) {
				$entry_preferred_usage
					= stripslashes($_REQUEST['entry_preferred_usage']);
			} else {
				$entry_preferred_usage = '';
			}

			// 用途地域
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_zoning'])) {
					$_REQUEST['entry_zoning'] = $nn4_estate_data['zoning'];
				}
			}
			if (isset($_REQUEST['entry_zoning'])) {
				$entry_zoning
					= stripslashes($_REQUEST['entry_zoning']);
			} else {
				$entry_zoning = '';
			}

			// 都市計画
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_area_control'])) {
					$_REQUEST['entry_area_control']
						= $nn4_estate_data['area_control'];
				}
			}
			if (isset($_REQUEST['entry_area_control'])) {
				$entry_area_control
					= stripslashes($_REQUEST['entry_area_control']);
			} else {
				$entry_area_control = '';
			}

			// 法令上の制限
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_restriction'])) {
					$_REQUEST['entry_restriction']
						= $nn4_estate_data['restriction'];
				}
			}
			if (isset($_REQUEST['entry_restriction'])) {
				$entry_restriction
					= stripslashes($_REQUEST['entry_restriction']);
			} else {
				$entry_restriction = '';
			}

			// 接道状況
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_abutting_road'])) {
					$_REQUEST['entry_abutting_road']
						= $nn4_estate_data['abutting_road'];
				}
			}
			if (isset($_REQUEST['entry_abutting_road'])) {
				$entry_abutting_road
					= stripslashes($_REQUEST['entry_abutting_road']);
			} else {
				$entry_abutting_road = '';
			}

			// 地勢
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_landscape'])) {
					$_REQUEST['entry_landscape']
						= $nn4_estate_data['landscape'];
				}
			}
			if (isset($_REQUEST['entry_landscape'])) {
				$entry_landscape
					= stripslashes($_REQUEST['entry_landscape']);
			} else {
				$entry_landscape = '';
			}

			// 引渡し
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery'])) {
					$_REQUEST['entry_delivery']
						= $nn4_estate_data['delivery'];
				}
			}
			if (isset($_REQUEST['entry_delivery'])) {
				$entry_delivery
					= stripslashes($_REQUEST['entry_delivery']);
			} else {
				$entry_delivery = '';
			}

			// 引渡し条件
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery_cond'])) {
					$_REQUEST['entry_delivery_cond']
						= $nn4_estate_data['delivery_cond'];
				}
			}
			if (isset($_REQUEST['entry_delivery_cond'])) {
				$entry_delivery_cond
					= stripslashes($_REQUEST['entry_delivery_cond']);
			} else {
				$entry_delivery_cond = '';
			}

			// 引渡し日
			if ($mode == 'editinput') {
				if (!isset($_REQUEST['entry_delivery_date'])) {
					$_REQUEST['entry_delivery_date']
						= $nn4_estate_data['delivery_date'];
				}
			}
			if (isset($_REQUEST['entry_delivery_date'])) {
				$entry_delivery_date
					= stripslashes($_REQUEST['entry_delivery_date']);
			} else {
				$entry_delivery_date = '';
			}
		}
	}

	// 種目/モード別の処理
	if ($mode == 'exec' || $mode == 'editexec') {
		// 投稿情報
		if ($mode == 'exec') {
			$post = get_default_post_to_edit('nn4_estate', TRUE);
			$post_ID = $post->ID;
		} else {  // $mode == 'editexec'
			$post_ID = $entry_post_id;
		}

		// APK 取得
		$apk = nn4_auth();
		if (!$apk) {
			$nn4_estate_last_err_msg[] = $nn4_last_err_msg;
			return FALSE;
		}

		$channel_code = 'ancalcu_bukken';
		$entry_aux = json_encode(array('post_id' => $post_ID));
		if (preg_match('/^1/', $entry_type)) {
			// 一戸建てのパラメータ
			$data = array('apk' => $apk,
						  'channel' => $channel_code,
						  'type' => $entry_type,
						  'name' => $entry_name,
						  'fee_unit' => $entry_fee_unit,
						  'published' => $entry_published,
						  'ch_serial' => $entry_serial,
						  'ap_aux' => $entry_aux,
						  'pref_code' => $entry_pref_code,
						  'address1' => $entry_address1,
						  'address2' => $entry_address2,
						  'address_info' => $entry_address_info,
						  'address_memo' => $entry_address_memo,
						  'transport' => $entry_transport,
						  'price' => $entry_price,
						  'price_memo' => $entry_price_memo,
						  'price_max' => $entry_price_max,
						  'flg_new' => $entry_flg_new,
						  'built_on' => $entry_built_on,
						  'room_layout' => $entry_room_layout,
						  'bldg_structure' => $entry_bldg_structure,
						  'num_stairs' => $entry_num_stairs,
						  'num_stairs_memo' => $entry_num_stairs_memo,
						  'parking_space' => $entry_parking_space,
						  'bldg_dim' => $entry_bldg_dim,
						  'bldg_dim_memo' => $entry_bldg_dim_memo,
						  'bldg_dim_max' => $entry_bldg_dim_max,
						  'land_dim' => $entry_land_dim,
						  'land_dim_memo' => $entry_land_dim_memo,
						  'land_dim_max' => $entry_land_dim_max,
						  'pyeong' => $entry_pyeong,
						  'pyeong_memo' => $entry_pyeong_memo,
						  'pyeong_max' => $entry_pyeong_max,
						  'driveway_dim' => $entry_driveway_dim,
						  'driveway_dim_memo' => $entry_driveway_dim_memo,
						  'setback' => $entry_setback,
						  'bldg_coverage' => $entry_bldg_coverage,
						  'bldg_coverage_memo' => $entry_bldg_coverage_memo,
						  'plot_ratio' => $entry_plot_ratio,
						  'plot_ratio_memo' => $entry_plot_ratio_memo,
						  'land_ownership_id' => $entry_land_ownership_id,
						  'land_lease_memo' => $entry_land_lease_memo,
						  'land_category' => $entry_land_category,
						  'zoning' => $entry_zoning,
						  'area_control' => $entry_area_control,
						  'restriction' => $entry_restriction,
						  'mlit_nmbr' => $entry_mlit_nmbr,
						  'abutting_road' => $entry_abutting_road,
						  'landscape' => $entry_landscape,
						  'current_state' => $entry_current_state,
						  'delivery' => $entry_delivery,
						  'delivery_cond' => $entry_delivery_cond,
						  'available_from' => $entry_available_from,
						  'equipments' => $entry_equipments,
						  'special_instr' => $entry_special_instr,
						  'bldg_check_nmbr' => $entry_bldg_check_nmbr,
						  'note' => $entry_note,
						  'agent' => $entry_agent,
						  'image' => $entry_image);
		} else if (preg_match('/^2/', $entry_type)) {
			// マンションのパラメータ
			$data = array('apk' => $apk,
						  'channel' => $channel_code,
						  'type' => $entry_type,
						  'name' => $entry_name,
						  'fee_unit' => $entry_fee_unit,
						  'published' => $entry_published,
						  'ch_serial' => $entry_serial,
						  'ap_aux' => $entry_aux,
						  'pref_code' => $entry_pref_code,
						  'address1' => $entry_address1,
						  'address2' => $entry_address2,
						  'address_info' => $entry_address_info,
						  'address_memo' => $entry_address_memo,
						  'transport' => $entry_transport,
						  'price' => $entry_price,
						  'price_memo' => $entry_price_memo,
						  'price_max' => $entry_price_max,
						  'service_charge' => $entry_service_charge,
						  'maintenance_kitty' => $entry_maintenance_kitty,
						  'flg_new' => $entry_flg_new,
						  'built_on' => $entry_built_on,
						  'room_layout' => $entry_room_layout,
						  'bldg_structure' => $entry_bldg_structure,
						  'floor' => $entry_floor,
						  'family_num' => $entry_family_num,
						  'parking_space' => $entry_parking_space,
						  'bldg_name' => $entry_bldg_name,
						  'management_form' => $entry_management_form,
						  'exclusive_area' => $entry_exclusive_area,
						  'exclusive_area_memo' => $entry_exclusive_area_memo,
						  'balcony_dim' => $entry_balcony_dim,
						  'balcony_dim_memo' => $entry_balcony_dim_memo,
						  'land_ownership_id' => $entry_land_ownership_id,
						  'land_lease_memo' => $entry_land_lease_memo,
						  'mlit_nmbr' => $entry_mlit_nmbr,
						  'current_state' => $entry_current_state,
						  'delivery' => $entry_delivery,
						  'equipments' => $entry_equipments,
						  'special_instr' => $entry_special_instr,
						  'pets' => $entry_pets,
						  'note' => $entry_note,
						  'agent' => $entry_agent,
						  'image' => $entry_image);
		} else if (preg_match('/^3/', $entry_type)) {
			// 土地のパラメータ
			$data = array('apk' => $apk,
						  'channel' => $channel_code,
						  'type' => $entry_type,
						  'name' => $entry_name,
						  'city_block' => $entry_city_block,
						  'block_num' => $entry_block_num,
						  'fee_unit' => $entry_fee_unit,
						  'published' => $entry_published,
						  'ch_serial' => $entry_serial,
						  'ap_aux' => $entry_aux,
						  'pref_code' => $entry_pref_code,
						  'address1' => $entry_address1,
						  'address2' => $entry_address2,
						  'address_info' => $entry_address_info,
						  'address_memo' => $entry_address_memo,
						  'transport' => $entry_transport,
						  'price' => $entry_price,
						  'price_memo' => $entry_price_memo,
						  'price_max' => $entry_price_max,
						  'land_dim' => $entry_land_dim,
						  'land_dim_memo' => $entry_land_dim_memo,
						  'land_dim_max' => $entry_land_dim_max,
						  'pyeong' => $entry_pyeong,
						  'pyeong_memo' => $entry_pyeong_memo,
						  'pyeong_max' => $entry_pyeong_max,
						  'driveway_dim' => $entry_driveway_dim,
						  'driveway_dim_memo' => $entry_driveway_dim_memo,
						  'setback' => $entry_setback,
						  'bldg_coverage' => $entry_bldg_coverage,
						  'bldg_coverage_memo' => $entry_bldg_coverage_memo,
						  'plot_ratio' => $entry_plot_ratio,
						  'plot_ratio_memo' => $entry_plot_ratio_memo,
						  'development_area' => $entry_development_area,
						  'land_ownership_id' => $entry_land_ownership_id,
						  'land_lease_memo' => $entry_land_lease_memo,
						  'land_category' => $entry_land_category,
						  'preferred_usage' => $entry_preferred_usage,
						  'zoning' => $entry_zoning,
						  'area_control' => $entry_area_control,
						  'restriction' => $entry_restriction,
						  'mlit_nmbr' => $entry_mlit_nmbr,
						  'abutting_road' => $entry_abutting_road,
						  'landscape' => $entry_landscape,
						  'current_state' => $entry_current_state,
						  'delivery' => $entry_delivery,
						  'delivery_cond' => $entry_delivery_cond,
						  'delivery_date' => $entry_delivery_date,
						  'equipments' => $entry_equipments,
						  'special_instr' => $entry_special_instr,
						  'note' => $entry_note,
						  'agent' => $entry_agent,
						  'image' => $entry_image);
		} else if (preg_match('/^4/', $entry_type)) {
			// 賃貸住居のパラメータ
			$data = array('apk' => $apk,
						  'channel' => $channel_code,
						  'type' => $entry_type,
						  'name' => $entry_name,
						  'fee_unit' => $entry_fee_unit,
						  'published' => $entry_published,
						  'ch_serial' => $entry_serial,
						  'ap_aux' => $entry_aux,
						  'pref_code' => $entry_pref_code,
						  'address1' => $entry_address1,
						  'address2' => $entry_address2,
						  'address_info' => $entry_address_info,
						  'address_memo' => $entry_address_memo,
						  'transport' => $entry_transport,
						  'deposit_rate' => $entry_deposit_rate,
						  'key_money_rate' => $entry_key_money_rate,
						  'agent_charge_rate' => $entry_agent_charge_rate,
						  'amoritization_rate' => $entry_amoritization_rate,
						  'lump_sum' => $entry_lump_sum,
						  'lump_sum_memo' => $entry_lump_sum_memo,
						  'contract_period' => $entry_contract_period,
						  'rent' => $entry_rent,
						  'rent_memo' => $entry_rent_memo,
						  'rent_max' => $entry_rent_max,
						  'common_area_charge' => $entry_common_area_charge,
						  'service_charge' => $entry_service_charge,
						  'renewal_charge_rate' => $entry_renewal_charge_rate,
						  'insurance' => $entry_insurance,
						  'built_on' => $entry_built_on,
						  'room_layout' => $entry_room_layout,
						  'floor_space' => $entry_floor_space,
						  'floor_space_memo' => $entry_floor_space_memo,
						  'bldg_structure' => $entry_bldg_structure,
						  'floor' => $entry_floor,
						  'parking_space' => $entry_parking_space,
						  'bldg_name' => $entry_bldg_name,
						  'current_state' => $entry_current_state,
						  'available_from' => $entry_available_from,
						  'equipments' => $entry_equipments,
						  'special_instr' => $entry_special_instr,
						  'pets' => $entry_pets,
						  'note' => $entry_note,
						  'agent' => $entry_agent,
						  'image' => $entry_image);
		} else {  // preg_match('/^5/', $entry_type)
			// 賃貸土地のパラメータ
			$data = array('apk' => $apk,
						  'channel' => $channel_code,
						  'type' => $entry_type,
						  'name' => $entry_name,
						  'city_block' => $entry_city_block,
						  'block_num' => $entry_block_num,
						  'fee_unit' => $entry_fee_unit,
						  'published' => $entry_published,
						  'ch_serial' => $entry_serial,
						  'ap_aux' => $entry_aux,
						  'pref_code' => $entry_pref_code,
						  'address1' => $entry_address1,
						  'address2' => $entry_address2,
						  'address_info' => $entry_address_info,
						  'address_memo' => $entry_address_memo,
						  'transport' => $entry_transport,
						  'deposit_rate' => $entry_deposit_rate,
						  'key_money_rate' => $entry_key_money_rate,
						  'agent_charge_rate' => $entry_agent_charge_rate,
						  'amoritization_rate' => $entry_amoritization_rate,
						  'lump_sum' => $entry_lump_sum,
						  'lump_sum_memo' => $entry_lump_sum_memo,
						  'contract_period' => $entry_contract_period,
						  'rent' => $entry_rent,
						  'rent_memo' => $entry_rent_memo,
						  'rent_max' => $entry_rent_max,
						  'service_charge' => $entry_service_charge,
						  'renewal_charge_rate' => $entry_renewal_charge_rate,
						  'insurance' => $entry_insurance,
						  'land_dim' => $entry_land_dim,
						  'land_dim_memo' => $entry_land_dim_memo,
						  'land_dim_max' => $entry_land_dim_max,
						  'pyeong' => $entry_pyeong,
						  'pyeong_memo' => $entry_pyeong_memo,
						  'pyeong_max' => $entry_pyeong_max,
						  'driveway_dim' => $entry_driveway_dim,
						  'driveway_dim_memo' => $entry_driveway_dim_memo,
						  'setback' => $entry_setback,
						  'bldg_coverage' => $entry_bldg_coverage,
						  'bldg_coverage_memo' => $entry_bldg_coverage_memo,
						  'plot_ratio' => $entry_plot_ratio,
						  'plot_ratio_memo' => $entry_plot_ratio_memo,
						  'land_category' => $entry_land_category,
						  'preferred_usage' => $entry_preferred_usage,
						  'zoning' => $entry_zoning,
						  'area_control' => $entry_area_control,
						  'restriction' => $entry_restriction,
						  'abutting_road' => $entry_abutting_road,
						  'landscape' => $entry_landscape,
						  'current_state' => $entry_current_state,
						  'delivery' => $entry_delivery,
						  'delivery_cond' => $entry_delivery_cond,
						  'delivery_date' => $entry_delivery_date,
						  'equipments' => $entry_equipments,
						  'special_instr' => $entry_special_instr,
						  'note' => $entry_note,
						  'agent' => $entry_agent,
						  'image' => $entry_image);
		}

		$result = FALSE;
		for ($i = 0; !$result && $i < 2; $i++) {
			/* エラーがあっても最大2回試行 */
			if ($mode == 'exec') {
				$ret = nn4_post('estate/add', $data);
			} else {
				$data['id'] = $entry_id;
				$ret = nn4_post('estate/update', $data);
			}
			if (!$ret) {
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('Add API failed.');
				continue;
			}
			$r = json_decode($ret, TRUE);
			if (!$r) {
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('API returned invalid data.');
				continue;
			}
			if (!$r['stat']) {
				if ($r['err_reason'] == ERR_REASON_AUTH) {
					// 認証エラーの場合は再接続フラグつきで認証
					$data['apk'] = nn4_auth(TRUE);
				}
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('Add failed', $r);
				continue;
			}
			if ($mode == 'exec') {
				$result = $r['data'];
			} else {
				$result = TRUE;
			}
		}

		if (!$result) {
			$nn4_estate_last_err_msg[] = '登録に失敗しました。';
			require 'nn4-estate-admin-add-error.tmpl';
			exit;
		}

		if ($mode == 'exec') {
			$entry_id = $result['estate_id'];
		}

		// 投稿/更新
		($post_title = $entry_serial)
			|| ($post_title = $entry_id)
			|| ($post_title = $entry_name);
		edit_post(array('post_ID' => $post_ID,
						'post_type' => 'nn4_estate',
						'post_status' => 'publish',
						'post_title' => $post_title));
		if ($mode == 'exec') {
			add_metadata('post', $post_ID, 'nn4_estate_id', $entry_id);
		}
	} else if ($mode == 'delete') {
		// 削除実行
		// APK 取得
		$apk = nn4_auth();
		if (!$apk) {
			$nn4_estate_last_err_msg[] = $nn4_last_err_msg;
			return FALSE;
		}

		$cond = array('apk' => $apk,
					  'id' => $entry_id);
		for ($i = 0; !$result && $i < 2; $i++) {
			/* エラーがあっても最大2回試行 */
			$ret = nn4_post('estate/delete', $cond);
			if (!$ret) {
				$nn4_estate_last_err_msg[] = nn4_errmsg('Delete API failed.');
				continue;
			}
			$r = json_decode($ret, TRUE);
			if (!$r) {
				$nn4_estate_last_err_msg[]
					= nn4_errmsg('API returned invalid data.');
				continue;
			}
			if (!$r['stat']) {
				if ($r['err_reason'] == ERR_REASON_AUTH) {
					// 認証エラーの場合は再接続フラグつきで認証
					$cond['apk'] = nn4_auth(TRUE);
				}
				$nn4_estate_last_err_msg[] = nn4_errmsg('Delete failed', $r);
				continue;
			}
			$result = TRUE;
		}

		// WordPress からの削除
		wp_delete_post($entry_post_id, TRUE);
	}

	// 画面表示
	$chktoken = nn4_chktoken_gen();
	if ($mode == 'typesel') {
		require 'nn4-estate-admin-add-typesel.tmpl';
	} else if ($mode == 'input' || $mode == 'editinput') {
		$entry_image_key_format = get_option('nn4_estate_image_key_format');
		if (!$entry_image_key_format) {
			$entry_image_key_format = NN4_ESATE_DEFAULT_IMAGE_KEY_FORMAT;
		}
		if (preg_match('/^1/', $entry_type)) {
			require 'nn4-estate-admin-add-1-input.tmpl';
		} else if (preg_match('/^2/', $entry_type)) {
			require 'nn4-estate-admin-add-2-input.tmpl';
		} else if (preg_match('/^3/', $entry_type)) {
			require 'nn4-estate-admin-add-3-input.tmpl';
		} else if (preg_match('/^4/', $entry_type)) {
			require 'nn4-estate-admin-add-4-input.tmpl';
		} else {  // preg_match('/^5/', $entry_type)
			require 'nn4-estate-admin-add-5-input.tmpl';
		}
	} else if ($mode == 'confirm' || $mode == 'editconfirm') {
		if (preg_match('/^1/', $entry_type)) {
			require 'nn4-estate-admin-add-1-confirm.tmpl';
		} else if (preg_match('/^2/', $entry_type)) {
			require 'nn4-estate-admin-add-2-confirm.tmpl';
		} else if (preg_match('/^3/', $entry_type)) {
			require 'nn4-estate-admin-add-3-confirm.tmpl';
		} else if (preg_match('/^4/', $entry_type)) {
			require 'nn4-estate-admin-add-4-confirm.tmpl';
		} else {  // preg_match('/^5/', $entry_type)
			require 'nn4-estate-admin-add-5-confirm.tmpl';
		}
	} else if ($mode == 'exec' || $mode == 'editexec') {
		require 'nn4-estate-admin-add-exec.tmpl';
	} else {  // $mode == 'delete'
		require 'nn4-estate-admin-add-delete.tmpl';
	}
}

function nn4_estate_admin_actionlog()
{
	echo '<div>(準備中)</div>';
}

function nn4_estate_admin_sync()
{
	if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'update') {
		nn4_estate_sync();
		$flg_updated = TRUE;
	} else {
		$flg_updated = FALSE;
	}

	require 'nn4-estate-admin-sync.tmpl';
}

function nn4_estate_admin_settings()
{
	$entry_image_key_format = get_option('nn4_estate_image_key_format');
	if (!$entry_image_key_format) {
		$entry_image_key_format = NN4_ESATE_DEFAULT_IMAGE_KEY_FORMAT;
	}

	require 'nn4-estate-admin-settings.tmpl';
}

/****************************************************************
 * テンプレート用関数
 ****************************************************************/
/**
 * 不動産情報を検索する
 */
function nn4_estate_search($arr_cond)
{
	global $nn4_last_err_msg;
	global $nn4_estate_last_err_msg;
	global $nn4_estate_num_total;

	// APK 取得
	$apk = nn4_auth();
	if (!$apk) {
		$nn4_estate_last_err_msg = $nn4_last_err_msg;
		return FALSE;
	}

	// 検索実行
	$result = FALSE;
	for ($i = 0; !$result && $i < 2; $i++) {  // エラーがあっても最大2回試行
		$nn4_estate_last_err_msg = '';

		$arr_cond['apk'] = $apk;
		$arr_cond['channel'] = 'ancalcu_bukken';
		$arr_cond['req_num_total'] = 1;
		if (!isset($arr_cond['offset'])
			|| !is_numeric($arr_cond['offset'])) {
			$arr_cond['offset'] = 0;
		}
		if (!isset($arr_cond['limit']) || !is_numeric($arr_cond['limit'])) {
			$arr_cond['limit'] = 20;
		}
		$ret = nn4_post('estate/search', $arr_cond);
		if (!$ret) {
			$nn4_estate_last_err_msg = nn4_errmsg('Search API failed.');
			continue;
		}
		$r = json_decode($ret, TRUE);
		if (!$r) {
			$nn4_estate_last_err_msg
				= nn4_errmsg('API returned invalid data.');
			continue;
		}
		if (!$r['stat']) {
			if ($r['err_reason'] == ERR_REASON_AUTH) {
				// 認証エラーの場合は再接続フラグつきで認証
				$apk = nn4_auth(TRUE);
			}
			$nn4_estate_last_err_msg = nn4_errmsg('Search failed', $r);
			continue;
		}
		$nn4_estate_num_total = $r['data']['num_total'];
		$result = json_decode($r['data']['result'], TRUE);
	}

	return $result;
}

/**
 * get_posts() に置き換わるもの
 */
function get_nn4_estate_posts($args)
{
	$posts = array();
	foreach (nn4_estate_search($args) as $post) {
		$obj_post = WP_Post::get_instance($post['ap_aux']['post_id']);
		foreach ($post as $k => $v) {
			$obj_post->$k = $v;
		}
		$posts[] = $obj_post;
	}

	return $posts;
}

/**
 * ループ内での呼び出しで次の投稿を取得 (the_post() タグに置き換わるもの)
 */
function the_nn4_estate_post()
{
	global $wp_query;
	global $wp_object_cache;
	global $post;

	$wp_query->the_post();
	$arr_cache = $wp_object_cache->cache;
	foreach (nn4_estate_get($post->nn4_estate_id) as $k => $v) {
		$post->$k = $v;
	}
}

/**
 * 現在投稿の物件種目名
 */
function the_nn4_estate_type()
{
	global $post;
	global $nn4_estate_arr_type;

	return $nn4_estate_arr_type[$post->type];
}

/**
 * 現在投稿のカテゴリー
 */
function the_nn4_estate_category()
{
	global $post;
	if (preg_match('/^(.)/', $post->type, $matches)) {
		return $matches[1];
	} else {
		return NULL;
	}
}

/**
 * 現在投稿の画像
 */
function the_nn4_estate_image($image_key)
{
	global $post;

	$apid = get_option('nn4_apid');

	if (isset($post->image) && is_array($post->image)) {
		foreach ($post->image as $image) {
			if (isset($image[$apid . '_aux']['image_key'])
				&& $image[$apid . '_aux']['image_key'] == $image_key) {
				return $image;
			}
		}
	}

	return NULL;
}
 
/**
 * 不動産情報を取得する
 */
function nn4_estate_get($id)
{
	global $nn4_last_err_msg;
	global $nn4_estate_last_err_msg;
	global $nn4_estate_num_total;

	// APK 取得
	$apk = nn4_auth();
	if (!$apk) {
		$nn4_estate_last_err_msg = $nn4_last_err_msg;
		return FALSE;
	}

	// データ取得
	$result = FALSE;
	for ($i = 0; !$result && $i < 2; $i++) {  // エラーがあっても最大2回試行
		$nn4_estate_last_err_msg = '';

		$arr_cond = array();
		$arr_cond['apk'] = $apk;
		$arr_cond['id'] = $id;
		$ret = nn4_post('estate/get', $arr_cond);
		if (!$ret) {
			$nn4_estate_last_err_msg = nn4_errmsg('Get API failed.');
			continue;
		}
		$r = json_decode($ret, TRUE);
		if (!$r) {
			$nn4_estate_last_err_msg = nn4_errmsg('API returned invalid data.');
			continue;
		}
		if (!$r['stat']) {
			if ($r['err_reason'] == ERR_REASON_AUTH) {
				// 認証エラーの場合は再接続フラグつきで認証
				$apk = nn4_auth(TRUE);
			}
			$nn4_estate_last_err_msg = nn4_errmsg('Get failed', $r);
			continue;
		}
		$result = $r['data'];
	}

	return $result;
}
?>
