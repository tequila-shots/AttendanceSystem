<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Subject;
use App\Student;
use App\Classes;
use App\Lecture;

class LectureController extends Controller
{
    /**
     * Constructor method
     * Register your middleware here
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * Returns students array by day and time
     * This function determines current class by day and time and user id of the logged in teacher
     * @param string day in DAY format
     * @param string time in HH format
     * @return Illuminate\Http\JsonResponse
     */
    public function getByDay($day, $hour)
    {
        $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
        $hours = ['11', '12', '1', '2', '3', '4'];

        $day = strtoupper($day);

        if (!in_array($day, $days, true)) {
            return response()->json(["success" => false, "error" => "Passed 'Day' value is invalid"], 400);
        }

        if (strlen($hour) != 2 || str_contains($hour, ':') || !in_array($hour, $hours)) {
            return response()->json(["success" => false, "error" => "Passed 'Time' value is invalid ! Make sure you are passing time in HH format"], 400);
        }

        if ($hour == '11' || $hour == '12' || $hour == '1') {
            if ($hour == '12') {
                $time_to = "01";
            } else {
                $time_to = ((int) $hour) + 1;
            }
            $time_from = $hour . ":00";
            $time_to = $time_to . ":00";
        } else if ($hour == '2' || $hour == '3' || $hour == '4') {
            $time_from = $hour . ":30";
            $time_to = ((int) $hour) + 1;
            $time_to = $time_to . ":30";
        }

        $time_from = date('h:i', strtotime($time_from));
        $time_to = date('h:i', strtotime($time_to));

        $teacher_id = auth()->id();

        $lecture = Lecture::select('id', 'class', 'subject_id', 'group')->where('day', $day)->where('teacher_id', $teacher_id)->where('time_from', $time_from)->where('time_to', $time_to)->first();

        if (!$lecture) {
            return response()->json(["success" => false, "error" => "No Data Found"], 400);
        }

        $students = $this->getStudents($lecture['group'], $lecture['class']);

        $subject = Subject::select('name')->where('id', $lecture['subject_id'])->first();

        $data = [
            'lecture_id' => $lecture['id'],
            'subject' => $subject['name'],
            'class' => $lecture['class'],
            'teacher_name' => auth()->user()['name'],
            'students' => $students
        ];

        if ($lecture['group'] == NULL) {
            $data['class_type'] = "Class";
        } else {
            $data['class_type'] = "Lab";
            $data['group'] = $lecture['group'];
        }

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Returns array of students
     * @param string
     * @param string
     * @return App\Student [] 
     */
    private function getStudents($group, $class)
    {
        if ($group == NULL) {
            $students = Student::select('roll_no', 'name', 'prn')->where('class', $class)->get();
        } else {
            $students = Student::select('roll_no', 'name', 'prn')->where('class', $class)->where('group', $group)->get();
        }

        return $students;
    }

    /**
     * Add a proxy lecture
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function addProxyLecture(Request $request)
    {
        $request = $request->only('class', 'subject_id', 'day', 'time_from', 'time_to', 'group');

        $request['class'] = strtoupper($request['class']);
        $request['day'] = strtoupper($request['day']);
        $request['group'] = ($request['group'] != NULL) ? strtoupper($request['group']) : NULL;

        $rules = [
            'class' => 'required|max:255',
            'subject_id' => 'required',
            'day' => 'required|in:MON,TUE,WED,THU,FRI,SAT',
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
            return response()->json(['success' => false, 'error' => 'Given subject can not be taught in given class'], 400);
        }

        $request['teacher_id'] = auth()->id();
        $request['type'] = 0; # indicates proxy

        $lecture_id = Lecture::create($request)['id'];

        $students = $this->getStudents($request['group'], $request['class']);

        $data = [
            'lecture_id' => $lecture_id,
            'subject' => Subject::select('name')->where('id', $request['subject_id'])->first()['name'],
            'class' => $request['class'],
            'teacher_name' => auth()->user()['name'],
            'students' => $students
        ];

        if ($request['group'] == NULL) {
            $data['class_type'] = "Class";
        } else {
            $data['class_type'] = "Lab";
            $data['group'] = $request['group'];
        }

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Get student list and lecture id
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function get(Request $request)
    {
        $request = $request->only('class', 'subject_id', 'day', 'time_from', 'time_to', 'group');

        $request['class'] = strtoupper($request['class']);
        $request['day'] = strtoupper($request['day']);
        $request['group'] = ($request['group'] != NULL) ? strtoupper($request['group']) : NULL;

        $rules = [
            'class' => 'required|max:255',
            'subject_id' => 'required',
            'day' => 'required|in:MON,TUE,WED,THU,FRI,SAT',
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
            return response()->json(['success' => false, 'error' => 'Given subject can not be taught in given class'], 400);
        }

        $request['teacher_id'] = auth()->id();
        $request['type'] = 0; # indicates proxy

        $lecture_id = Lecture::create($request)['id'];

        $students = $this->getStudents($request['group'], $request['class']);

        $data = [
            'lecture_id' => $lecture_id,
            'subject' => Subject::select('name')->where('id', $request['subject_id'])->first()['name'],
            'class' => $request['class'],
            'teacher_name' => auth()->user()['name'],
            'students' => $students
        ];

        if ($request['group'] == NULL) {
            $data['class_type'] = "Class";
        } else {
            $data['class_type'] = "Lab";
            $data['group'] = $request['group'];
        }

        return response()->json(['success' => true, 'data' => $data], 200);
    }
}
