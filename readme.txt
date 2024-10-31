=== NN4 ===
Contributors: Keisuke Ishikawa
Tags: curation
Requires at least: 3.0.1
Tested up to: 3.9.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

NN4 は、NN4 情報エンジンから API 経由で各種情報を取得・管理するプラグインです。

== Description ==
NN4 情報配信エンジンからの情報取得、エンジン内の情報の管理を行うためのプラグインです。

NN4 プラグインは、以下の情報を管理できます。

* 不動産情報 (nn4_estate)

投稿した不動産情報は、カスタム投稿タイプ nn4_estate として投稿されます。

NN4 プラグインを利用するためには、事前に AP (アプリケーション・プロバイダー) 登録が必要です。
AP 登録については NN4 担当者 (info@nn4.jp) までご連絡ください。

== Installation ==
1. 事前にAP登録を行います。(問い合わせ先: info@nn4.jp)
1. `nn4` ディレクトリー全体を `/wp-content/plugins/` ディレクトリーにアップロードします。
1. WordPress の「プラグイン」メニューで NN4 を有効化します。
1. WordPress の「NN4 設定」の「AP アカウント設定」メニューで、AP アカウントとパスワードを設定します。
1. WordPress の「NN4 不動産情報」の「サーバー同期」メニューで、NN4 サーバーとの同期を実行します。


== Frequently Asked Questions ==
= NN4 を利用すると何ができますか？ =

NN4 は複数のチャネル (情報源) から情報を収集・整理して再配信する、情報再配信エンジンです。

収集する情報の種類は、不動産情報、イベント情報、施設情報、動画情報、ショッピング情報などです。

= AP とは何ですか？ =

AP (= Application Provider) とは、NN4 エンジンが配信する情報を利用して、アプリケーションを開発・運営する業者や個人を指します。

AP としてアプリケーションを運営するには、NN4 への AP 登録が必要です。

== Changelog ==
= 1.0.0 =
* 正式リリース

= 0.1 =
* テストリリース
