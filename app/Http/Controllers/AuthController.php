<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
//5|S124FSg1IFC7bgvDzSTsYrobfpIfD5vkEHvc6v3D
class AuthController extends Controller
{
    public function login(Request $request)
    {
        if(Auth::attempt($request->only('email','password'))){
            return  response()->json(['message'=>'Authorized','status'=>200,'data'=>[
                        'token'=> $request->user()->createToken('developer')->plainTextToken
                    ],
            ]);
        }else{
            return  response()->json(['message'=>'Not Authorized','status'=>403]);

        }
    }
}
