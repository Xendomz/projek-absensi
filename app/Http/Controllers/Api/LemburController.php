<?php

namespace App\Http\Controllers\Api;

use App\Absensi;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Lembur;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\DB;
use App\ProjectManager;

class LemburController extends Controller
{
    protected $imagePath;

    public function __construct()
    {
        $this->imagePath = public_path() . '/storage/lembur/';
    }

    public function filter($role, $id, $month, $year)
    {
        $results = [];
        $users = User::all();
        if ($role == 'Project Manager') {
            $users_under_pm = [];
            $pm_results = [];
            foreach ($users as $key => $user) {
                if (ProjectManager::where('pm_id', '=', $id)->where('user_id', $user->id)->get()->count()) {
                    $users_under_pm[] = $user;
                }
            }
            foreach ($users_under_pm as $key => $under_pm) {
                $pm_results[] =  DB::select(DB::raw("SELECT * FROM lemburs WHERE MONTH(tanggal) = " . $month . " AND YEAR(tanggal) = " . $year . " AND user_id = " . $under_pm->id . " AND status != 'menunggu' "));
            }
            foreach ($pm_results as $res) {
                foreach ($res as $key => $r) {
                    $results[] = $r;
                }
            }
        } else {
            $results =  DB::select(DB::raw("SELECT * FROM lemburs WHERE MONTH(tanggal) = " . $month . " AND YEAR(tanggal) = " . $year . " AND status != 'menunggu' "));
        }

        foreach ($results as $key => $result) {
            $results[$key]->name = User::find($result->user_id)->name;
        }

        return response()->json(['status' => 200, 'message' => 'Berhasil lembur!. Mohon tunggu admin untuk mempersetujuinya.', 'data' => $results]);
    }

    public function index($role, $id)
    {
        $carbon = new Carbon();
        $lates = Lembur::all();
        $latesStatusIsWaiting = [];
        $latesStatusIsDeniedRejected = [];

        if ($role == 'Project Manager') {
            $lembur = [];
            foreach ($lates as $user) {
                if (ProjectManager::where('pm_id', '=', $id)->where('user_id', $user->user_id)->get()->count()) {
                    $lembur[] = $user;
                }
            }

            foreach ($lembur as $key => $l) {
                $lembur[$key]['name'] = User::find($l->user_id)->name;
            }

            foreach ($lembur as $l) {
                if ($l->status == 'menunggu') {
                    $latesStatusIsWaiting[] = $l;
                } else {
                    $latesStatusIsDeniedRejected[] = $l;
                }
            }
        } else {
            $latesStatusIsWaiting = Lembur::where('status', 'menunggu')->where('tanggal', $carbon->now()->toDateString())->get();
            $latesStatusIsDeniedRejected = Lembur::where('status', '!=', 'menunggu')->get();
            foreach ($latesStatusIsWaiting as $key => $wait) {
                $latesStatusIsWaiting[$key]['name'] = User::find($wait->user_id)->name;
            }
            foreach ($latesStatusIsDeniedRejected as $key => $denied) {
                $latesStatusIsDeniedRejected[$key]['name'] = User::find($denied->user_id)->name;
            }
        }

        return response()->json([
            'status' => 200, 'message' => 'Sukses', 'data' =>
            [
                'waiting' => $latesStatusIsWaiting,
                'others' => $latesStatusIsDeniedRejected
            ]
        ]);
    }

    // public function create(Request $request)
    // {
    //     $carbon = new Carbon();
    //     $lembur = new Lembur();
    //     $check_absensi_today = Absensi::where('user_id', '=', Auth::user()->id)->where('tanggal', '=', $carbon->toDateString())->first();

    //     $check_lembur = Lembur::where('user_id', '=', Auth::user()->id)->where('absensi_id', '=', $check_absensi_today['id'])->first();

    //     if ($check_absensi_today === null) {
    //         return response()->json(['status' => 400, 'message' => 'Anda belum absensi hari ini!']);
    //     }

    //     if ($check_lembur !== null) {
    //         return response()->json(['status' => 400, 'message' => 'Anda sudah mengajukan lembur hari ini!']);
    //     }

    //     if (!File::isDirectory($this->imagePath)) {
    //         File::makeDirectory($this->imagePath);
    //     }

