<?php

namespace App\Models;

use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'invite_code',
        'creator_id',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Family $family) {
            if (empty($family->invite_code)) {
                $family->invite_code = Str::upper(Str::random(12));
            }
        });
    }

    /** @param Builder<Family> $query */
    public function scopeEditableBy(Builder $query, int $userId): void
    {
        // Keep mutable administration grants inside the final database write.
        //
        // MySQL refuses to modify a table that a subquery of the same statement
        // selects (error 1093), which breaks the roster mutations that update
        // family_members while this scope reads it. The administrator grant is
        // therefore read from a materialized derived table (GROUP BY prevents
        // MySQL from merging it back into the outer statement), so the write and
        // its authorization predicate remain a single statement on every driver.
        $administrators = DB::query()->selectRaw('1')
            ->fromSub(
                DB::table('family_members')
                    ->select('family_id')
                    ->where('user_id', $userId)
                    ->where('role', 'ADMIN')
                    ->groupBy('family_id'),
                'administrators'
            )
            ->whereColumn('administrators.family_id', 'families.id');

        $query->where('families.is_archived', false)
            ->where(function (Builder $family) use ($userId, $administrators): void {
                $family->where('families.creator_id', $userId)
                    ->orWhereExists($administrators);
            });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return HasMany<FamilyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<Wishlist, $this> */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
}
