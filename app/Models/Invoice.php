<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'client_id',
        'created_by',
        'number',
        'status',
        'total_pence',
        'locale',
        'invoice_region',
        'pdf_path',
        'sent_at',
        'paid_at',
        'sync_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => InvoiceStatus::class,
            'total_pence' => 'integer',
            'locale' => Locale::class,
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
