<?php  // -*- tab-width: 4; c-basic-offset: 4; coding: utf-8; -*-
  /**
   * Plugin Name: NN4
   * Plugin URI: http://dev.nn4.jp/wordpress/plugins/
   * Description: NN4 は、NN4 情報エンジンから API 経由で各種情報を取得・管理するプラグインです。
   * Version: 1.0.0
   * Author: Y&Green
   * License: GPL2
   */

  /*  Copyright 2014  Keisuke Ishikawa  (email : k-ishik@tan9.net)

	  This program is free software; you can redistribute it and/or modify
	  it under the terms of the GNU General Public License, version 2, as 
	  published by the Free Software Foundation.

	  This program is distributed in the hope that it will be useful,
	  but WITHOUT ANY WARRANTY; without even the implied warranty of
	  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	  GNU General Public License for more details.

	  You should have received a copy of the GNU General Public License
	  along with this program; if not, write to the Free Software
	  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  */

defined('ABSPATH') or die("Hello?");

require_once 'nn4-estate.php';
require_once 'nn4-location.php';
require_once 'nn4-event.php';

// 定数定義
@include_once 'site_config.inc';

if (!defined('NN4_BASE_URL')) {
	define('NN4_BASE_URL', 'https://nn4.jp/');
}

if (!defined('NN4_CACHE_DIR')) {
	define('NN4_CACHE_DIR', plugin_dir_path(__FILE__) . "cache/");
}

if (!defined('NN4_CACHE_APK_FNAME')) {
	define('NN4_CACHE_APK_FNAME', NN4_CACHE_DIR . "apk");
}

define('ERR_REASON_OK',        0);
define('ERR_REASON_AUTH',      1);
define('ERR_REASON_NONPOST',   2);
define('ERR_REASON_PARAM',     3);
define('ERR_REASON_DB_INSERT', 4);
define('ERR_REASON_DB_SELECT', 5);
define('ERR_REASON_DB_UPDATE', 6);
define('ERR_REASON_DB_DELETE', 7);

// 都道府県一覧
$nn4_arr_pref = array(
	'01' => '北海道',
	'02' => '青森県',
	'03' => '岩手県',
	'04' => '宮城県',
	'05' => '秋田県',
	'06' => '山形県',
	'07' => '福島県',
	'08' => '茨城県',
	'09' => '栃木県',
	'10' => '群馬県',
	'11' => '埼玉県',
	'12' => '千葉県',
	'13' => '東京都',
	'14' => '神奈川県',
	'15' => '新潟県',
	'16' => '富山県',
	'17' => '石川県',
	'18' => '福井県',
	'19' => '山梨県',
	'20' => '長野県',
	'21' => '岐阜県',
	'22' => '静岡県',
	'23' => '愛知県',
	'24' => '三重県',
	'25' => '滋賀県',
	'26' => '京都府',
	'27' => '大阪府',
	'28' => '兵庫県',
	'29' => '奈良県',
	'30' => '和歌山県',
	'31' => '鳥取県',
	'32' => '島根県',
	'33' => '岡山県',
	'34' => '広島県',
	'35' => '山口県',
	'36' => '徳島県',
	'37' => '香川県',
	'38' => '愛媛県',
	'39' => '高知県',
	'40' => '福岡県',
	'41' => '佐賀県',
	'42' => '長崎県',
	'43' => '熊本県',
	'44' => '大分県',
	'45' => '宮崎県',
	'46' => '鹿児島県',
	'47' => '沖縄県',
);

// 鉄道駅一覧
$nn4_arr_station = array(
	"53a6fcf369d57c6451d88fd8" => '姫宮',
	"53a6fcff69d57c6451d88fd9" => '東武動物公園',
	"53a6fd0b69d57c6451d88fda" => '和戸',
);

// 初期化時/終了時のセッション処理
add_action('init', 'nn4_session_start');
add_action('shutdown', 'nn4_session_end');
add_action('init', 'nn4_estate_init');

// 管理メニューの追加
add_action('admin_menu', 'nn4_menu');
add_action('admin_menu', 'nn4_estate_menu');
#add_action('admin_menu', 'nn4_location_menu');
#add_action('admin_menu', 'nn4_event_menu');

/****************************************************************
 * セッション関数
 ****************************************************************/
/**
 * セッション開始
 */
function nn4_session_start()
{
	@session_start();
	ini_set('display_erros', 1);
}

/**
 * セッション終了
 */
function nn4_session_end()
{
	@session_write_close();
}

