<?php

namespace App\Http\Controllers;

use App\Models\user;
use Illuminate\Http\Request;
use Carbon\Carbon;

class userController extends Controller {
    public function insert(Request $request) {
        try {
            user::create($request->all());
            return response()->json(['message' => 'เพิ่มข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete($id) {
        try {
            $user = user::findOrFail($id);
            $user->delete();
            return response()->json(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) {
        try {
            $user = user::findOrFail($id);
            $user->update($request->all());
            return response()->json(['message' => 'อัพเดตข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getList(Request $request) {
        try {
            $gender = $request->input('gender');
            $query = user::query();
            if($gender) {
                $query->where('gender', $gender);
            }
            $user_data = $query->get();
            $records = $user_data->map(function ($user) {
                return [
                    'id' => $user->id,
                    'ชื่อ' => $user->name,
                    'จำนวนครั้งที่ยืม' => $user->times_of_borrow
                ];
            });
            return response()->json(['รายการ user' => $records]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getDetail($id) {
        try {
            $user = user::findOrFail($id);
            $age = $this->calculateAge($user->birthday);
            $record = [
                'id' => $user->id,
                'ชื่อ' => $user->name,
                'เพศ' => $user->gender,
                'อายุ' => $age,
                'วันเกิด' => $user->birthday,
                'เบอร์โทร' => $user->phone,
                'E-mail' => $user->email,
                'จำนวนครั้งที่ยืม' => $user->times_of_borrow
            ];
            return response()->json(['รายละเอียด User' => $record]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function calculateAge($birthday) {
        $birthdate = Carbon::parse($birthday);
        $currentDate = Carbon::now();
        $age = $currentDate->diffInYears($birthdate);
        return $age;
    }
}
