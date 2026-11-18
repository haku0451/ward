# Docker

リポジトリ直下の `docker-compose.yml` で Panel、Agent、PostgreSQL、ダミー origin を起動できます。本番の秘密情報はイメージに含めません。

| サービス | 役割 | ホスト側ポート |
| --- | --- | --- |
| `postgres` | PostgreSQL 16 | `5433`（コンテナ内は `5432`） |
| `panel` | Ward Panel（Laravel） | `8000` |
| `origin` | 保護対象のダミー（nginx） | `9080` |
| `agent` | WAF Agent | `8080` |

## 前提

- Docker Compose v2
- 初回の Panel イメージは PHP・Node のビルドがあるため時間がかかります

## 起動

ホストで `php artisan serve` を動かしている場合は止めてください。`8000` がぶつかります。ローカル PostgreSQL が `5432` を使っていても問題ありません。Compose の DB はホストの **5433** です。コンテナ同士はサービス名 `postgres:5432` で話します。

```bash
podman-compose down
podman-compose up -d --build
```

ブラウザは **http://localhost:8000** です。管理者はコンテナ内で作ります。

```bash
docker compose exec panel php artisan make:admin --name="Ward Admin" --email="admin@ward.test" --password="password"
```

起動時に作る場合は、リポジトリ直下の `.env`（Compose 用）に次を置きます。

```env
WARD_ADMIN_EMAIL=admin@ward.test
WARD_ADMIN_PASSWORD=password
```

パスワードが Laravel の強度ルールを満たさないと作成に失敗します。そのときは `exec` で作り直してください。

### サイトとノード（オフラインのとき）

Agent コンテナから `127.0.0.1:8000` は Panel ではありません。Compose では `http://panel:8000` を使います（`configs/agent.docker.yaml`）。トークンが空でもハートビートできず **オフライン** のままです。

1. http://localhost:8000 でノードを作成し、表示されたトークン（`ward_....`）をコピーする
2. リポジトリ直下に `.env` を置く

```env
WARD_AGENT_TOKEN=ward_ここにコピーしたトークン
```

3. Agent だけ作り直す

```bash
podman-compose up -d --force-recreate agent
```

数十秒でノードがオンラインになります。サイトを作る場合、hostname は `localhost`、origin は **`http://origin`** です。

検査:

```bash
curl -H "Host: localhost" "http://127.0.0.1:8080/?q=<script>alert(1)</script>"
```

イベントは Panel の画面で確認します。

## ホストで Panel / Agent を動かす

DB と origin だけ使う場合:

```bash
docker compose up -d postgres origin
```

`ward-panel/.env` は次です。サイト origin は `http://127.0.0.1:9080`。生成 YAML の `panel.url` は `APP_URL`（通常 `http://localhost:8000`）、`data_dir` は `./data` です。`WARD_AGENT_PANEL_URL` は設定しません。

```env
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=ward
DB_USERNAME=ward
DB_PASSWORD=ward
```

## ポートと管理面

- `8000`: Panel
- `8080`: Agent の検査用プロキシ
- `9080`: ダミー origin（ホストから。Agent コンテナからは `http://origin`）
- `5433`: PostgreSQL（ホストから。コンテナ内 Panel は `postgres:5432`）
- Agent の `/healthz` と `/metrics` は `admin_listen`（既定 `127.0.0.1:9090`）のため、コンテナ外からは見えません

Panel の署名鍵とセッションはボリューム `ward-panel-storage` に残ります。Agent の last-good とキューは `ward-agent-data`（`/data`）です。

## 更新

```bash
docker compose build panel agent
docker compose up -d panel agent
```

PostgreSQL は `ward-postgres` に残るので、起動後に Panel が migrate します。

```bash
docker compose down
# データも消す場合のみ
docker compose down -v
```

## 単体ビルド

```bash
docker build -t ward-panel ./ward-panel
docker build -t ward-agent ./ward-agent
```
