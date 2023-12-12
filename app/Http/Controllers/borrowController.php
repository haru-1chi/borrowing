<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\product;
use App\Models\borrow;
use App\Models\user;
use Illuminate\Http\Request;

class borrowController extends Controller
{
    public function borrow(Request $request)
    {
        try {
            $borrowing = [
                'user_id' => $request->input('user_id'),
                'product_id' => $request->input('product_id'),
                'borrow_days' => $request->input('borrow_days'),
                'borrow_product_number' => $request->input('borrow_product_number')
            ];
            $user_exists = User::find($request->input('user_id'));

            if (!$user_exists) {
                throw new \Exception("ไม่สามารถทำการยืมได้, ไม่มี user_id {$request->input('user_id')} ในตาราง User");
            }
            $stock_update_result = $this->update_product_stock($request->input('product_id'), $request->input('borrow_product_number'));

            if ($stock_update_result['success']) {
                $this->update_user_borrow_count($request->input('user_id'));
                Borrow::create($borrowing);
                return response()->json([
                    'message' => $stock_update_result['message'],
                    'remaining_stock' => $stock_update_result['จำนวนคงเหลือ'],
                    'all_stock' => $stock_update_result['จำนวนทั้งหมด']
                ]);
            } else {
                return response()->json(['error' => $stock_update_result['message']], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function return (Request $request, $id)
    {
        try {
            $borrow = Borrow::findOrFail($id);

            $returnResult = $this->process_return($borrow);

            if ($returnResult['success']) {
                return response()->json(['message' => $returnResult['message']]);
            } else {
                return response()->json(['error' => $returnResult['message']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function delete($id)
    {
        try {
            $borrow = borrow::findOrFail($id);
            $borrow->delete();

            return response()->json(['message' => 'ลบรายการยืมเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) {
        try {
            $borrow = borrow::findOrFail($id);
            $borrow->update($request->all());

            return response()->json(['message' => 'อัพเดตรายการยืมเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getList(Request $request)
    {
        try {
            $query = Borrow::query();
            $query->join('users', 'borrows.user_id', '=', 'users.id')
                ->join('products', 'borrows.product_id', '=', 'products.id')
                ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            $filters = $request->only(['borrow_status', 'user_id', 'category', 'products.name as product_name']);
            foreach ($filters as $key => $value) {
                if ($value) {
                    $query->where($key, $value);
                }
            }
            $borrow_data = $query->get();

            $records = $borrow_data->map(function ($borrow) {
                return [
                    'id' => $borrow->id,
                    'ชื่อ' => $borrow->user_name,
                    'ชื่อสิ่งของ' => $borrow->product_name,
                    'จำนวน' => $borrow->borrow_product_number,
                    'สถานะการยืม' => $borrow->borrow_status
                ];
            });

            return response()->json(['รายการยืมทั้งหมด' => $records]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getDetail($id)
    {
        try {
            $borrow = Borrow::query()
                ->join('users', 'borrows.user_id', '=', 'users.id')
                ->join('products', 'borrows.product_id', '=', 'products.id')
                ->select('borrows.*', 'users.name as user_name', 'products.name as product_name')
                ->findOrFail($id);
            $note = '';
            $created_at_date = Carbon::parse($borrow->created_at)->toDateString();
            $updated_date = Carbon::parse($borrow->updated_at)->toDateString();
            $return_day = Carbon::parse($borrow->created_at)->addDays($borrow->borrow_days)->toDateString();
            $days_late = $this->is_return_late($return_day, $updated_date);
            if ($days_late > 0) {
                $note = "เลยกำหนด {$days_late} วัน";
            }
            $days_late = $this->is_borrow_late($return_day, $borrow);

            $record = [
                'id' => $borrow->id,
                'ชื่อ' => $borrow->user_name,
                'ชื่อสิ่งของ' => $borrow->product_name,
                'จำนวน' => $borrow->borrow_product_number,
                'วันที่ยืม' => $created_at_date,
                'วันที่ต้องคืน' => $return_day,
                'สถานะการคืน' => $borrow->borrow_status,
                'วันที่คืน' => $updated_date,
                'หมายเหตุ' => $note
            ];
            return response()->json(['รายละเอียดการยืม' => $record]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getHistory(Request $request)
    {
        try {
            $query = Borrow::query()
                ->join('users', 'borrows.user_id', '=', 'users.id')
                ->join('products', 'borrows.product_id', '=', 'products.id')
                ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            $filters = $request->only(['borrow_status', 'user_id']);
            foreach ($filters as $key => $value) {
                if ($value) {
                    $query->where($key, $value);
                }
            }
            $borrow_data = $query->get();

            $records = [];
            foreach ($borrow_data as $borrow) {
                $action_date = $borrow->borrow_status === 'กำลังยืม'
                    ? Carbon::parse($borrow->created_at)->toDateString()
                    : Carbon::parse($borrow->updated_at)->toDateString();
                $note = '';
                if ($borrow->borrow_status === 'คืนแล้ว') {
                    $return_day = Carbon::parse($borrow->created_at)->addDays($borrow->borrow_days)->toDateString();
                    $days_late = $this->isReturnLate($return_day, $action_date);
                    if ($days_late > 0) {
                        $note = "เลยกำหนด {$days_late} วัน";
                    }
                    $records[] = [
                        'id' => $borrow->id,
                        'ชื่อ' => $borrow->user_name,
                        'ชื่อสิ่งของ' => $borrow->product_name,
                        'จำนวน' => $borrow->borrow_product_number,
                        'จำนวนวันยืม' => $borrow->borrow_days,
                        'สถานะ' => 'ทำการยืม',
                        'วันที่ทำรายการ' => Carbon::parse($borrow->created_at)->toDateString(),
                        'หมายเหตุ' => '',
                    ];
                    $records[] = [
                        'id' => $borrow->id,
                        'ชื่อ' => $borrow->user_name,
                        'ชื่อสิ่งของ' => $borrow->product_name,
                        'จำนวน' => $borrow->borrow_product_number,
                        'จำนวนวันยืม' => $borrow->borrow_days,
                        'สถานะ' => 'ทำการคืน',
                        'วันที่ทำรายการ' => Carbon::parse($borrow->updated_at)->toDateString(),
                        'หมายเหตุ' => $note,
                    ];
                    continue;
                }
                $records[] = [
                    'id' => $borrow->id,
                    'ชื่อ' => $borrow->user_name,
                    'ชื่อสิ่งของ' => $borrow->product_name,
                    'จำนวน' => $borrow->borrow_product_number,
                    'จำนวนวันยืม' => $borrow->borrow_days,
                    'สถานะ' => 'ทำการยืม',
                    'วันที่ทำรายการ' => $action_date,
                    'หมายเหตุ' => $note,
                ];
            }

            $sorted_records = collect($records)->sortBy('action_date')->values()->all();
            return response()->json(['ประวัติการยืมทั้งหมด' => $sorted_records]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dashboard(Request $request)
    {
        try {
            $query = Borrow::query();
            $query->join('users', 'borrows.user_id', '=', 'users.id')
                ->join('products', 'borrows.product_id', '=', 'products.id')
                ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            $filters = $request->only(['borrow_status', 'user_id', 'category', 'product_id','gender']);
            foreach ($filters as $key => $value) {
                if ($value) {
                    $query->where($key, $value);
                }
            }
            $borrow_data = $query->get();
            $total_borrows = $borrow_data->count();
            $total_returns = $borrow_data->where('borrow_status', 'คืนแล้ว')->count();
            $borrow_status_counts = $borrow_data->groupBy('borrow_status')->map->count();
            $dashboardData = [
                'จำนวนการยืมทั้งหมด' => $total_borrows,
                'จำนวนการยืมที่คืนแล้ว' => $total_returns,
                'จำนวนสถานะแต่ละรายการ' => $borrow_status_counts,
                'รายการยืมทั้งหมด' => $borrow_data->map(function ($borrow) {
                    return [
                        'id' => $borrow->id,
                        'ชื่อ' => $borrow->user_name,
                        'ชื่อสิ่งของ' => $borrow->product_name,
                        'จำนวน' => $borrow->borrow_product_number,
                        'สถานะ' => $borrow->borrow_status,
                    ];
                }),
            ];
            return response()->json(['Dashboard Data' => $dashboardData]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function update_user_borrow_count($user_id)
    {
        if (!$user_id) {
            return ['success' => false, 'message' => 'ไม่มี user_id นี้'];
        } else {
            User::where('id', $user_id)->increment('times_of_borrow');
        }

    }

    private function update_product_stock($product_id, $borrow_product_number)
    {
        try {
            $product = Product::find($product_id);

            if (!$product) {
                return ['success' => false, 'message' => 'ไม่มี product_id นี้'];
            }
            if ($product->in_stock > 0) {
                $remaining_stock = $product->in_stock - $borrow_product_number;
                if ($remaining_stock === 0) {
                    $product->decrement('in_stock', $borrow_product_number);
                    $product->update(['status' => false]);
                    return [
                        'success' => true,
                        'message' => "ทำการยืมเรียบร้อยแล้ว ตอนนี้ของหมดสต๊อกแล้ว",
                        'จำนวนคงเหลือ' => $remaining_stock,
                        'จำนวนทั้งหมด' => $product->full_stock
                    ];
                } elseif ($remaining_stock < 0) {
                    return ['success' => false, 'message' => 'ยืมเกินจำนวนที่มี', 'จำนวนคงเหลือ' => $remaining_stock];
                } else {
                    $product->decrement('in_stock', $borrow_product_number);
                    return ['success' => true,
                        'message' => "ทำการยืมเรียบร้อยแล้ว ",
                        'จำนวนคงเหลือ' => $remaining_stock,
                        'จำนวนทั้งหมด' => $product->full_stock
                    ];
                }
            } else {
                return ['success' => false, 'message' => 'ไม่สามารถทำการยืมได้ ตอนนี้ของหมดสต๊อก'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function process_return(Borrow $borrow)
    {
        try {
            if ($borrow->borrow_status === 'กำลังยืม') {
                $borrow->update(['borrow_status' => 'คืนแล้ว']);
                $productId = $borrow->product_id;
                $product = Product::find($productId);
                if ($product) {
                    $product->increment('in_stock', $borrow->borrow_product_number);
                    $product->update(['status' => true]);
                    return ['success' => true, 'message' => 'ทำการคืนเรียบร้อยแล้ว'];
                } else {
                    return ['success' => false, 'message' => 'ไม่มี product_id นี้'];
                }
            } else {
                return ['success' => false, 'message' => 'เลือกรายการยืมไม่ถูกต้อง'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function is_borrow_late($return_day, $borrow)
    {
        $return_date = Carbon::parse($return_day)->startOfDay();
        $current_date = Carbon::now()->startOfDay();
        if ($return_date->isAfter($current_date)) {
            $days_late = $return_date->diffInDays($current_date);
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => "เลยกำหนด {$days_late} วัน"]);
            }
            return $days_late;
        }
        return 0;
    }

    private function is_return_late($return_day, $updated_at)
    {
        $return_date = Carbon::parse($return_day)->startOfDay();
        $update_date = Carbon::parse($updated_at)->startOfDay();
        if ($update_date->isAfter($return_date)) {
            $days_late = $update_date->diffInDays($return_date);
            return $days_late;
        }

        return 0;
    }
}
