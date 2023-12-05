<?php

namespace App\Http\Controllers\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use Illuminate\Support\Carbon;
use App\Models\Admin\SetupKyc;
use App\Models\UserNotification;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Traits\ControlDynamicInputFields;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Validation\ValidationException;
use App\Notifications\User\Auth\SendAuthorizationCode;


class AuthorizationController extends Controller
{
    use ControlDynamicInputFields;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function showMailFrom($token)
    {
        $page_title = setPageTitle("Mail Authorization");
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_time = 0;
        if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            $resend_time = Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE));
        }
        $email = $user_authorize->user->email;

        return view('user.auth.authorize.verify-mail',compact("page_title","token","resend_time","email"));
    }

    /**
     * Verify authorizaation code.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function mailVerify(Request $request,$token)
    {
        $request->merge(['token' => $token]);
        $request->validate([
            'token'     => "required|string|exists:user_authorizations,token",
            'code.*'      => "required|integer",
        ]);

        $code = implode("",$request->code);

        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("token",$request->token)->where("code",$code)->first();
        
        if(!$auth_column) return back()->with(['error' => ['invalid Token!']]);

        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Session expired. Please try again']]);
        }
        
        try{
            $auth_column->user->update([
                'email_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Something went wrong! Please try again']]);
        }

        return redirect()->intended(route("user.dashboard"))->with(['success' => ['Account successfully verified']]);
    }
    public function mailResend($token) {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        if(!$user_authorize) return back()->with(['error' => ['Request token is invalid']]);
        if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            throw ValidationException::withMessages([
                'code'      => 'You can resend verification code after '.Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)). ' seconds',
            ]);
        }
        $resend_code = generate_random_code();
        try{
            $user_authorize->update([
                'code'          => $resend_code,
                'created_at'    => now(),
            ]);
            $data = $user_authorize->toArray();
            $user_authorize->user->notify(new SendAuthorizationCode((object) $data));
        }catch(Exception $e) {
            throw ValidationException::withMessages([
                'code'      => "Something went wrong! Please try again.",
            ]);
        }
        return redirect()->route('user.authorize.mail',$token)->with(['success' => ['Mail Resend Success!']]);
    }
    public function authLogout(Request $request) {
        auth()->guard("web")->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function showKycFrom() {
        $basic_settings   = BasicSettings::first();
        $client_ip        = request()->ip() ?? false;
        $user_country     = geoip()->getLocation($client_ip)['country'] ?? "";
        $user             = auth()->user();
        $notifications    = UserNotification::where('user_id',$user->id)->latest()->take(10)->get();
        if($basic_settings['kyc_verification'] == false) return back()->with(['success' => ['Do not need to identity verification!!']]);

        $user         = auth()->user();
        $page_title   = "| KYC Verification";
        $user_kyc     = SetupKyc::userKyc()->first();
        if(!$user_kyc) return back()->with(['success' => ['Do not need to identity verification!!']]);
            $kyc_data   = $user_kyc->fields;
            $kyc_fields = [];
        if($kyc_data) {
            $kyc_fields = array_reverse($kyc_data);
        }

        return view('user.sections.kyc.verify-kyc',compact(
            "page_title",
            "kyc_fields",
            "user_kyc",
            'user_country',
            'user',
            'notifications'
        ));
    }

    public function kycSubmit(Request $request) {

        $user = auth()->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return back()->with(['success' => ['You are already KYC Verified User']]);

        $user_kyc_fields = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules = $this->generateValidationRules($user_kyc_fields);

        $validated = Validator::make($request->all(),$validation_rules)->validate();
        $get_values = $this->placeValueWithFields($user_kyc_fields,$validated);
        
        $create = [
            'user_id'       => auth()->user()->id,
            'data'          => json_encode($get_values),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id],$create);
            $user->update([
                'kyc_verified'  => GlobalConst::PENDING,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return redirect()->route("user.profile.index")->with(['success' => ['KYC information successfully submitted']]);
    }
    /**
     * Googel 2FA Form
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function showGoogle2FAForm() {
        $page_title =  "Authorize Google Two Factor";
        return view('user.auth.authorize.verify-g2fa',compact('page_title'));
    }
    public function google2FASubmit(Request $request) {
        $request->validate([
            'code*'    => "required|numeric",
        ]);
        $code = implode($request->code);;
        $user = auth()->user();
        if(!$user->two_factor_secret) {
            return back()->with(['warning' => ['Your secret key not stored properly. Please contact with system administrator']]);
        }
        if(google_2fa_verify($user->two_factor_secret,$code)) {
            $user->update([
                'two_factor_verified'   => true,
            ]);
            return redirect()->intended(route('user.dashboard'));
        }
        return back()->with(['warning' => ['Failed to login. Please try again']]);
    }
}
