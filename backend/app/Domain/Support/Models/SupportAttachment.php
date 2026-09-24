<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAttachment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['storage_key'];
}
