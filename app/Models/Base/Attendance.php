<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Base;

use App\Models\History;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Attendance
 *
 * @property int $id
 * @property int $worker_id
 * @property Carbon $date
 * @property Carbon $in_time
 * @property Carbon $out_time
 * @property Carbon $work_hour
 * @property Carbon $over_time
 * @property Carbon $late_time
 * @property Carbon $early_out_time
 * @property string $in_location
 * @property string $out_location
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property History $history
 *
 * @package App\Models\Base
 */
class Attendance extends Model
{
	protected $table = 'attendances';

	protected $casts = [
		'worker_id' => 'int'
	];

	protected $dates = [
		'date',
	];

	public function history()
	{
		return $this->belongsTo(History::class, 'worker_id');
	}
}