/****************************************************************
 * 管理メニュー
 ****************************************************************/
/**
 * 管理メニュー
 */
function nn4_menu()
{
	add_menu_page('NN4 設定', 'NN4 設定',
				  'install_plugins',
				  'nn4');
	add_submenu_page('nn4',
					 'NN4 AP アカウント設定', 'AP アカウント設定',
					 'edit_plugins',
					 'nn4', 'nn4_ap_account');
	add_submenu_page('nn4',
					 'NN4 機能選択', '機能選択',
					 'activate_plugins',
					 'nn4-select-func', 'nn4_select_func');
}

/**
 * APアカウント設定画面
 */
function nn4_ap_account()
{
	$apid = get_option('nn4_apid');
	$appw = get_option('nn4_appw');

	require 'nn4-ap-account.tmpl';
}

/**
 * 機能選択
 */
function nn4_select_func()
{
	require 'nn4-select-func.tmpl';
}

/****************************************************************
 * パラメータ検証
 ****************************************************************/
/**
 * チェックトークン生成
 */
function nn4_chktoken_gen()
{
	$chktoken = md5(uniqid());
	$_SESSION['chktoken'] = $chktoken;

	return $chktoken;
}

/**
 * チェックトークン照合
 */
function nn4_chktoken_check($token_name)
{
	if (!isset($_REQUEST[$token_name]) || $_REQUEST[$token_name] == '') {
		return FALSE;
	}

	return $_REQUEST[$token_name] == $_SESSION['chktoken'];
}

/**
 * 必須
 */
function nn4_param_chk_required($name, $label, &$arr_errmsg)
{
	if (isset($_REQUEST[$name]) && $_REQUEST[$name] !== '') {
		return TRUE;
	} else {
		$arr_errmsg[] = "{$label}が指定されていません。";
		return FALSE;
	}
}

/**
 * 含まれる
 */
function nn4_param_chk_match_collection($name, $label, $collection, &$arr_errmsg)
{
	if (isset($_REQUEST[$name]) && $_REQUEST[$name] != '') {
		if (in_array($_REQUEST[$name], $collection)) {
			return TRUE;
		} else {
			$arr_errmsg[] = "{$label}が正しくありません。";
			return FALSE;
		}
	} else {
		return TRUE;
	}
}

/**
 * 数値最小値
 */
function nn4_param_chk_numeric_min($name, $label, $min_val, &$arr_errmsg)
{
	if (isset($_REQUEST[$name]) && $_REQUEST[$name] != '') {
		if ($_REQUEST[$name] >= $min_val) {
			return TRUE;
		} else {
			$arr_errmsg[] = "{$label}は{$min_val}以上の値を指定してください。";
			return FALSE;
		}
	} else {
		return TRUE;
	}
}

/**
 * 数値最大値
 */
function nn4_param_chk_numeric_max($name, $label, $max_val, &$arr_errmsg)
{
	if (isset($_REQUEST[$name]) && $_REQUEST[$name] != '') {
		if ($_REQUEST[$name] <= $max_val) {
			return TRUE;
		} else {
			$arr_errmsg[] = "{$label}は{$max_val}以下の値を指定してください。";
			return FALSE;
		}
	} else {
		return TRUE;
	}
}

/**
 * 文字列評価
 *
 * $name   string  パラメータ
 * $label  string  パラメータの名称
 * $flags  array   以下の評価フラグを格納した配列
 *                   alpha:           アルファベットを許可
 *                   uppercase:       alpha とともに指定して大文字を許可
 *                   lowercase:       alpha とともに指定して小文字を許可
 *                   numeric:         数字を許可
 *                   spaces:          空白文字 ( ) を許可
 *                   newlines:        改行文字を許可
 *                   tabs:            タブ文字を許可
 *                   dots:            ドット (.) を許可
 *                   commas:          カンマ (,) を許可
 *                   punctuation:     句読点 (.,!?:;) を許可
 *                   dashes:          ダッシュ (-_) を許可
 *                   singlequotes:    単一引用符を許可
 *                   doublequotes:    二重引用符を許可
 *                   quotes:          単一引用符と二重引用符を許可
 *                   forwardslashes:  スラッシュを許可
 *                   backwardslashes: バックスラッシュを許可
 *                   slashes:         スラッシュとバックスラッシュを許可
 *                   brackets:        カッコ (()[]) を許可
 *                   braces:          波カッコ ({}) を許可
 */
