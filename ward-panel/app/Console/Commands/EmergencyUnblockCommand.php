<?php

namespace App\Console\Commands;

use App\Actions\PublishPolicyRevision;
use App\Models\AuditLog;
use App\Models\IpBlock;
use App\Support\Cidr;
use Illuminate\Console\Command;

class EmergencyUnblockCommand extends Command
{
    protected $signature = 'ward:emergency-unblock {cidr? : CIDR or IP to revoke. Omit to revoke all active blocks.}';

    protected $description = '誤遮断からの緊急復旧として、遮断ルールを解除します';

    public function handle(PublishPolicyRevision $publish): int
    {
        $query = IpBlock::query()->active();
        $cidr = $this->argument('cidr');

        if (is_string($cidr) && $cidr !== '') {
            if (! Cidr::isValid($cidr)) {
                $this->error('A valid IP or CIDR is required.');

                return self::FAILURE;
            }

            $query->where('cidr', Cidr::normalize($cidr));
        }

        $count = 0;

        $query->each(function (IpBlock $block) use (&$count): void {
            $block->forceFill([
                'revoked_at' => now(),
                'revoke_reason' => 'emergency-unblock',
            ])->save();
            $count++;
        });

        if ($count > 0) {
            $publish->handle();
        }

        AuditLog::record('ip_block.emergency_unblocked', null, [
            'count' => $count,
            'cidr' => $cidr,
        ]);

        $this->info("Revoked {$count} active block(s).");

        return self::SUCCESS;
    }
}
