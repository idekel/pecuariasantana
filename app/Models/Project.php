<?php

namespace App\Models;

use App\Enums\ExpenseType;
use App\Enums\ProjectType;
use App\Exceptions\CannotGrantAccessToOwnerException;
use App\Exceptions\InvalidSaleQuantityException;
use App\Exceptions\InvalidYieldQuantityException;
use App\Exceptions\UnauthorizedProjectActionException;
use App\Notifications\SaleRecordedNotification;
use App\Notifications\YieldRecordedNotification;
use Database\Factories\ProjectFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Users who have been granted access to this project by its owner.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    public function yields(): HasMany
    {
        return $this->hasMany(YieldRecord::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Whether the user is the owner or has been granted access to the project.
     */
    public function hasAccess(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->collaborators()->whereKey($user->id)->exists();
    }

    /**
     * Grant a user access to this project. Only the owner may do this.
     */
    public function grantAccessTo(User $user, User $grantedBy): void
    {
        if (! $this->isOwnedBy($grantedBy)) {
            throw UnauthorizedProjectActionException::grantAccess();
        }

        if ($this->isOwnedBy($user)) {
            throw new CannotGrantAccessToOwnerException;
        }

        $this->collaborators()->syncWithoutDetaching([$user->id]);
    }

    /**
     * Revoke a user's access to this project. Only the owner may do this.
     */
    public function revokeAccessFrom(User $user, User $revokedBy): void
    {
        if (! $this->isOwnedBy($revokedBy)) {
            throw UnauthorizedProjectActionException::revokeAccess();
        }

        $this->collaborators()->detach($user->id);
    }

    /**
     * Record a yield for this project on behalf of a user with access to it.
     */
    public function recordYield(User $user, int|float $quantity, DateTimeInterface|string $producedOn): YieldRecord
    {
        if (! $this->hasAccess($user)) {
            throw UnauthorizedProjectActionException::recordYield();
        }

        if (! $this->type->isValidYieldQuantity($quantity)) {
            throw InvalidYieldQuantityException::forProjectType($this->type, $quantity);
        }

        $yield = $this->yields()->create([
            'user_id' => $user->id,
            'quantity' => $quantity,
            'produced_on' => $producedOn,
        ]);

        $this->owner->notify(new YieldRecordedNotification($this, $yield));

        return $yield;
    }

    /**
     * Update an existing yield belonging to this project, on behalf of a user with access to it.
     */
    public function updateYield(YieldRecord $yield, User $user, int|float $quantity, DateTimeInterface|string $producedOn): YieldRecord
    {
        if (! $this->hasAccess($user)) {
            throw UnauthorizedProjectActionException::manageYields();
        }

        if (! $this->type->isValidYieldQuantity($quantity)) {
            throw InvalidYieldQuantityException::forProjectType($this->type, $quantity);
        }

        $yield->update([
            'quantity' => $quantity,
            'produced_on' => $producedOn,
        ]);

        return $yield;
    }

    /**
     * Delete an existing yield belonging to this project, on behalf of a user with access to it.
     */
    public function deleteYield(YieldRecord $yield, User $user): void
    {
        if (! $this->hasAccess($user)) {
            throw UnauthorizedProjectActionException::manageYields();
        }

        $yield->delete();
    }

    /**
     * Record a sale for this project on behalf of a user with access to it.
     */
    public function recordSale(User $user, int|float $quantity, int|float $amount, DateTimeInterface|string $soldOn): Sale
    {
        if (! $this->hasAccess($user)) {
            throw UnauthorizedProjectActionException::recordSale();
        }

        if (! $this->type->isValidYieldQuantity($quantity)) {
            throw InvalidSaleQuantityException::forProjectType($this->type, $quantity);
        }

        $sale = $this->sales()->create([
            'user_id' => $user->id,
            'quantity' => $quantity,
            'amount' => $amount,
            'sold_on' => $soldOn,
        ]);

        $this->owner->notify(new SaleRecordedNotification($this, $sale));

        return $sale;
    }

    /**
     * Record an expense for this project on behalf of a user with access to it.
     */
    public function recordExpense(User $user, int|float $amount, ExpenseType|string $type, DateTimeInterface|string $incurredOn): Expense
    {
        if (! $this->hasAccess($user)) {
            throw UnauthorizedProjectActionException::recordExpense();
        }

        return $this->expenses()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => $type,
            'incurred_on' => $incurredOn,
        ]);
    }
}
