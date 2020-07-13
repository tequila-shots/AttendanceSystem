<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Department;

class DepartmentController extends Controller
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
     * Get all departments
     * @return array
     */
    public function getAll()
    {
        $arr = Department::all();
        if (count($arr) < 1) {
            return response()->json(['success' => false, 'error' => 'No Data Found'], 400);
        } else {
            return response()->json(['success' => true, 'data' => $arr], 200);
        }
    }

    /**
     * Add new department
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $dept_name = strtoupper($request['name']);

        $dept = Department::where('name', $dept_name)->first();

        if ($dept) {
            return response()->json(['success' => false, 'error' => 'Already Exists'], 400);
        }
        $dept = Department::create(['name' => $dept_name]);
        if ($dept) {
            return response()->json(['success' => true, 'message' => 'Added'], 200);
        }
        return response()->json(['success' => false, 'error' => 'Something went wrong'], 500);
    }
}
