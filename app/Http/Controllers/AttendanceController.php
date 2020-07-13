<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceListExport;
use App\Attendance;
use App\Lecture;
use App\Student;
use App\Subject;
use App\User;
use App\Classes;

class AttendanceController extends Controller
{
    /**
     * Constructor method
     * Register your middleware here
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['getStats', 'getStatsForAll']]);
    }

    /**
     * Method to mark attendance
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function markAttendance(Request $request)
    {
        $request = $request->only('lecture_id', 'students', 'date');

        $rules = [
            'lecture_id' => 'required',
            "students"    => "array",
            "students.*"  => "string|distinct",
            'date' => 'required|date_format:Y-m-d'
        ];

        $validator = Validator::make($request, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 400);
        }

        if (!Lecture::where('id', $request['lecture_id'])->exists()) {
            return response()->json(["success" => false, "error" => "Given Lecture ID is invalid"], 400);
        }

        $lecture_id = $request['lecture_id'];

        $lecture_obj = Lecture::select('group', 'class', 'time_from')->where('id', $lecture_id)->first();

        $date = date('Y-m-d h:i:s', strtotime($request['date'] . " " . $lecture_obj['time_from']));

        # All students
        if ($lecture_obj['group'] == NULL) {
            $students_obj = Student::select('*')->where('class', $lecture_obj['class'])->get();
        } else {
            $students_obj = Student::select('*')->where('class', $lecture_obj['class'])->where('group', $lecture_obj['group'])->get();
        }

        foreach ($students_obj as $item) {
            $students[] = $item['roll_no'];
        }

        if (!isset($request['students'])) {
            $present_students[] = NULL;
        } else {
            foreach ($request['students'] as $item) {
                $present_students[] =  $item;
            }
        }

        if (count($present_students) != count($students)) {
            $absent_students = array();
            $absent_students = array_diff($students, $present_students);
        }

        # Transaction to ensure each entry is inserted
        DB::beginTransaction();

        try {
            # Loop for marking present
            foreach ($present_students as $student_id) {
                Attendance::create([
                    'lecture_id' => $lecture_id,
                    'student_id' => $student_id,
                    'date' => $date,
                    'isPresent' => 1
                ]);
            }
            if (count($present_students) != count($students)) {
                # Loop for marking absent
                foreach ($absent_students as $student_id) {
                    Attendance::create([
                        'lecture_id' => $lecture_id,
                        'student_id' => $student_id,
                        'date' => $date,
                        'isPresent' => 0
                    ]);
                }
            }
            DB::commit();

            # Get Stats and then send mail if attendance is lower than criteria
            $subject_id = Lecture::select('subject_id')->where('id', $request['lecture_id'])->first()['subject_id'];
            foreach ($students_obj as $student) {
                $response = $this->getStats($student['prn'], $subject_id);
                $response = get_object_vars($response->getData());
                if ($response['success']) {
                    $response['data'] = get_object_vars($response['data']);
                    if ($response['data']['percentage'] < env('PERCENTAGE_CRITERIA')) {
                        $this->sendMail($student, $response['data']);
                    }
                }
            }
            return response()->json(["success" => true, "message" => 'Marked Successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["success" => false, "error" => "Something went wrong ! Please try again"], 500);
        }
        return response()->json(["success" => false, "error" => "Something went wrong ! Please try again"], 500);
    }

    /**
     * Returns statistics for given PRN number for given subject
     * @param string PRN
     * @param string Subject ID
     * @return Illuminate\Http\JsonResponse
     */
    public function getStats($prn, $subject_id)
    {
        if ($prn == NULL) {
            return response()->json(["success" => false, "error" => "PRN not provided or NULL provided"], 400);
        }

        if ($subject_id == NULL) {
            return response()->json(["success" => false, "error" => "Subject ID not provided or NULL provided"], 400);
        }

        $student_obj = Student::select('roll_no', 'name', 'class', 'group')->where('prn', $prn)->orderBy('id', 'DESC')->first();

        if (!$student_obj) {
            return response()->json(["success" => false, "error" => "PRN not found"], 400);
        }

        $subject_name = Subject::select('name')->where('id', $subject_id)->first()['name'];

        $lecture_obj = Lecture::select('id', 'type', 'group')->where('subject_id', $subject_id)->where('class', $student_obj['class'])->get();

        if ($lecture_obj->count() == 0) {
            return response()->json(["success" => false, "error" => "No Lectures"], 400);
        }

        if ($lecture_obj[0]['group'] != NULL) {
            $lecture_obj = Lecture::select('id', 'type', 'group')->where('subject_id', $subject_id)->where('class', $student_obj['class'])->where('group', $student_obj['group'])->get();
        }

        $present_count = 0;
        $absent_count = 0;
        $regular_count = 0;
        $proxy_count = 0;
        foreach ($lecture_obj as $lecture) {

            $present_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->where('isPresent', 1)->count();
            $absent_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->where('isPresent', 0)->count();

            if ($lecture['type'] == '1') {
                $regular_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->count();
            } else {
                $proxy_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->count();
            }
        }

        $total_days = $present_count + $absent_count;
        $percentage = ($total_days == 0) ? 0 : ROUND((float) ($present_count / $total_days) * 100);

        return response()->json(["success" => true, "data" => [
            "student_name" => $student_obj['name'],
            "subject_name" => $subject_name,
            "class" => $student_obj['class'],
            "group" => $student_obj['group'],
            "total_lectures" => $total_days,
            "total_present" => $present_count,
            "total_absent" => $absent_count,
            "regular_lectures" => $regular_count,
            "proxy_lectures" => $proxy_count,
            "percentage" => $percentage
        ]], 200);
    }

