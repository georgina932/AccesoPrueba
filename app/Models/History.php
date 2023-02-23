<?php

namespace App\Models;

use App\Models\Base\History as BaseHistory;

class History extends BaseHistory
{
	protected $fillable = [
		'name'
	];
}
