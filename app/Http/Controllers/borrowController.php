<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\product;
use App\Models\borrow;
use App\Models\user;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class borrowController extends Controller
{
    public function borrow(Request $request)
    {
        try {
            $validated_data = $request->validate([
                'user_id' => 'required|exists:users,id',
                'product_id' => 'required|exists:products,id',
                'borrow_days' => 'required|max:14',
                'borrow_product_number' => 'required'
            ]);
            // $borrowing = [
            //     'user_id' => $request->input('user_id'),
            //     'product_id' => $request->input('product_id'),
            //     'borrow_days' => $request->input('borrow_days'),
            //     'borrow_product_number' => $request->input('borrow_product_number')
            // ];
            // $user_exists = User::find($request->input('user_id'));
            // if (!$user_exists) {
            //     throw new \Exception("Unable to borrow, user_id {$request->input('user_id')} not found in the User table."); //ห้าม handle 500
            // }
            $stock_update_result = $this->update_product_stock($validated_data['product_id'], $validated_data['borrow_product_number']);
            if ($stock_update_result['success']) {
                $this->update_user_borrow_count($validated_data['user_id']);
                $now = now();
                $borrow_data = array_merge($validated_data, [
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                DB::table('borrows')->insert($borrow_data);
                return response()->json([
                    'message' => $stock_update_result['message'],
                    'remaining_stock' => $stock_update_result['remaining_stock'],
                    'all_stock' => $stock_update_result['all_stock']
                ]);
            } else {
                return response()->json(['success' => false, 'error' => $stock_update_result['message']], 400);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function return (Request $request, $id)
    {
        try {
            $borrow = Borrow::find($id);
            $returnResult = $this->process_return($borrow);
            if ($returnResult['success']) {
                $borrow->update(['return_date' => now()]);
                return response()->json(['success' => true, 'message' => $returnResult['message']]);
            } else {
                return response()->json(['success' => false, 'error' => $returnResult['message']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function delete($id)
    {
        try {
            // $borrow = borrow::find($id);
            // $borrow->delete();
            // return response()->json(['success' => true, 'message' => 'Data deleted successfully']);
            $borrow = Borrow::withTrashed()->where('id', $id)->first();
            if ($borrow) {
                if ($borrow->borrow_status !== 'คืนแล้ว') {
                    return response()->json(['success' => false, 'message' => 'User must be returned before deletion'], 400);
                }
                $borrow->delete();
                return response()->json(['success' => true, 'message' => 'Borrow deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Borrow_id not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        // try {
        //     $borrow = borrow::findOrFail($id);
        //     $borrow->update($request->all()); //สำหรับเพิ่มวันยืมเท่านั้น
        //     return response()->json(['success' => true, 'message' => 'Data updated successfully']);
        // } catch (\Exception $e) {
        //     return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        // }
        try {
            $validated_data = $request->validate([
                'borrow_days' => 'required|max:14'
            ]);
            $borrow = Borrow::where('id', $id)->first();
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => 'กำลังยืม']);
                $borrow->update($validated_data);
                return response()->json(['success' => true, 'message' => 'Borrow_days changed successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Borrow_id not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getList(Request $request)
    {
        try { //รายวัน กำลังยืม คืนแล้ว ดูว่าใครยืม item นี้ไป //ใช้ with whereHas where like //มี data ที่ query ออกมาด้วย
            // $query = Borrow::query(); 
            // $query->join('users', 'borrows.user_id', '=', 'users.id')
            //     ->join('products', 'borrows.product_id', '=', 'products.id') 
            //     ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            // $filters = $request->only(['borrow_status', 'user_id', 'category', 'product_name']); 
            // foreach ($filters as $key => $value) {
            //     if ($value) {
            //         $query->where($key, $value);
            //     }
            // }
            // $borrow_data = $query->get();
            $query = Borrow::withTrashed();
            $filters = $request->only(['borrow_status', 'user_id', 'product_id', 'category', 'user_name', 'product_name', 'create_at']);

            foreach ($filters as $key => $value) {
                if ($value && !in_array($key, ['product_name', 'category', 'user_name'])) {
                    $query->where($key, 'like', "%$value%");
                }
                if ($value && in_array($key, ['user_name', 'product_name', 'category'])) {
                    $relation = ($key === 'user_name') ? 'users' : 'products';
                    $query->whereHas($relation, function ($relationQuery) use ($key, $value) {
                        if ($key === 'category') {
                            $relationQuery->where($key, 'like', "%$value%");
                        } else {
                            $relationQuery->where('name', 'like', "%$value%");
                        }
                    });
                }
            }
            // $borrow_data = $query->with(['users', 'products'])->get();
            // $borrow_data = Borrow::withTrashed()->with(['users', 'products'])->get();
            $borrow_data = $query->with(['users' => function ($query) {
                $query->withTrashed();
            }, 'products' => function ($query) {
                $query->withTrashed();
            }])->get();
            $records = $borrow_data->map(function ($borrow) {
                return [
                    'id' => $borrow->id,
                    'user_name' => optional($borrow->users)->name,
                    'product_name' => optional($borrow->products)->name,
                    'borrow_product_number' => $borrow->borrow_product_number,
                    'borrow_status' => $borrow->borrow_status
                ];
            });
            return response()->json(['success' => true, 'List of borrows' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getDetail($id)
    {
        try {
            // $borrow = Borrow::query();
            //     ->join('users', 'borrows.user_id', '=', 'users.id')
            //     ->join('products', 'borrows.product_id', '=', 'products.id')
            //     ->select('borrows.*', 'users.name as user_name', 'products.name as product_name')
            //     ->findOrFail($id);
            $query = Borrow::withTrashed();
            // $borrow = $query->with(['users', 'products'])->find($id);
            $borrow = $query->with(['users' => function ($query) {
                $query->withTrashed();
            }, 'products' => function ($query) {
                $query->withTrashed();
            }])->find($id);
            $note = '';
            $created_at_date = Carbon::parse($borrow->created_at)->toDateString();
            $return_date = Carbon::parse($borrow->return_date)->toDateString();
            $must_return_day = Carbon::parse($borrow->created_at)->addDays($borrow->borrow_days)->toDateString();
            $days_late = $this->is_return_late($must_return_day, $return_date); //ห้ามยุ่งกับ column updated_at ให้สร้างมาใหม่
            if ($days_late > 0) {
                $note = "เลยกำหนด {$days_late} วัน";
            }
            $this->is_borrow_late($must_return_day, $borrow); //สำหรับอัพเดตยืมเลยกำหนด

            $record = [
                'id' => $borrow->id,
                'user_name' => optional($borrow->users)->name,
                'product_name' => optional($borrow->products)->name,
                'borrow_product_number' => $borrow->borrow_product_number,
                'created_at_date' => $created_at_date,
                'must_return_day' => $must_return_day,
                'borrow_status' => $borrow->borrow_status, //is_borrow_late
                'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                'note' => $note
            ];
            return response()->json(['success' => true, 'Detail of Borrow' => $record], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getHistory(Request $request) //แยกเปน history ของ user product
    {
        try {
            //สำหรับฝั่ง user กับ admin ของชิ้นนี้ใครยืมไป ยืมกี่วัน แยกกันและต่างกัน (admin ดู history ของอะไร เช่น user product มีอะไร ถูกใครยืมไปบ้าง ล่าสุดใครยืม)
            // $query = Borrow::query() //ใช้ groupBy
            //     ->join('users', 'borrows.user_id', '=', 'users.id')
            //     ->join('products', 'borrows.product_id', '=', 'products.id')
            //     ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            // $filters = $request->only(['borrow_status', 'user_id']);
            // foreach ($filters as $key => $value) {
            //     if ($value) {
            //         $query->where($key, $value);
            //     }
            // }
            // $borrow_data = $query->get();
            // $records = [];
            // foreach ($borrow_data as $borrow) {
            //     $action_date = $borrow->borrow_status === 'กำลังยืม'
            //         ? Carbon::parse($borrow->created_at)->toDateString()
            //         : Carbon::parse($borrow->updated_at)->toDateString();
            //     $note = '';
            //     if ($borrow->borrow_status === 'คืนแล้ว') {
            //         $return_day = Carbon::parse($borrow->created_at)->addDays($borrow->borrow_days)->toDateString();
            //         $days_late = $this->is_return_late($return_day, $action_date);
            //         if ($days_late > 0) {
            //             $note = "เลยกำหนด {$days_late} วัน";
            //         }
            //         $records[] = [
            //             'id' => $borrow->id,
            //             'user_name' => $borrow->user_name,
            //             'product_name' => $borrow->product_name,
            //             'borrow_product_number' => $borrow->borrow_product_number,
            //             'borrow_days' => $borrow->borrow_days,
            //             'borrow_status' => 'ทำการยืม',
            //             'วันที่ทำรายการ' => Carbon::parse($borrow->created_at)->toDateString(),
            //             'หมายเหตุ' => '',
            //         ];
            //         $records[] = [
            //             'id' => $borrow->id,
            //             'ชื่อ' => $borrow->user_name,
            //             'ชื่อสิ่งของ' => $borrow->product_name,
            //             'จำนวน' => $borrow->borrow_product_number,
            //             'จำนวนวันยืม' => $borrow->borrow_days,
            //             'สถานะ' => 'ทำการคืน',
            //             'วันที่ทำรายการ' => Carbon::parse($borrow->updated_at)->toDateString(),
            //             'หมายเหตุ' => $note,
            //         ];
            //         continue;
            //     }
            //     $records[] = [
            //         'id' => $borrow->id,
            //         'ชื่อ' => $borrow->user_name,
            //         'ชื่อสิ่งของ' => $borrow->product_name,
            //         'จำนวน' => $borrow->borrow_product_number,
            //         'จำนวนวันยืม' => $borrow->borrow_days,
            //         'สถานะ' => 'ทำการยืม',
            //         'วันที่ทำรายการ' => $action_date,
            //         'หมายเหตุ' => $note,
            //     ];
            // }
            // $sorted_records = collect($records)->sortBy('action_date')->values()->all();
            // return response()->json(['ประวัติการยืมทั้งหมด' => $sorted_records]);
            // $query = Borrow::withTrashed();
            // $filters = $request->only(['borrow_status', 'user_id', 'product_id', 'category', 'user_name', 'product_name', 'create_at']);
            // foreach ($filters as $key => $value) {
            //     if ($value && !in_array($key, ['product_name', 'category', 'user_name'])) {
            //         $query->where($key, 'like', "%$value%");
            //     }
            //     if ($value && in_array($key, ['user_name', 'product_name', 'category'])) {
            //         $relation = ($key === 'user_name') ? 'users' : 'products';
            //         $query->whereHas($relation, function ($relationQuery) use ($key, $value) {
            //             if ($key === 'category') {
            //                 $relationQuery->where($key, 'like', "%$value%");
            //             } else {
            //                 $relationQuery->where('name', 'like', "%$value%");
            //             }
            //         });
            //     }
            // }
            $filters = $request->only(['user_id', 'product_id']);
            $query = Borrow::withTrashed()
                ->with(['users' => function ($query) {
                    $query->withTrashed();
                }, 'products' => function ($query) {
                    $query->withTrashed();
                }]);

            foreach ($filters as $key => $value) {
                if ($value) {
                    if ($key === 'user_id') {
                        $query->where('user_id', $value);
                    } elseif ($key === 'product_id') {
                        $query->where('product_id', $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            }

            $query = $query->orderBy('created_at', 'asc')->get();

            if ($request->has('user_id')) {
                $records = $query->groupBy('product_id')->map(function ($group) {
                    $productBorrows = $group->map(function ($borrow) {
                        return [
                            'product_name' => optional($borrow->products)->name,
                            'borrow_days' => $borrow->borrow_days,
                            'borrow_product_number' => $borrow->borrow_product_number,
                            'borrow_status' => $borrow->borrow_status,
                            'borrow_date' => Carbon::parse($borrow->created_at)->toDateString(),
                            'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                        ];
                    });

                    return [
                        'user' => [
                            'id' => $group->first()->user_id,
                            'name' => optional($group->first()->users)->name,
                            'product_borrow' => $productBorrows->toArray(),
                        ],
                    ];
                });
            } elseif ($request->has('product_id')) {
                $records = $query->groupBy('user_id')->map(function ($group) {
                    $userBorrows = $group->map(function ($borrow) {
                        return [
                            'user_name' => optional($borrow->users)->name,
                            'borrow_days' => $borrow->borrow_days,
                            'borrow_product_number' => $borrow->borrow_product_number,
                            'borrow_status' => $borrow->borrow_status,
                            'borrow_date' => Carbon::parse($borrow->created_at)->toDateString(),
                            'return_date' => $borrow->return_date ? Carbon::parse($borrow->return_date)->toDateString() : 'ยังไม่คืน',
                        ];
                    });

                    return [
                        'product' => [
                            'id' => $group->first()->product_id,
                            'name' => optional($group->first()->products)->name,
                            'user_borrow' => $userBorrows->toArray(),
                        ],
                    ];
                });
            }

            return response()->json(['List of borrows' => $records->values()]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dashboard(Request $request)
    {
        try {
            // $query = Borrow::query();
            // $query->join('users', 'borrows.user_id', '=', 'users.id')
            //     ->join('products', 'borrows.product_id', '=', 'products.id')
            //     ->select('borrows.*', 'users.name as user_name', 'products.name as product_name');
            // $filters = $request->only(['borrow_status', 'user_id', 'category', 'product_id', 'gender']);
            // foreach ($filters as $key => $value) {
            //     if ($value) {
            //         $query->where($key, $value); //ใช้ where like สำหรับ products.name as product_name
            //     }
            // }
            // $borrow_data = $query->get();
            $query = Borrow::withTrashed();
            $filters = $request->only(['borrow_status', 'user_id', 'product_id', 'category', 'gender']);
            foreach ($filters as $key => $value) {
                if ($value && !in_array($key, ['category', 'gender'])) {
                    $query->where($key, 'like', "%$value%");
                }
                if ($value && in_array($key, ['gender', 'category'])) {
                    $relation = ($key === 'gender') ? 'users' : 'products';
                    $query->whereHas($relation, function ($relationQuery) use ($key, $value) {
                        $relationQuery->where($key, 'like', "%$value%");
                    });
                }
            }
            // $borrow_data = $query->with(['users', 'products'])->get();
            $borrow_data = $query->with(['users' => function ($query) {
                $query->withTrashed();
            }, 'products' => function ($query) {
                $query->withTrashed();
            }])->get();
            $total_borrows = $borrow_data->count();
            $total_returns = $borrow_data->where('borrow_status', 'คืนแล้ว')->count();
            $borrow_status_counts = $borrow_data->groupBy('borrow_status')->map->count(); //pluck แทน groupBy
            // dd($borrow_status_counts);
            $dashboardData = [
                'total_borrows' => $total_borrows,
                'total_returns' => $total_returns,
                'borrow_status_counts' => $borrow_status_counts,
                'all_borrowing' => $borrow_data->map(function ($borrow) {
                    return [
                        'id' => $borrow->id,
                        'user_name' => optional($borrow->users)->name,
                        'product_name' => optional($borrow->products)->name,
                        'borrow_product_number' => $borrow->borrow_product_number,
                        'borrow_status' => $borrow->borrow_status,
                    ];
                }),
            ];
            return response()->json(['Dashboard Data' => $dashboardData]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function update_user_borrow_count($user_id)
    {
        User::where('id', $user_id)->increment('times_of_borrow');
        // if (!$user_id) {
        //     return ['success' => false, 'message' => 'ไม่มี user_id นี้'];
        // } else {
        // }
    }

    private function update_product_stock($product_id, $borrow_product_number)
    {
        try {
            $product = Product::find($product_id);
            // if (!$product) {
            //     return ['success' => false, 'message' => 'ไม่มี product_id นี้'];
            // }
            if ($product->in_stock > 0) {
                $remaining_stock = $product->in_stock - $borrow_product_number;
                if ($remaining_stock === 0) {
                    $product->decrement('in_stock', $borrow_product_number);
                    $product->update(['status' => 'ของหมด']);
                    return [
                        'success' => true,
                        'message' => "ทำการยืมเรียบร้อยแล้ว ตอนนี้ของหมดสต๊อกแล้ว",
                        'remaining_stock' => $remaining_stock,
                        'all_stock' => $product->full_stock
                    ];
                } elseif ($remaining_stock < 0) {
                    return [
                        'success' => false,
                        'message' => 'ยืมเกินจำนวนที่มี',
                        'remaining_stock' => $remaining_stock];
                } elseif ($product->status === 'ยังไม่เปิดให้ยืม') {
                    return [
                        'success' => false,
                        'message' => 'ตอนนี้ยังไม่เปิดให้ยืม',
                        'remaining_stock' => $remaining_stock];
                } else {
                    $product->decrement('in_stock', $borrow_product_number);
                    return [
                        'success' => true,
                        'message' => "ทำการยืมเรียบร้อยแล้ว ",
                        'remaining_stock' => $remaining_stock,
                        'all_stock' => $product->full_stock
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
            if ($borrow->borrow_status !== 'คืนแล้ว') {
                $borrow->update(['borrow_status' => 'คืนแล้ว', 'return_date' => now()]);
                // $productId = $borrow->product_id;
                // $product = Product::find($productId); //ไม่ต้อง find อีกรอบ เพิ่มตาม relation ได้เลย
                $borrow->products()->increment('in_stock', $borrow->borrow_product_number); //ใส่ success ทั้งหมด
                if ($borrow->products->status === 'ของหมด') {
                    $borrow->products()->update(['status' => 'ยืมได้']);
                }
                return ['success' => true, 'message' => 'ทำการคืนเรียบร้อยแล้ว'];
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
        if ($current_date->isAfter($return_date)) {
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
        if (!$update_date->isToday()) {
            if ($update_date->isAfter($return_date)) {
                $days_late = $update_date->diffInDays($return_date);
                return $days_late;
            }
        }
        return 0;
    }
}
