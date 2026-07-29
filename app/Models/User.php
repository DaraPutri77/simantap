<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'employee_number',
        'name',
        'email',
        'phone',
        'work_unit',
        'position',
        'status',
        'password',
        'must_change_password',
        'email_verified_at',
        'activated_at',
        'password_changed_at',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Active->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function activationToken(): HasOne
    {
        return $this->hasOne(AccountActivationToken::class);
    }

    public function createdActivationTokens(): HasMany
    {
        return $this->hasMany(AccountActivationToken::class, 'created_by');
    }

    public function createdInventoryReceipts(): HasMany
    {
        return $this->hasMany(InventoryReceipt::class, 'created_by');
    }

    public function postedInventoryReceipts(): HasMany
    {
        return $this->hasMany(InventoryReceipt::class, 'posted_by');
    }

    public function cancelledInventoryReceipts(): HasMany
    {
        return $this->hasMany(InventoryReceipt::class, 'cancelled_by');
    }

    public function createdStockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'created_by');
    }

    public function postedStockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'posted_by');
    }

    public function cancelledStockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'cancelled_by');
    }

    public function requestedInventoryRequests(): HasMany
    {
        return $this->hasMany(InventoryRequest::class, 'requested_by');
    }

    public function reviewedInventoryRequests(): HasMany
    {
        return $this->hasMany(InventoryRequest::class, 'reviewed_by');
    }

    public function deliveredInventoryRequests(): HasMany
    {
        return $this->hasMany(InventoryRequest::class, 'delivered_by');
    }

    public function changedInventoryRequestStatusHistories(): HasMany
    {
        return $this->hasMany(
            InventoryRequestStatusHistory::class,
            'changed_by',
        );
    }

    public function createdStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }

    public function borrowedVehicleLoans(): HasMany
    {
        return $this->hasMany(VehicleLoan::class, 'borrower_id');
    }

    public function reviewedVehicleLoans(): HasMany
    {
        return $this->hasMany(VehicleLoan::class, 'reviewed_by');
    }

    public function changedVehicleLoanStatusHistories(): HasMany
    {
        return $this->hasMany(
            VehicleLoanStatusHistory::class,
            'changed_by',
        );
    }

    public function performedVehicleConditionChecks(): HasMany
    {
        return $this->hasMany(VehicleConditionCheck::class, 'checked_by');
    }

    public function reportedMaintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'reported_by');
    }

    public function handledMaintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'handled_by');
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    public function digitalSignatures(): HasMany
    {
        return $this->hasMany(DigitalSignature::class, 'signer_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function isPendingActivation(): bool
    {
        return $this->status === AccountStatus::PendingActivation;
    }

    public function requiresPasswordChange(): bool
    {
        return (bool) $this->must_change_password;
    }
}
