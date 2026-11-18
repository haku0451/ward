<?php

namespace App\Models;

use Database\Factories\IpProtectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $cidr
 * @property string|null $reason
 * @property int|null $created_by
 */
#[Fillable(['cidr', 'reason', 'created_by'])]
class IpProtection extends Model
{
    /** @use HasFactory<IpProtectionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
