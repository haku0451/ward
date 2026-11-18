# 性能確認

高負荷検証は環境依存のため、ここでは再現手順と見る指標を固定します。数値の合格線はハードウェアごとに決めてください。

## 準備

- origin（`docker compose up origin`。手順は [DOCKER.md](DOCKER.md)）
- Agent（`:8080`）
- Panel は負荷経路に入れない

```bash
./scripts/loadtest.sh http://127.0.0.1:8080/ localhost
```

攻撃相当のクエリも混ぜます。

```bash
curl -H "Host: localhost" "http://127.0.0.1:8080/?q=<script>alert(1)</script>"
```

## 指標

Agent の `http://127.0.0.1:9090/metrics`（ループバック）:

- `requests`
- `blocked`
- `denied_ip`
- `queue`

あわせて CPU、RSS、origin のレイテンシ、イベントキューの滞留を見ます。キューはサイズ上限と期限で古いイベントを捨てます。再送は指数バックオフ、最大試行後に破棄します。

## 既知の上限

- リクエストボディは `waf.request_body_limit`（既定 12.5MiB）
- ヘッダはサーバの `MaxHeaderBytes`（16KiB）
- Panel へのイベントバッチは最大 500 件
