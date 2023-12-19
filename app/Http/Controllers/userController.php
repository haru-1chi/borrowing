<?php

namespace App\Http\Controllers;

use App\Models\user;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class userController extends Controller
{
    public function insert(Request $request)
    {
        try {
            //ชื่อโมเดล ควรเปน CamelUpper 'User', ชื่อ controller CamelUpper 'UserController', ตัวแปรเป็น snake แล้วแต่ request
            // $validatedData = $request->validate([
            //     'name' => $request->name,
            //     'gender' => $request->gender,
            //     'birthday' => $request->birthday,
            //     'phone' => $request->phone,
            //     'email' => $request->email,
            // ]);
            // User::create($validatedData); validate *require
            $validated_data = $request->validate([
                'name' => 'required|max:50',
                'gender' => 'required',
                'birthday' => 'required',
                'phone' => 'required',
                'email' => 'required|email',
            ]);
            $now = now();
            $user_data = array_merge($validated_data, ['created_at' => $now, 'updated_at' => $now]);
            DB::table('users')->insert($user_data);
            return response()->json(['success' => true, 'message' => 'User insert successfully'], 201);
            // return response()->json(['message' => 'เพิ่มข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            // return response()->json(['error' => $e->getMessage()], 500); //การส่ง error
        }
    }

    public function delete($id)
    {
        try {
            // $user = User::findOrFail($id); //where find return ในกรณี อย่าส่ง 500 *handle
            // $user->delete();
            $user = User::where('id', $id)->whereDoesntHave('borrows', function ($query) {
                $query->where('borrow_status', 'borrowing');
            })->first();
            $user->delete();
            return response()->json(['success' => true, 'message' => 'User deleted successfully'], 201); //success message error
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated_data = $request->validate([
                'name' => 'sometimes|required|max:50',
                'gender' => 'sometimes|required',
                'birthday' => 'sometimes|required',
                'phone' => 'sometimes|required',
                'email' => 'sometimes|required|email',
            ]);
            $user = User::where('id', $id)->first();
            if ($user) {
                $user->update($validated_data);
                return response()->json(['success' => true, 'message' => 'User updated successfully'], 201);
            } else {
                return response()->json(['success' => false,'error' => 'User not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false,'error' => $e->getMessage()], 400);
        }
    }

    public function getList(Request $request)
    {
        try {
            $gender = $request->input('gender'); //ใช้ request สำหรับ update บางตัวได้ *enum เช่น เพศ status (มีขอบเขต)
            $query = User::query(); //user all
            if ($gender) {
                $query->where('gender', $gender);
            }
            $user_data = $query->get();
            $user_data = $user_data->map(function ($user) { //ใช้ชื่อตาม $user_data หรือ ประกาศ record=[] ก่อน *check ก่อน map เสมอ
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'times_of_borrow' => $user->times_of_borrow
                ];
            });
            return response()->json(['List of users' => $user_data]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getDetail($id)
    {
        try {
            $user = User::findOrFail($id); //find = user only no relation, where = condition relation
            $age = $this->calculateAge($user->birthday);
            $record = [ //key
                'id' => $user->id,
                'name' => $user->name,
                'gender' => $user->gender,
                'age' => $age,
                'birthday' => $user->birthday,
                'phone' => $user->phone,
                'email' => $user->email,
                'times_of_borrow' => $user->times_of_borrow
            ];
            return response()->json(['Detail of User' => $record]); //
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500); //
        }
    }

    private function calculateAge($birthday)
    {
        $birthdate = Carbon::parse($birthday);
        $currentDate = Carbon::now();
        $age = $currentDate->diffInYears($birthdate);
        return $age;
    }
}
