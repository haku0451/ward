<?php

namespace App\Console\Commands;

use App\Support\PolicySigner;
use Illuminate\Console\Command;

class GenerateSigningKeyCommand extends Command
{
    protected $signature = 'ward:generate-signing-key {--force : Overwrite an existing key file}';

    protected $description = 'Ed25519 のポリシー署名鍵を生成します';

    public function handle(PolicySigner $signer): int
    {
        $path = (string) config('ward.policy_key_path');

        if (is_file($path) && ! $this->option('force')) {
            $this->error("Key file already exists: {$path}");

            return self::FAILURE;
        }

        $keys = $signer->generateAndStore($path);
        $this->info("Wrote signing keys to {$path}");
        $this->line('Public key: '.$keys['public']);
        $this->comment('Keep the private key out of git, images, and logs.');

        return self::SUCCESS;
    }
}
