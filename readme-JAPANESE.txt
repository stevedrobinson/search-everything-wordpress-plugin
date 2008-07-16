Search Everything (SE) v4.7 - 検索対象にするコンテンツを追加するプラグイン
GNU General Public License
Developed by Daniel Cameron http://dancameron.org/
Translation by Naoko McCracken http://detlog.org/
対応テスト済みのWordPressバージョン: 2.1〜2.6（日本語タグ未対応）

このプラグインの翻訳版配布ページ： http://wppluginsj.sourceforge.jp/i18n-ja_jp/search-everything/
このプラグインに関する追加情報（英語）： http://wordpress.org/extend/plugins/search-everything/

◆はじめに
------------------------
デフォルトの状態のWordPressでは、公開したと投稿の本文のみが検索されます。このプラグインを使って、検索対象にするコンテンツを追加することができます。

 - ページ作成機能で追加した全ページ
 - パスワード保護されていない全ページ
 - タグ（ただし現時点では日本語はうまく行かないようです）
 - すべてのコメント
 - 承認済みのコメントのみ
 - 未公開の草稿記事
 - 投稿の要約
 - アタッチメント（記事内で使用中のアップロードファイル）
 - カスタムフィールドで追加した記事のメタデータ
 - カテゴリー
 - ID指定した以外の全ページ
 - ID指定した以外の全カテゴリー

◆インストール
------------------------
1. ZIPファイルを解凍します。

2. search-everythingフォルダをそのままプラグインディレクトリ（/wp-content/plugins/）に移動します。

- wp-content
	- plugins
	  - search-everything
	  	  | search_everything.php
	  	  | SE-Admin.php
		  - lang
		     | SE4-ja.mo

2. プラグイン管理画面にログインし、「Search Everything」を有効化します。

3. 「設定」メニューのサブメニューとして現れる、「Search Everything」管理パネルへ移動し、設定を変更します。検索したいコンテンツにチェックを入れ、「設定を更新」ボタンをクリックします。