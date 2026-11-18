<?php

namespace App\Actions;

use App\Enums\ApplyStatus;
use App\Enums\IpBlockSource;
use App\Models\AttackEvent;
use App\Models\IpBlock;
use App\Models\IpProtection;
use App\Models\WafSetting;
use App\Support\Cidr;
use Illuminate\Support\Collection;

class EvaluateAutoBlock
{
    public function __construct(private PublishPolicyRevision $publish) {}

    /**
     * @param  list<string>  $clientIps
     */
    public function handle(array $clientIps): void
    {
        $settings = WafSetting::current();

        if (! $settings->auto_block_enabled) {
            return;
        }

        $uniqueIps = array_values(array_unique(array_filter($clientIps)));

        if ($uniqueIps === []) {
            return;
        }

        $windowStart = now()->subSeconds($settings->auto_block_window_seconds);
        $protections = IpProtection::query()->get();
        $changed = false;

        foreach ($uniqueIps as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }

            if ($this->isProtected($ip, $protections)) {
                continue;
            }

            $already = IpBlock::query()
                ->active()
                ->where('cidr', Cidr::normalize($ip))
                ->exists();

            if ($already) {
                continue;
            }

            $count = AttackEvent::query()
                ->where('client_ip', $ip)
                ->where('occurred_at', '>=', $windowStart)
                ->count();

            if ($count < $settings->auto_block_threshold) {
                continue;
            }

            IpBlock::query()->create([
                'cidr' => Cidr::normalize($ip),
                'source' => IpBlockSource::Automatic,
                'reason' => 'Automatic block after '.$count.' events in window',
                'apply_status' => ApplyStatus::Pending,
                'expires_at' => now()->addSeconds($settings->auto_block_duration_seconds),
            ]);

            $changed = true;
        }

        if ($changed) {
            $this->publish->handle();
        }
    }

    /**
     * @param  Collection<int, IpProtection>  $protections
     */
    private function isProtected(string $ip, Collection $protections): bool
    {
        foreach ($protections as $protection) {
            if (Cidr::containsIp($protection->cidr, $ip)) {
                return true;
            }
        }

        return false;
    }
}
