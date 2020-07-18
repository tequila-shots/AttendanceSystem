<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Exports\StudentsListExport;
use Maatwebsite\Excel\Facades\Excel;

use App\Student;
use App\Classes;

class StudentController extends Controller
{
    /**
     * Constructor method
     * Register your middleware here
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['getClassByPRN']]);
    }

    /**
     * Returns class name of the student by PRN
     * @param string PRN
     * @return Illuminate\Http\JsonResponse
     */
    public function getClassByPRN($prn)
    {
        $student_obj = Student::select('class')->where('prn', $prn)->first();
        if ($student_obj) {
            return response()->json(['success' => true, 'data' => ['class_name' => $student_obj['class']]], 200);
        } else {
            return response()->json(['success' => false, 'error' => 'No Data Found'], 400);
        }
    }

    /**
     * Add New Student
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function addOne(Request $request)
    {

        $request['name'] = strtoupper($request['name']);
        $request['class'] = strtoupper($request['class']);
        $request['group'] = strtoupper($request['group']);

        $request = $request->only('roll_no', 'name', 'email', 'dob', 'prn', 'class', 'group');

        $rules = [
            'roll_no' => 'required|numeric|unique:students',
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:students',
            'prn' => 'required|unique:students',
            'dob' => 'date|date_format:Y-m-d',
            'class' => 'required',
            'group' => 'required|in:A,B'
        ];

        $validator = Validator::make($request, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'type' => 'Validation Failed', 'error' => $validator->messages()], 400);
        }

        $class = Classes::where('name', $request['class'])->first();
        if (!$class) {
            return response()->json(['success' => false, 'error' => 'Class is not known'], 400);
        }

        $student = Student::create($request);

        if ($student) {
            return response()->json(['success' => true, 'message' => 'Added'], 200);
        }

        return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
    }

    /**
     * Returns Student List
     * @param string
     * @return Illuminate\Http\JsonResponse
     */
    public function getList($class)
    {
        $class_obj = Classes::where('name', $class)->first();
        if (!$class_obj) {
            return response()->json(['success' => false, 'error' => 'Class is not known'], 400);
        }

        $students = Student::where('class', $class)->get();

        if (count($students) < 1) {
            return response()->json(['success' => false, 'error' => 'No Students Found'], 400);
        }

        return response()->json(['success' => true, 'data' => $students], 200);
    }

    /**
     * Returns Student List in form of Excel Sheet
     * File will be deleted after being downloaded
     * @param string, string
     * @return File
     * Or in case of failure
     * @return Illuminate\Http\JsonResponse
     */
    public function export($class, $group = null)
    {
        if (Excel::store(new StudentsListExport($class, $group), '/public//' . $class . 'studentslist.xlsx')) {
            $headers = array('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            return response()->download(storage_path('app\\public\\' . $class . 'studentslist.xlsx'), $class . 'studentslist.xlsx', $headers)->deleteFileAfterSend(true);
            //return response()->json(['success' => true, 'data' => ['url' => storage_path('//public//' . $class . 'studentslist.xlsx')]], 200);
        } else {
            return response()->json(['success' => false, 'error' => "Something Went Wrong"], 500);
        }
    }

    /**
     * Mail Student List in form of Excel Sheet
     * File will be deleted after being downloaded
     * @param string, string
     * @return Illuminate\Http\JsonResponse
     */
    public function mailExport($class, $group = null)
    {
        $user = auth()->user();
        $success = '';
        if (Excel::store(new StudentsListExport($class, $group), '/public//' . $class . 'studentslist.xlsx')) {
            try {
                $subject = "Student List";
                $pathToFile = storage_path('/app/public/' . $class . 'studentslist.xlsx');
                Mail::send(
                    'email.student_list',
                    ['name' => $user->name, 'class' => $class, 'group' => $group],
                    function ($mail) use ($user, $subject, $pathToFile, $class) {
                        $mail->from(env('MAIL_USERNAME'), env('MAIL_PASSWORD'));
                        $mail->to($user->email, $user->name);
                        $mail->subject($subject);
                        $mail->attach($pathToFile, [
                            'as' => $class .  " - Student List.xlsx",
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
            return response()->json(['success' => false, 'error' => "Something Went Wrong"], 500);
        }
    }
}
