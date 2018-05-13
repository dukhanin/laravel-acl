<?php

namespace Dukhanin\Acl\Auth\Access;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $guarded = [];

    protected $hidden = [];

    protected $table = 'access_rules';
}