function nn4_param_chk_valid_string($name, $label, $flags, &$arr_errmsg)
{
	if (isset($_REQUEST[$name]) && $_REQUEST[$name] != '') {
		$pattern = '';

		// アルファベット
		if (in_array('alpha', $flags)) {
			$alpha_ptrn = '';
			if (in_array('uppercase', $flags)) {
				$alpha_ptrn .= 'A-Z';
			}
			if (in_array('lowercase', $flags)) {
				$alpha_ptrn .= 'a-z';
			}
			if ($alpha_ptrn == '') {
				$alpha_ptrn = 'A-Za-z';
			}
			$pattern .= $alpha_ptrn;
		}

		// 数字
		if (in_array('numeric', $flags)) {
			$pattern .= '0-9';
		}

		// 空白文字
		if (in_array('spaces', $flags)) {
			$pattern .= ' ';
		}

		// 改行文字
		if (in_array('newlines', $flags)) {
			$pattern .= '\r\n';
		}

		// タブ文字
		if (in_array('tabs', $flags)) {
			$pattern .= '\t';
		}

		// ドット
		if (in_array('dots', $flags)) {
			$pattern .= '\.';
		}

		// カンマ
		if (in_array('commas', $flags)) {
			$pattern .= ',';
		}

		// 句読点
		if (in_array('punctuation', $flags)) {
			$pattern .= '\.,\!\?\:;';
		}

		// ダッシュ
		if (in_array('dashes', $flags)) {
			$pattern .= '\-_';
		}

		// 単一引用符
		if (in_array('singlequotes', $flags)) {
			$pattern .= '\\\'';
		}

		// 二重引用符
		if (in_array('doublequotes', $flags)) {
			$pattern .= '\"';
		}

		// 引用符
		if (in_array('quotes', $flags)) {
			$pattern .= '\\\'\"';
		}

		// スラッシュ
		if (in_array('forwardslashes', $flags)) {
			$pattern .= '/';
		}

		// バックスラッシュ
		if (in_array('backwardslashes', $flags)) {
			$pattern .= '\\\\';
		}

		// スラッシュとバックスラッシュ
		if (in_array('slashes', $flags)) {
			$pattern .= '/\\\\';
		}

		// カッコ
		if (in_array('brackets', $flags)) {
			$pattern .= '\(\)\[\]';
		}

		// 波カッコ
		if (in_array('braces', $flags)) {
			$pattern .= '\{\}';
		}

		if (preg_match('/^[' . $pattern . ']+$/', $_REQUEST[$name])) {
			return TRUE;
		} else {
			$arr_errmsg[] = "{$label}が正しくありません。";
			return FALSE;
		}
	} else {
		return TRUE;
	}
}

/****************************************************************
 * NN4関連関数
 ****************************************************************/
/**
 * NN4 サーバーとの認証処理
 *
 * @param  bool   $flg_reconnect  再接続フラグ
 * @return string $apk            アクセスコード
 */
function nn4_auth($flg_reconnect = FALSE)
{
	global $nn4_last_err_msg;

	if ($flg_reconnect) {
		$apk = NULL;
	} else {
		$apk = nn4_apk_cache_get();
	}

	if (!$apk) {
		$apid = get_option('nn4_apid');
		$appw = get_option('nn4_appw');

		// 認証、APK の取得
		$ret = nn4_post('auth/login',
						array('apid' => $apid, 'appw' => $appw));
		if (!$ret) {
			$nn4_last_err_msg = nn4_errmsg('Auth login API failed.');
			return FALSE;
		}
		$r = json_decode($ret, TRUE);
		if (!$r) {
			$nn4_last_err_msg = nn4_errmsg('API returned invalid data.');
			return FALSE;
		}
		if (!$r['stat']) {
			$nn4_last_err_msg = nn4_errmsg('Auth failed.', $r);
			return FALSE;
		}
		$apk = $r['data']['apk'];

		// APK をキャッシュに保存
		nn4_apk_cache_set($apk);

		// タイムゾーンの設定
		$ret = nn4_post('time_zone/set',
						array('apk' => $apk,
							  'time_zone' => 'Asia/Tokyo'));
		if (!$ret) {
			$nn4_last_err_msg = nn4_errmsg('Timezone set API failed.');
			return FALSE;
		}
		$r = json_decode($ret, TRUE);
		if (!$r) {
			$nn4_last_err_msg = nn4_errmsg('API returned invalid data.');
			return FALSE;
		}
		if (!$r['stat']) {
			$nn4_last_err_msg = nn4_errmsg('TZ set failed.', $r);
			return FALSE;
		}
	}

	return $apk;

}

