# アップデート

Panel、Agent、PostgreSQL は別々に更新できます。先に Panel を上げ、Agent は互換メジャーが同じうちに追従します。

## Panel

1. コードを取得する
2. `composer install`
3. `php artisan migrate`
4. `npm ci && npm run build`
5. php-fpm / キューワーカーを再起動する

署名鍵はそのまま使います。再生成すると Agent の `policy_public_key` も更新が必要です。

## Agent

1. 新しいバイナリをビルドする（`scripts/install-agent.sh`）
2. `systemctl restart ward-agent`
3. 起動直後は last-good 設定で防御し、Panel から新しい版を取り込みます

適用に失敗した版は破棄し、直前の正常設定へ戻します。古い版番号の再送は拒否します。ロールバックは Panel が新しい版番号で再署名したペイロードとして配布されます。

## Docker

```bash
docker compose build panel agent
docker compose up -d panel agent
```

詳細は [DOCKER.md](DOCKER.md) です。
