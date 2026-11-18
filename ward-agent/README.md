# Ward Agent

Coraza と OWASP CRS を組み込んだ WAF リバースプロキシです。Panel から設定を受け取り、攻撃イベントを送り返します。Panel が停止しても最後に成功した設定で防御を継続します。

設定は Ward Panel のノード画面で生成し、`nano configs/agent.yaml` に貼り付けて使います。`configs/agent.example.yaml` は Panel なしの開発用です。systemd ユニットは `deployments/ward-agent.service` を参照してください。
