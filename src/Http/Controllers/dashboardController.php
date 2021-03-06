<?php

namespace YM\Http\Controllers;

use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use YM\Models\User;

class DashBoardController extends Controller
{
    use RegistersUsers;

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    public function index()
    {
        ######################get IP Information##############################
        $ips = DB::table('ip_info')->orderBy('created_at', 'desc')->paginate(10);
        $link = $ips->links();

        $label = [];
        $data = '';
        DB::table('ip_info_rate')->orderBy('order', 'asc')->get()->each(function ($item) use (&$label, &$data) {
            array_push($label, $item->period);
            $data .= $item->rate . ',';
        });

        $label = json_encode($label);
        $data = rtrim($data, ',');
        $list = compact('ips', 'link', 'label', 'data');
        return view('umi::dashboard', $list);
        ######################################################################

//        return view('umi::dashboard');
    }

    public function dashboard(Request $request)
    {
        $userName = $request->get('username');
        $password = $request->get('password');
        if (Auth::attempt(['name' => $userName, 'password' => $password])) {

            #######################save ip information#######################
            $ip = $_SERVER['REMOTE_ADDR'];
            $query = @unserialize(file_get_contents('http://ip-api.com/php/'.$ip));
            if($query && $query['status'] == 'success') {
                $country = $query['country'];
                $region = $query['region'];
                $city = $query['city'];
                $add = DB::table('ip_info')->insert([
                    'user_name' => $userName,
                    'ip'        => $ip,
                    'country'   => $country,
                    'region'    => $region,
                    'city'      => $city
                ]);

                if ($add) {
                    $period = date('Y-M');
                    DB::table('ip_info_rate')->where('period', $period)->increment('rate', 1);
                }
            }
            ######################################################################

            if (session('previousUrl'))
                return redirect($request->session()->pull('previousUrl'));

            return redirect()->route('dashboard');
        } else {
            return view('umi::login', ['error' => '<script>alert("please check username or password")</script>']);
        }
    }

    public function getLogout()
    {
        Auth::logout();
        Cache::flush();
        return redirect()->route('admin');
    }

    public function getRefresh()
    {
        $url = base64_decode($_REQUEST['u']);
        Cache::flush();
        return redirect($url);
    }
}
