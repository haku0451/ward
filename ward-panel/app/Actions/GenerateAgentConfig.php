<?php

namespace App\Actions;

use App\Models\Node;
use App\Models\Site;
use App\Models\WafSetting;
use App\Support\PolicySigner;

class GenerateAgentConfig
{
    public function __construct(private PolicySigner $signer) {}

    public function handle(Node $node, ?string $plainToken = null): string
    {
        $settings = WafSetting::current();
        $mode = ($node->waf_mode ?? $settings->waf_mode)->value;
        $panelUrl = rtrim((string) config('ward.agent_panel_url', config('app.url')), '/');
        $dataDir = (string) config('ward.agent_data_dir', './data');
        $listen = (string) config('ward.agent_listen', ':8080');
        $adminListen = (string) config('ward.agent_admin_listen', '127.0.0.1:9090');
        $https = str_starts_with($panelUrl, 'https://');
        $token = $plainToken !== null && $plainToken !== ''
            ? $plainToken
            : '${WARD_AGENT_TOKEN}';
        $publicKey = $this->signer->publicKey();
        $skipVerify = $https ? 'false' : 'true';
        $requireSigned = $https ? 'true' : 'false';
        $quotedUrl = $this->quote($panelUrl);
        $quotedToken = $this->quote($token);
        $quotedDataDir = $this->quote($dataDir);
        $quotedListen = $this->quote($listen);
        $quotedAdmin = $this->quote($adminListen);
        $quotedKey = $this->quote($publicKey);
        $queuePath = $this->quote(rtrim($dataDir, '/').'/events.jsonl');

        $sites = Site::query()
            ->where(function ($query) use ($node): void {
                $query->whereNull('node_id')->orWhere('node_id', $node->id);
            })
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $siteBlock = 'sites: []';
        } else {
            $siteBlock = "sites:\n".$sites->map(fn (Site $site): string => sprintf(
                "  - hostname: %s\n    origin_url: %s\n    id: %d",
                $this->quote($site->hostname),
                $this->quote($site->origin_url),
                $site->id,
            ))->implode("\n");
        }

        return <<<YAML
listen: {$quotedListen}
admin_listen: {$quotedAdmin}
data_dir: {$quotedDataDir}

panel:
  url: {$quotedUrl}
  token: {$quotedToken}
  tls_skip_verify: {$skipVerify}
  timeout: 10s
  heartbeat_every: 30s
  require_signed_policy: {$requireSigned}
  policy_public_key: {$quotedKey}

waf:
  mode: {$mode}
  request_body_limit: 13107200
  request_body_timeout: 10s

queue:
  path: {$queuePath}
  max_bytes: 104857600
  max_age: 24h
  max_attempts: 8

tls:
  cert_file: ""
  key_file: ""

trusted_proxies: []

{$siteBlock}

YAML;
    }

    private function quote(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
