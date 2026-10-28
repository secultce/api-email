<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRegistrationEmailProcess extends Model
{
    protected $table = 'import_registration_email_processes';

    protected $fillable = [
        'opportunity_id',
        'registration_id',
        'status',
        'attempts',
        'message_id',
        'agent_email',
        'started_at',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
