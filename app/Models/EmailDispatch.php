<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class EmailDispatch extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'email_dispatches';

    protected $auditInclude = [
        'to',
        'subject',
        'meta'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'to',
        'subject',
        'content',
        'mailable_type',
        'meta',
        'status',
        'attempted_at',
        'dispatched_at',
        'failed_at',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'meta' => 'array',
        'attempted_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
