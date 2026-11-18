<?php

namespace App\Actions;

use App\Models\Node;
use Illuminate\Support\Str;

class IssueNodeToken
{
    public function handle(Node $node): string
    {
        $plain = Node::generatePlainToken();

        $node->forceFill([
            'token_prefix' => Node::tokenPrefix($plain),
            'token_hash' => Node::hashToken($plain),
        ]);

        if ($node->uuid === null || $node->uuid === '') {
            $node->uuid = (string) Str::uuid();
        }

        $node->save();

        return $plain;
    }
}
