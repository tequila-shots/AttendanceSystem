<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Factory;
use App\User;

//use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    /**
     * Constructor method
     * Register your middlewares here
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'respondWithToken']]);
    }

    /**
     * Register the User
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse 
     */
    public function register(Request $request)
    {
        $credentials = $request->only('name', 'email', 'password');

        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:teachers'
        ];

        $validator = Validator::make($credentials, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 400);
        }

        $name = $request->name;
        $email = $request->email;
        $password = $request->password;

        # Creating the user
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);

        return response()->json(['success' => true, 'message' => 'Signed Up'], 200);
    }

    /**
     * Login the User
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse 
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        # Validator validates the inputs against the rules
        $validator = Validator::make($credentials, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 400);
        }

        try {
            if (!$token = auth()->attempt($credentials)) {
                return response()->json(['success' => false, 'error' => 'We cant find an account with this credentials. Please make sure you entered the right information.'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'error' => 'Failed to login, please try again.'], 500);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Logs out the user
     * @param Illuminate\Http\Request
     * @return Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            auth()->logout();
            return response()->json(['success' => true, 'message' => "You have successfully logged out."], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Failed to logout, please try again.'], 500);
        }
    }

    /**
     * Construct a json object to send to client
     * @param string token
     * @return Illuminate\Http\JsonResponse object
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]
        ], 200);
    }

    /**
     * Get Current Logged In User (according to JWT Token)
     * @return Illuminate\Http\JsonResponse
     */
    public function getCurrentUser()
    {
        # Returns current user object
        $user_obj = auth()->user();

        if ($user_obj != null) {
            return response()->json(['success' => true, 'data' => $user_obj], 200);
        }
        return response()->json(['success' => false, 'error' => 'No Users Found'], 400);
    }
}
