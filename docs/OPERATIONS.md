# 運用

対応環境: Linux（x86_64 / aarch64）。

プロキシ方式は Agent による HTTP リバースプロキシです。TLS 終端は Agent の `tls.cert_file` / `tls.key_file`、または手前のリバースプロキシで行います。

互換バージョン: Panel と Agent は `0.3.x` 同士で通信します。

## Docker

開発用 Compose の手順は [DOCKER.md](DOCKER.md) です。Panel、Agent、PostgreSQL、ダミー origin をまとめて起動できます。

## 起動

Panel と Agent は別プロセスです。更新・再起動も独立して行えます。

1. PostgreSQL を起動する
2. `ward-panel` で migrate と `php artisan make:admin`
3. `php artisan ward:generate-signing-key`（本番必須）
4. 保護リストに管理用 IP を入れる
5. ノードを作成し、表示された `agent.yaml` をコピーする
6. Agent ホストで `nano configs/agent.yaml` に貼り付け、Agent を起動する

本番の Panel 通信は HTTPS です。`require_signed_policy: true` と `policy_public_key` を Agent に設定してください。

## バックアップ

- PostgreSQL: `pg_dump -Fc ward > ward.dump`
- Panel: `.env` と `storage/app/private/ward-policy.json`（署名鍵）
- Agent: `data_dir/last-good-config.json` とイベントキュー

復元は `pg_restore` のあと Panel / Agent を再起動します。鍵を失うと新しい版を署名できません。再生成した公開鍵を Agent に配ってください。

## 誤遮断からの緊急復旧

1. 保護リストに自分の IP を追加する（Panel に入れる前でも、コンソールから）
2. `cd ward-panel && php artisan ward:emergency-unblock`
3. 特定 IP だけ戻す場合は `php artisan ward:emergency-unblock 203.0.113.10`
4. Agent は次のハートビートで新しい版を取り込みます。Panel 不通時は Agent の `last-good-config.json` から該当 CIDR を削除して Agent を再起動します。

## ログとメトリクス

- Panel: Laravel の構造化ログ。トークン・署名秘密鍵は出力しません。
- Agent: JSON ログ（stdout）。管理面 `127.0.0.1:9090/healthz` と `/metrics` はループバック専用です。
- 監査: Panel の「監査」画面。

## ポリシー優先順位

サイト別 → ノード別 → グローバル。自動遮断は初期無効です。共有 NAT を巻き込むため、検知のみで評価してから有効化してください。

## WebSocket

Phase 3 でも WebSocket は検査対象外で `501` を返します。