    //     $input = $request->file('foto');
    //     $hashNameImage = time() . '_' . $input->getClientOriginalName();
    //     $canvas = Image::canvas(500, 500);
    //     $resizeImage = Image::make($input)->resize(500, 500, function ($constraint) {
    //         $constraint->aspectRatio();
    //     });
    //     $canvas->insert($resizeImage, 'center');
    //     $canvas->save($this->imagePath . '/' . $hashNameImage);

    //     $lembur->user_id = Auth::user()->id;
    //     $lembur->absensi_id = $check_absensi_today['id'];
    //     $lembur->tanggal = Carbon::now()->toDateString();
    //     $lembur->lembur_awal = $request->lembur_awal;
    //     $lembur->lembur_akhir = $request->lembur_akhir;
    //     $lembur->konsumsi = $request->konsumsi_lembur;
    //     $lembur->keterangan = $request->keterangan ?: null;
    //     $lembur->foto = $hashNameImage;
    //     $lembur->status = 'Menunggu';
    //     $lembur->save();

    //     return response()->json([
    //         'status' => 200, 
    //         'message' => 'Berhasil lembur!. Mohon tunggu admin untuk mempersetujuinya.',
    //         'data' => [
    //             'tanggal' => $lembur->tanggal,
    //             'jam_mulai' => $lembur->lembur_awal,
    //             'jam_selesai' => $lembur->lembur_akhir,
    //             'url_foto_lembur' => url('/storage/lembur/' . $hashNameImage),
    //             'konsumsi_lembur' => $lembur->konsumsi,
    //             'keterangan' => $request->keterangan ?: null
    //         ]
    //     ]);
    // }

    // Lembur yang dibuatkan oleh admin itu sendiri
    public function lemburByAdmin(Request $request)
    {
        $tanggal = Carbon::parse($request->tanggal)->addHours(8)->format('Y-m-d');
        $absensi = Absensi::where(['tanggal' => $tanggal, 'user_id' => $request->id])->get();
        if (count($absensi) < 1) {
            return response()->json(['status' => 400, 'message' => 'Absensi tidak ada!'], 400);
        }
        $lembur = new Lembur();
        $lembur->user_id = $request->id;
        $lembur->absensi_id = $absensi[0]->id;
        $lembur->tanggal = Carbon::parse($request->tanggal)->addHours(8)->format('Y-m-d');
        $lembur->lembur_awal = Carbon::parse($request->waktu_mulai)->addHours(8)->format('H:i:s');
        $lembur->lembur_akhir = Carbon::parse($request->waktu_selesai)->addHours(8)->format('H:i:s');
        $lembur->keterangan = $request->keterangan;
        $lembur->konsumsi = $request->konsumsi;
        $lembur->foto = 'default.jpg';
        $lembur->status = 'diterima';
        $lembur->save();
        return response()->json(['status' => 200, 'message' => 'Lembur berhasil dibuat!', 'data' => $lembur], 200);
    }
    public function edit(Request $request, $id)
    {
        Lembur::where('id', '=', $id)->update(['status' => $request->status]);
        return response()->json(['status' => 200, 'message' => 'Berhasil update status lembur!']);
    }

    public function show($id)
    {
        $detail_lembur = Lembur::findOrFail($id);
        $detail_lembur->user->name;

        return response()->json(['status' => 200, 'data' => [
            'detail_lembur' => $detail_lembur
        ]]);
    }

    public function cari($keyword)
    {
        $users = User::where('name', 'LIKE', '%' . $keyword . '%')->get()->pluck('id')->toArray();
        $lembur = [];

        foreach ($users as $user_id) {
            $lembur[] = Lembur::where('user_id', '=', $user_id)->get();
        }

        if (count($lembur) > 0) {
            $lembur = $lembur[0];
            foreach ($lembur as $key => $absen) {
                $lembur[$key]['name'] = User::find($absen->user_id)->name;
            }
            return response()->json(['status' => 200, 'message' => 'Sukses', 'data' => $lembur]);
        }

        return response()->json(['status' => 200, 'message' => 'Kata yang anda cari tidak ditemukan!', 'data' => $lembur]);
    }
}
