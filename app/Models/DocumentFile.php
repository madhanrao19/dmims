<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\Favoritable;
use App\Models\Concerns\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFile extends Model
{
    use Auditable, BelongsToCustomer, Favoritable, HasFactory, SoftDeletes, Taggable;

    protected $fillable = [
        'customer_id',
        'file_barcode',
        'file_reference_no',
        'title',
        'document_type_id',
        'department_id',
        'owner_name',
        'current_box_id',
        'current_status',
        'source_origin',
        'destination',
        'borrowed_by',
        'due_date',
        'returned_at',
        'received_date',
        'archived_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'archived_date' => 'date',
        'due_date' => 'date',
        'returned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function currentBox()
    {
        return $this->belongsTo(Box::class, 'current_box_id');
    }

    /**
     * Full physical chain, e.g. "Warehouse A > Rack B > Shelf S02 > Box BX-008 > HR001".
     */
    public function getPhysicalPathAttribute(): string
    {
        if (! $this->currentBox) {
            return $this->current_status === 'moved_out' && $this->destination
                ? "Dispatched to {$this->destination}"
                : 'Unassigned';
        }

        return "{$this->currentBox->physical_path} > {$this->file_reference_no}";
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->current_status === 'moved_out'
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
