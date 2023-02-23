<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Setting;
use Illuminate\Http\Request;
use Response;
use Carbon\Carbon;
use Config;
use File;
use Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class ApiAttendanceController extends Controller
{
    /**
     * Store data attendance to DB
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function apiSaveAttendance(Request $request)
    {
        // Get all request
        $new = $request->all();

        // Get data setting
        $getSetting = Setting::find(1);

        // Get data from request
        $key = $new['key'];
        $q = $new['q'];
        try {
            $qrId = Crypt::decryptString($new['qr_id']);
        } catch (DecryptException $e) {
            $data = [
                'message' => 'Error Qr!',
            ];
            return response()->json($data, 200);
        }
        $date = Carbon::now()->timezone($getSetting->timezone)->format('Y-m-d');
        $location = $new['location'];

        if (!empty($key)) {
            if ($key == $getSetting->key_app) {

                // Check-in
                if ($q == 'in') {

                    // Get data from request
                    $in_time = new Carbon(Carbon::now()->timezone($getSetting->timezone)->format('H:i:s'));

                    // Check if user already check-in
                    $checkAlreadyCheckIn = Attendance::where('worker_id', $qrId)
                        ->where('date', Carbon::now()->timezone($getSetting->timezone)->format('Y-m-d'))
                        ->where('in_time', '<>', null)
                        ->where('late_time', '<>', null)
                        ->where('out_time', null)
                        ->where('out_location', null)
                        ->first();

                    if ($checkAlreadyCheckIn) {
                        $data = [
                            'message' => 'already check-in',
                        ];
                        return response()->json($data, 200);
                    }

                    // Get late time
                    $startHour = Carbon::createFromFormat('H:i:s', $getSetting->start_time);
                    if (!$in_time->gt($startHour)) {
                        $lateTime = "00:00:00";
                    } else {
                        $lateTime = $in_time->diff($startHour)->format('%H:%I:%S');
                    }

                    // Save the data
                    $save = new Attendance();
                    $save->worker_id = $qrId;
                    $save->date = $date;
                    $save->in_location = $location;
                    $save->in_time = $in_time;
                    $save->late_time = $lateTime;

                    $createNew = $save->save();

                    // Saving
                    if ($createNew) {
                        $data = [
                            'message' => 'Success!',
                            'date' => Carbon::parse($date)->format('Y-m-d'),
                            'time' => Carbon::parse($in_time)->format('H:i:s'),
                            'location' => $location,
                            'query' => 'Check-in',
                        ];
                        return response()->json($data, 200);
                    }

                    $data = [
                        'message' => 'Error! Something Went Wrong!',
                    ];
                    return response()->json($data, 200);
                }

                // Check-out
                if ($q == 'out') {
                    // Get data from request
                    $out_time = new Carbon(Carbon::now()->timezone($getSetting->timezone)->format('H:i:s'));
                    $getOutHour = new Carbon($getSetting->out_time);

                    // Get data in_time from DB
                    // To get data work hour
                    $getInTime = Attendance::where('worker_id', $qrId)
                        ->where('date', Carbon::now()->timezone($getSetting->timezone)->format('Y-m-d'))
                        ->where('out_time', null)
                        ->where('out_location', null)
                        ->first();

                    if (!$getInTime) {
                        $data = [
                            'message' => 'check-in first',
                        ];
                        return response()->json($data, 200);
                    }

                    $in_time = Carbon::createFromFormat('H:i:s', $getInTime->in_time);

                    // Get data total working hour
                    $getWorkHour = $out_time->diff($in_time)->format('%H:%I:%S');

                    // Get over time
                    if ($in_time->gt($getOutHour) || !$out_time->gt($getOutHour)) {
                        $getOverTime = "00:00:00";
                    } else {
                        $getOverTime = $out_time->diff($getOutHour)->format('%H:%I:%S');
                    }

                    // Early out time
                    if ($in_time->gt($getOutHour)) {
                        $earlyOutTime = "00:00:00";
                    } else {
                        $earlyOutTime = $getOutHour->diff($out_time)->format('%H:%I:%S');
                    }

                    // Update the data
                    $getInTime->out_time = $out_time;
                    $getInTime->over_time = $getOverTime;
                    $getInTime->work_hour = $getWorkHour;
                    $getInTime->early_out_time = $earlyOutTime;
                    $getInTime->out_location = $location;

                    $updateData = $getInTime->save();

                    // Updating
                    if ($updateData) {
                        $data = [
                            'message' => 'Success!',
                            'date' => Carbon::parse($date)->format('Y-m-d'),
                            'time' => Carbon::parse($out_time)->format('H:i:s'),
                            'location' => $location,
                            'query' => 'Check-Out',
                        ];
                        return response()->json($data, 200);
                    }
                    $data = [
                        'message' => 'Error! Something Went Wrong!',
                    ];
                    return response()->json($data, 200);
                }
                $data = [
                    'message' => 'Error! Wrong Command!',
                ];
                return response()->json($data, 200);
            }
            $data = [
                'message' => 'The KEY is Wrong!',
            ];
            return response()->json($data, 200);
        }
        $data = [
            'message' => 'Please Setting KEY First!',
        ];
        return response()->json($data, 200);
    }
}