/**
 * POST でコンテンツ取得
 */
function nn4_post($url, $data)
{
	$content = http_build_query($data);
	$content_length = strlen($content);
	$header = array("Content-Type: application/x-www-form-urlencoded",
					"Content-Length: $content_length");
	$options = array('http' => array('method' => 'POST',
									 'header' => $header,
									 'content' => $content));
	$contents = @file_get_contents(NN4_BASE_URL . $url,
								   FALSE,
								   stream_context_create($options));
	return $contents;
}

/**
 * APK をキャッシュに保存
 */
function nn4_apk_cache_set($apk)
{
	$fh = fopen(NN4_CACHE_APK_FNAME, 'w');
	flock($fh, LOCK_EX);
	fwrite($fh, $apk);
	fclose($fh);
}

/**
 * APK をキャッシュから取得
 */
function nn4_apk_cache_get()
{
	if ($fh = @fopen(NN4_CACHE_APK_FNAME, 'r')) {
		flock($fh, LOCK_SH);
		$apk = fgets($fh);
		fclose($fh);
		$apk = trim($apk);
	} else {
		$apk = NULL;
	}

	return $apk;
}

/**
 * APK をキャッシュからクリアー
 */
function nn4_apk_cache_clear()
{
	@unlink(NN4_CACHE_APK_FNAME);
}

/**
 * エラーメッセージ生成
 */
function nn4_errmsg($msg, $r = array())
{
	if ($r['err_reason']) {
		$msg .= (' (error code: ' . $r['err_reason']);
		if ($r['err_description']) {
			$msg .= (', ' . $r['err_description']);
		}
		$msg .= ')';
	}

	return $msg;
}

/****************************************************************
 * その他関数
 ****************************************************************/
/**
 * 日時文字列のフォーマット変換
 */
function nn4_dateformat_converter($time, $format)
{
	$ts = strtotime($time);
	if ($ts === FALSE) {
		return FALSE;
	} else {
		return date($format, $ts);
	}
}

/**
 * 画像情報一覧から指定した最大ピクセルサイズを超えない最大サイズの画像を選ぶ
 * 画像情報一覧は、大きなピクセルサイズのものから順に、width, height キーを持つ連想配列の配列を与える
 */
function nn4_choice_max_image($arr_image, $max_width,
							  $max_height = NULL, $max_w_x_h = NULL)
{
	$flg_width_ok = is_null($max_width);
	$flg_height_ok = is_null($max_height);
	$flg_w_x_h_ok = is_null($max_w_x_h);

	foreach ($arr_image as $image) {
		if (!$flg_width_ok && (int)$image['width'] <= (int)$max_width) {
			$flg_width_ok = TRUE;
		}
		if (!$flg_height_ok && (int)$image['height'] <= (int)$max_height) {
			$flg_height_ok = TRUE;
		}
		if (!$flg_w_x_h_ok
			&& $image['width'] * $image['height'] <= (int)$max_w_x_h) {
			$flg_w_x_h_ok = TRUE;
		}

		if ($flg_width_ok && $flg_height_ok && $flg_w_x_h_ok) {
			return $image;
		}
	}
	return NULL;
}

/**
 * 画像情報一覧から指定した最小ピクセルサイズを下回らない最小サイズの画像を選ぶ
 * 画像情報一覧は、大きなピクセルサイズのものから順に、width, height キーを持つ連想配列の配列を与える
 */
function nn4_choice_min_image($arr_image, $min_width,
							  $min_height = NULL, $min_w_x_h = NULL)
{
	$flg_width_ok = is_null($min_width);
	$flg_height_ok = is_null($min_height);
	$flg_w_x_h_ok = is_null($min_w_x_h);

	for ($i = count($arr_image) - 1; $i >= 0; $i--) {
		$image = $arr_image[$i];

		if (!$flg_width_ok && (int)$image['width'] >= (int)$min_width) {
			$flg_width_ok = TRUE;
		}
		if (!$flg_height_ok && (int)$image['height'] >= (int)$min_height) {
			$flg_height_ok = TRUE;
		}
		if (!$flg_w_x_h_ok
			&& $image['width'] * $image['height'] >= (int)$min_w_x_h) {
			$flg_w_x_h_ok = TRUE;
		}

		if ($flg_width_ok && $flg_height_ok && $flg_w_x_h_ok) {
			return $image;
		}
	}
	return NULL;
}
?>
