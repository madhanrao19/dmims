<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFile extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

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
        'received_date',
        'archived_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'archived_date' => 'date',
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
}
