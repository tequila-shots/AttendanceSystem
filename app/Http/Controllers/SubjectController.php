<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Validator;
use \Illuminate\Support\Facades\DB;
use App\Classes;
use App\Subject;
use App\Program;

class SubjectController extends Controller
{

    /**
     * Constructor method
     * Register your middlewares here
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * Add New Student
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $request['name'] = strtoupper($request['name']);
        $request['class'] = strtoupper($request['class']);

        $request = $request->only('name', 'class');

        $rules = [
            'name' => 'required|max:255|unique:subjects',
            'class' => 'required'
        ];

        $validator = Validator::make($request, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'type' => 'Validation Failed', 'error' => $validator->messages()], 400);
        }

        $class = Classes::where('name', $request['class'])->first();
        if (!$class) {
            return response()->json(['success' => false, 'error' => 'Class is not known'], 400);
        }

        $subject = Subject::create($request);

        if ($subject) {
            return response()->json(['success' => true, 'message' => 'Added'], 200);
        }

        return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
    }


    /**
     * Get subjects by class name
     * @param string
     * @return Illuminate\Http\JsonResponse
     */
    public function getByClass($class)
    {
        $class = strtoupper($class);

        $class_obj = Classes::where('name', $class)->first();
        if (!$class_obj) {
            return response()->json(['success' => false, 'error' => 'Class is not known'], 400);
        }
        $subjects = Subject::where('class', $class)->get();

        if (count($subjects) < 1) {
            return response()->json(['success' => false, 'error' => 'No Subjects Found'], 400);
        }

        return response()->json(['success' => true, 'data' => $subjects], 200);
    }

    /**
     * Get subjects by class name
     * @param string
     * @return Illuminate\Http\JsonResponse
     */
    public function getByProgram($program)
    {
        $program = strtoupper($program);

        $program_obj = Program::where('name', $program)->first();
        if (!$program_obj) {
            return response()->json(['success' => false, 'error' => 'Program is not known'], 400);
        }

        $subjects = DB::select("SELECT `name`,`class` FROM subjects WHERE class IN (SELECT `name` FROM classes WHERE program='".$program."')");
        
        # Converting data of classes array from stdClass to array and adding one additional index for program name
        for ($i = 0; $i < count($subjects); $i++) {
            $subjects[$i] = (array) $subjects[$i];
            $subjects[$i]['program'] = $program;
        }

        if (count($subjects) < 1) {
            return response()->json(['success' => false, 'error' => 'No Subjects Found'], 400);
        }

        return response()->json(['success' => true, 'data' => $subjects], 200);
    }
}
