<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Support\Facades\DB;
use App\Http\Controllers\ProgramController;

use App\Classes;
use App\Program;

class ClassController extends Controller
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
     * Get all classes of a particular department
     * @param string
     * @return Illuminate\Http\JsonResponse
     */
    public function getByDepartment($dept = "CSE")
    {
        # Calling function of ProgramController to get all Program belongs to specified department
        $program_response = json_decode(((new ProgramController())->get($dept))->getContent(), true);

        if (!$program_response['success']) {
            return response()->json(["success" => false, "error" => "No Classes Found"], 400);
        }

        $classes = DB::select("SELECT * FROM classes WHERE program IN (SELECT name FROM programs WHERE department='" . $dept . "')");

        # Converting data of classes array from stdClass to array
        for ($i = 0; $i < count($classes); $i++) {
            $classes[$i] = (array) $classes[$i];
        }

        return response()->json(["success" => true, "data" => $classes], 200);
    }

    /**
     * Add new class
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $class_name = strtoupper($request['name']);
        $program_name = strtoupper($request['program']);

        $class = Classes::where('name', $class_name)->first();

        if ($class) {
            return response()->json(['success' => false, 'error' => 'Already Exists'], 400);
        }

        $program = Program::where('name', $program_name)->first();

        if (!$program) {
            return response()->json(['success' => false, 'error' => 'Program is not known'], 400);
        }

        $class = Classes::create(['name' => $class_name, 'program' => $program_name]);

        if ($class) {
            return response()->json(['success' => true, 'message' => 'Added'], 200);
        }

        return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
    }
}
