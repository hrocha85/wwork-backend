<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'client_id',
        'assignee_id',
        'service_date',
        'service_time',
        'description',
        'price_pence',
        'partner_earning_pence',
        'rate',
        'lat',
        'lng',
        'status',
        'check_in_at',
        'sync_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'price_pence' => 'integer',
            'partner_earning_pence' => 'integer',
            'rate' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'status' => VisitStatus::class,
            'check_in_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(VisitGoal::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CheckEvent::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VisitPhoto::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class);
    }
}
