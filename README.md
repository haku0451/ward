# Ward

攻撃検知と遮断状況を可視化し、複数の WAF ノードを管理するオープンソース WAF です。

実装範囲は仕様書の Phase 1〜3 です。mTLS は推奨事項として文書化しており、通信は HTTPS + ノード個別トークン + ポリシー署名です。

## 構成

- `ward-panel`: Laravel 13 + Inertia + React + TypeScript。PHP 8.5。PostgreSQL。
- `ward-agent`: Go。Coraza + OWASP CRS を組み込んだリバースプロキシ。

Panel は HTTP 検査経路に入りません。検査・IP 遮断・オリジンへのプロキシは Agent が行います。Panel と通信できなくても、Agent は最後に成功した設定で防御を続けます。

## Docker

```bash
docker compose up -d --build
docker compose exec panel php artisan make:admin --name="Ward Admin" --email="admin@ward.test" --password="password"
```

http://localhost:8000 。詳細は [docs/DOCKER.md](docs/DOCKER.md) です。

## ローカル動作確認

### 前提

- PHP 8.5、Composer、Node.js
- PostgreSQL 16（または Docker。全スタックは [docs/DOCKER.md](docs/DOCKER.md)）
- Go（`ward-agent/go.mod` のバージョン）

### 1. Panel

```bash
cd ward-panel
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan make:admin --name="Ward Admin" --email="admin@ward.test" --password="password"
npm install
npm run dev
```

別ターミナルで `php artisan serve`。http://localhost:8000 にログインし、保護リストへ管理 IP を入れ、サイト（hostname `localhost`、origin `http://127.0.0.1:9080`）とノードを作成します。作成直後に表示される `agent.yaml` をコピーします。

### 2. オリジンと Agent

```bash
docker compose up origin
cd ward-agent
nano configs/agent.yaml
```

Panel のノード画面からコピーした YAML を貼り付けて保存します。トークンはファイルに入っているので環境変数は不要です。

```bash
go run ./cmd/ward-agent --config configs/agent.yaml
```

```bash
curl -H "Host: localhost" "http://127.0.0.1:8080/?q=<script>alert(1)</script>"
```

Panel のイベント・遮断・ポリシー画面で結果を確認できます。Panel を止めても Agent は last-good 設定で動き続けます。

## Agent API

認証: `Authorization: Bearer <node token>`。汎用コマンド実行はありません。

- `POST /api/agent/v1/heartbeat`
- `POST /api/agent/v1/events`
- `GET /api/agent/v1/config`（Ed25519 署名付き）
- `POST /api/agent/v1/apply-results`

## インストーラとドキュメント

- `scripts/install-panel.sh`
- `scripts/install-agent.sh`
- `scripts/loadtest.sh`
- [docs/OPERATIONS.md](docs/OPERATIONS.md)
- [docs/DOCKER.md](docs/DOCKER.md)
- [docs/UPDATING.md](docs/UPDATING.md)
- [docs/PERFORMANCE.md](docs/PERFORMANCE.md)

本番では `php artisan ward:generate-signing-key` のあと、Agent に `require_signed_policy: true` と公開鍵を設定します。誤遮断時は `php artisan ward:emergency-unblock` です。

## テスト

```bash
cd ward-panel
php artisan test
```