    /**
     * Returns statistics for given PRN number for all subjects
     * @param string PRN
     * @return Illuminate\Http\JsonResponse
     */
    public function getStatsForAll($prn)
    {
        if ($prn == NULL) {
            return response()->json(["success" => false, "error" => "PRN not provided or NULL provided"], 400);
        }

        $student_obj = Student::select('roll_no', 'name', 'class', 'group')->where('prn', $prn)->orderBy('id', 'DESC')->first();

        if (!$student_obj) {
            return response()->json(["success" => false, "error" => "PRN not found"], 400);
        }

        $subjects = DB::select("SELECT DISTINCT `subject_id`, `teacher_id` FROM `lectures` WHERE class='SSMCA-II' AND (`group` = '" . $student_obj['group'] . "' OR `group` IS NULL)");

        if (count($subjects) == 0) {
            return response()->json(["success" => false, "error" => "No lectures found"], 400);
        }

        # Converting data of classes array from stdClass to array
        for ($i = 0; $i < count($subjects); $i++) {
            $subjects[$i] = (array) $subjects[$i];
        }

        $data = array();
        $data["student_name"] = $student_obj['name'];
        $data["class"] = $student_obj['class'];
        $data["group"] = $student_obj['group'];
        $data["subjects"] = array();
        foreach ($subjects as $subject) {
            $present_count = 0;
            $absent_count = 0;
            $regular_count = 0;
            $proxy_count = 0;
            $local_data = array();

            # Subject Name
            $local_data['subject'] = Subject::select('name')->where('id', $subject['subject_id'])->first()['name'];
            # Teacher Name
            $local_data['teacher'] = User::select('name')->where('id', $subject['teacher_id'])->first()['name'];

            $sql = 'SELECT `id`, `type` FROM `lectures` WHERE `subject_id`=' . $subject['subject_id'] . ' AND `teacher_id`=' . $subject['teacher_id'] . ' AND `class`="' . $student_obj['class'] . '" AND (`group` = "' . $student_obj['group'] . '" OR `group` IS NULL)';

            $lectures = DB::select($sql);

            # Converting data of classes array from stdClass to array
            for ($i = 0; $i < count($lectures); $i++) {
                $lectures[$i] = (array) $lectures[$i];
            }

            foreach ($lectures as $lecture) {

                $present_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->where('isPresent', 1)->count();
                $absent_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->where('isPresent', 0)->count();
                if ($lecture['type'] == '1') {
                    $regular_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->count();
                } else {
                    $proxy_count += (int) Attendance::select('*')->where('lecture_id', $lecture['id'])->where('student_id', $student_obj['roll_no'])->count();
                }
            }

            $local_data["total_lectures"] = $present_count + $absent_count;
            $local_data["total_present"] = $present_count;
            $local_data["total_absent"] = $absent_count;
            $local_data["regular_lectures"] = $regular_count;
            $local_data["proxy_lectures"] = $proxy_count;
            $local_data["percentage"] = ($local_data["total_lectures"] != 0) ? ROUND((float) ($present_count / $local_data["total_lectures"]) * 100) : 0;
            array_push($data['subjects'], $local_data);
        }

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Function to send mail to students whose attendance is lower than the eligible criteria
     * @param array
     * @param array
     * @return void
     */
    private function sendMail($student, $stats)
    {
        try {
            $subject = "Low Attendance";
            Mail::send(
                'email.attendance',
                ['name' => $student['name'], 'prn' => $student['prn'], 'percentage' => $stats['percentage'], 'subject_name' => $stats['subject_name']],
                function ($mail) use ($student, $subject) {
                    $mail->from(env('MAIL_USERNAME'), env('MAIL_PASSWORD'));
                    $mail->to($student['email'], $student['name']);
                    $mail->subject($subject);
                }
            );
            Log::info('Mail Sent');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }

    /**
     * Get student list with attendance
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function previousAttendance(Request $request)
    {
        $request = $request->only('class', 'subject_id', 'time_from', 'time_to', 'group', 'date');

        $request['class'] = strtoupper($request['class']);
        $request['group'] = ($request['group'] != NULL) ? strtoupper($request['group']) : NULL;

        $rules = [
            'class' => 'required|max:255',
            'subject_id' => 'required',
            'date' => 'required|date_format:Y-m-d|before_or_equal:' . date('Y-m-d'),
            'time_from' => 'date_format:h:i:s',
            'time_to' => 'date_format:h:i:s',
        ];

        $validator = Validator::make($request, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 400);
        }

        if (!Subject::where('id', $request['subject_id'])->exists()) {
            return response()->json(['success' => false, 'error' => 'Passed Subject ID does not exists'], 400);
        }

        if (!Classes::where('name', $request['class'])->exists()) {
            return response()->json(['success' => false, 'error' => 'Passed Class does not exists'], 400);
        }

        if (!Subject::where('id', $request['subject_id'])->where('class', $request['class'])->exists()) {
            # replace error message
            return response()->json(['success' => false, 'error' => 'Given subject not be taught in given class'], 400);
        }

        $day = strtoupper(date('D', strtotime($request['date'])));

        if ($request['group'] != NULL) {
            $lecture_obj = Lecture::select('id')->where('class', $request['class'])->where('teacher_id', auth()->id())->where('subject_id', $request['subject_id'])->where('day', $day)->where('time_from', $request['time_from'])->where('group', $request['group'])->first();
        } else {
            $lecture_obj = Lecture::select('id')->where('class', $request['class'])->where('teacher_id', auth()->id())->where('subject_id', $request['subject_id'])->where('day', $day)->where('time_from', $request['time_from'])->first();
        }

        if (!$lecture_obj) {
            return response()->json(['success' => false, 'error' => 'No Lectures'], 400);
        }

        $lecture_id = $lecture_obj['id'];

        $attendance_obj = Attendance::join('students', 'students.roll_no', '=', 'attendance.student_id')->select('students.roll_no', 'students.name', 'attendance.isPresent')->where('lecture_id', $lecture_id)->where('date', $request['date'] . " " . $request['time_from'])->get();

        if ($attendance_obj->count() == 0) {
            return response()->json(['success' => false, 'error' => 'No attendance data found for given lecture']);
        } else {
            return response()->json(['success' => true, 'data' => $attendance_obj], 200);
        }
    }

    /**
     * Returns Excel based on data from the previousAttendance method
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function getExcel(Request $request)
    {
        $send_mail = false;
        if (isset($request['mail'])) {
            if ($request['mail'] == 1) {
                $send_mail = true;
            }
            unset($request['mail']);
        }
        $response = $this->previousAttendance($request);
        $data = get_object_vars($response->getData());
        if (!$data['success']) {
            return $response;
        }
        $data = $data['data'];
        for ($i = 0; $i < count($data); $i++) {
            $data[$i] = (array) $data[$i];
            if ($data[$i]['isPresent']) {
                $data[$i]['isPresent'] = "P";
            } else {
                $data[$i]['isPresent'] = "A";
            }
        }
        if (!Excel::store(new AttendanceListExport($data), '/public//' . $request['class'] . 'attendancelist.xlsx')) {
            return response()->json(['success' => false, 'error' => 'Excel Generation Failed'], 500);
        }

        if ($send_mail) {
            $user = auth()->user();
            $success = '';
            try {
                $subject = "Students Attendance List";
                $pathToFile = storage_path('/app/public/' . $request['class'] . 'attendancelist.xlsx');
                $class = $request['class'];
                Mail::send(
                    'email.attendance_list',
                    ['name' => $user->name, 'class' => $class, 'group' => $request['group']],
                    function ($mail) use ($user, $subject, $pathToFile, $class) {
                        $mail->from(env('MAIL_USERNAME'), env('MAIL_PASSWORD'));
                        $mail->to($user->email, $user->name);
                        $mail->subject($subject);
                        $mail->attach($pathToFile, [
                            'as' => $class .  " - Students Attendance List.xlsx",
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ]);
                    }
                );
                Log::info('Mail Sent');
                $success = true;
            } catch (\Exception $e) {
                Log::info($e->getMessage());
                $success = false;
            } finally {
                if (File::exists($pathToFile))
                    File::delete($pathToFile);
            }
            if ($success)
                return response()->json(['success' => true, 'message' => 'Mail Sent'], 200);
            else
                return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
        } else {
            $headers = array('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            return response()->download(storage_path('app\\public\\' . $request['class'] . 'attendancelist.xlsx'),  $request['class'] . 'attendancelist.xlsx', $headers)->deleteFileAfterSend(true);
        }
    }
}
