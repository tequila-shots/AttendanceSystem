<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Program;
use App\Department;

class ProgramController extends Controller
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
     * Get all programs
     * @return Illuminate\Http\JsonResponse
     */
    public function getAll()
    {
        $arr = Program::all();
        if (count($arr) < 1) {
            return response()->json(['success' => false, 'error' => 'No Data Found'], 400);
        } else {
            return response()->json(['success' => true, 'data' => $arr], 200);
        }
    }

    /**
     * Get all programs comes under passed department
     * @param string
     * @return Illuminate\Http\JsonResponse
     */
    public function get($dept)
    {
        if ($dept == "") {
            return response()->json([], 400);     
        }
        $dept = strtoupper($dept);
        $data = Program::where('department', $dept)->get();
        if (count($data) < 1) {
            return response()->json(['success' => false, 'error' => 'No Data Found'], 400);
        } else {
            return response()->json(['success' => true, 'data' => $data], 200);
        }
    }

    /**
     * Add new program
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $program_name = strtoupper($request['name']);
        $dept_name = strtoupper($request['dept']);

        $program = Program::where('name', $program_name)->first();

        if ($program) {
            return response()->json(['success' => false, 'error' => 'Already Exists'], 400);
        }

        $dept = Department::where('name', $dept_name)->first();

        if (!$dept) {
            return response()->json(['success' => false, 'error' => 'Department is not known'], 400);
        }

        $program = Program::create(['name' => $program_name, 'department' => $dept_name]);
        
        if ($program) {
            return response()->json(['success' => true, 'message' => 'Added'], 200);
        }
        
        return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
    }
}
