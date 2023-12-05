<?php

namespace App\Traits\PaymentGateway;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\AppliedCoupon;
use App\Models\TemporaryData;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Notifications\sendNotification;
use Illuminate\Support\Facades\Notification;


trait SslcommerzTrait
{
    public function sslcommerzInit($output = null) {
        if(!$output) $output = $this->output;
        
        $credentials = $this->getSslCredentials($output);
        $reference = generateTransactionReference();
        $amount = $output['amount']->total_amount ? number_format($output['amount']->total_amount,2,'.','') : 0;
        $currency = $output['currency']['currency_code']??"USD";

        if(auth()->guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
            $user_email = $user->email;
            $user_phone = $user->full_mobile ?? '';
            $user_name = $user->firstname.' '.$user->lastname ?? '';
        }

        $post_data = array();
        $post_data['store_id'] = $credentials->store_id??"";
        $post_data['store_passwd'] = $credentials->store_password??"";
        $post_data['total_amount'] =$amount;
        $post_data['currency'] = $currency;
        $post_data['tran_id'] =  $reference;
        $post_data['success_url'] =  route('send.remittance.ssl.success');
        $post_data['fail_url'] = route('send.remittance.ssl.fail');
        $post_data['cancel_url'] = route('send.remittance.ssl.cancel');
       
        # $post_data['multi_card_name'] = "mastercard,visacard,amexcard";  # DISABLE TO DISPLAY ALL AVAILABLE

        # EMI INFO
        $post_data['emi_option'] = "1";
        $post_data['emi_max_inst_option'] = "9";
        $post_data['emi_selected_inst'] = "9";

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $user->fullname??"Test Customer";
        $post_data['cus_email'] = $user->email??"test@test.com";
        $post_data['cus_add1'] = $user->address->country??"Dhaka";
        $post_data['cus_add2'] = $user->address->address??"Dhaka";
        $post_data['cus_city'] = $user->address->city??"Dhaka";
        $post_data['cus_state'] = $user->address->state??"Dhaka";
        $post_data['cus_postcode'] = $user->address->zip??"1000";
        $post_data['cus_country'] = $user->address->country??"Bangladesh";
        $post_data['cus_phone'] = $user->full_mobile??"01711111111";
        $post_data['cus_fax'] = "";



        # PRODUCT INFORMATION
        $post_data['product_name'] = "Send Remittance";
        $post_data['product_category'] = "Send Remittance";
        $post_data['product_profile'] = "Send Remittance";
        # SHIPMENT INFORMATION
        $post_data['shipping_method'] = "NO";

         $data = [
            'request_data'    => $post_data,
            'amount'          => $amount,
            'email'           => $user_email,
            'tx_ref'          => $reference,
            'currency'        =>  $currency,
            'customer'        => [
                'email'        => $user_email,
                "phone_number" => $user_phone,
                "name"         => $user_name
            ],
            "customizations" => [
                "title"       => "Send Remittance",
                "description" => dateFormat('d M Y', Carbon::now()),
            ]
        ];

        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->sandbox_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        # REQUEST SEND TO SSLCOMMERZ
        $direct_api_url = $link_url."/gwprocess/v4/api.php";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $direct_api_url );
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


        $content = curl_exec($handle );
        $result = json_decode( $content,true);
        if( $result['status']  != "SUCCESS"){
            throw new Exception($result['failedreason']);
        }
        $this->sslJunkInsert($data);
        return redirect($result['GatewayPageURL']);

    }

    public function getSslCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");
        $store_id_sample = ['store_id','Store Id','store-id'];
        $store_password_sample = ['Store Password','store-password','store_password'];
        $sandbox_url_sample = ['Sandbox Url','sandbox-url','sandbox_url'];
        $live_url_sample = ['Live Url','live-url','live_url'];

        $store_id = '';
        $outer_break = false;
        foreach($store_id_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $store_id = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }


        $store_password = '';
        $outer_break = false;
        foreach($store_password_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $store_password = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        $sandbox_url = '';
        $outer_break = false;
        foreach($sandbox_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $sandbox_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        $live_url = '';
        $outer_break = false;
        foreach($live_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $live_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $mode = $gateway->env;

        $paypal_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if(array_key_exists($mode,$paypal_register_mode)) {
            $mode = $paypal_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }

        return (object) [
            'store_id'     => $store_id,
            'store_password' => $store_password,
            'sandbox_url' => $sandbox_url,
            'live_url' => $live_url,
            'mode'          => $mode,

        ];

    }

    public function sllPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function sslJunkInsert($response) {
        $output = $this->output;
        $user = auth()->guard(get_auth_guard())->user();
        $creator_table = $creator_id = $wallet_table = $wallet_id = null;

        $creator_table = auth()->guard(get_auth_guard())->user()->getTable();
        $creator_id = auth()->guard(get_auth_guard())->user()->id;
       
            $data = [
                'gateway'       => $output['gateway']->id,
                'currency'      => $output['currency']->id,
                'amount'        => json_decode(json_encode($output['amount']),true),
                'response'      => $response,
                'user_record'   => $output['request_data']['identifier'],
                'creator_table' => $creator_table,
                'creator_id'    => $creator_id,
                'creator_guard' => get_auth_guard(),
            ];

        return TemporaryData::create([
            'user_id'       => Auth::user()->id,
            'type'          => PaymentGatewayConst::SSLCOMMERZ,
            'identifier'    => $response['tx_ref'],
            'data'          => $data,
        ]);
    }

    public function sslcommerzSuccess($output = null) {
        
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');
        return $this->createTransactionSsl($output);
    }

    public function createTransactionSsl($output) {
        
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        $trx_id = 'R'.getTrxNum();
       
        $inserted_id = $this->insertRecordSsl($output,$trx_id);
        
        
        $this->removeTempDataSsl($output);
        if($this->requestIsApiUser()) {
            // logout user
            $api_user_login_guard = $this->output['api_login_guard'] ?? null;
            if($api_user_login_guard != null) {
                auth()->guard($api_user_login_guard)->logout();
            }
        }
        if(auth()->check()){
            UserNotification::create([
                'user_id'  => auth()->user()->id,
                'message'  => "Your Remittance  (Payable amount: ".get_amount($output['amount']->total_amount).",
                Get Amount: ".get_amount($output['amount']->will_get).") Successfully Sended.", 
            ]);
        }
        if( $basic_setting->email_notification == true){
            Notification::route("mail",$user->email)->notify(new sendNotification($user,$output,$trx_id));
        }
        return $this->output['trx_id'] ?? "";
    }

    public function insertRecordSsl($output,$trx_id) {
        
        $trx_id = $trx_id;
        $token = $this->output['tempData']['identifier'] ?? "";
        $user_data = TemporaryData::where('identifier',$output['request_data']['identifier'] )->first();
        $this->output['user_data']  = $user_data;
        DB::beginTransaction();
        try{
            if(Auth::guard(get_auth_guard())->check()){
                $user_id = auth()->guard(get_auth_guard())->user()->id;
            }
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => auth()->user()->id,
                'payment_gateway_currency_id'   => $output['currency']->id,
                'type'                          => PaymentGatewayConst::TYPESENDREMITTANCE,
                'remittance_data'               => json_encode([
                    'type'                      => $this->output['user_data']->type,
                    'sender_name'               => $this->output['user_data']->data->sender_name,
                    'sender_email'              => $this->output['user_data']->data->sender_email,
                    'sender_currency'           => $this->output['user_data']->data->sender_currency,
                    'receiver_currency'         => $this->output['user_data']->data->receiver_currency,
                    'sender_ex_rate'            => $this->output['user_data']->data->sender_ex_rate,
                    'sender_base_rate'          => $this->output['user_data']->data->sender_base_rate,
                    'receiver_ex_rate'          => $this->output['user_data']->data->receiver_ex_rate,
                    'coupon_id'                 => $this->output['user_data']->data->coupon_id,
                    'first_name'                => $this->output['user_data']->data->first_name,
                    'middle_name'               => $this->output['user_data']->data->middle_name,
                    'last_name'                 => $this->output['user_data']->data->last_name,
                    'email'                     => $this->output['user_data']->data->email,
                    'country'                   => $this->output['user_data']->data->country,
                    'city'                      => $this->output['user_data']->data->city,
                    'state'                     => $this->output['user_data']->data->state,
                    'zip_code'                  => $this->output['user_data']->data->zip_code,
                    'phone'                     => $this->output['user_data']->data->phone,
                    'method_name'               => $this->output['user_data']->data->method_name,
                    'account_number'            => $this->output['user_data']->data->account_number,
                    'address'                   => $this->output['user_data']->data->address,
                    'document_type'             => $this->output['user_data']->data->document_type,
                    'front_image'               => $this->output['user_data']->data->front_image,
                    'back_image'                => $this->output['user_data']->data->back_image,
                    'sending_purpose'           => $this->output['user_data']->data->sending_purpose->name,
                    'source'                    => $this->output['user_data']->data->source->name,
                    'currency'                  => [
                        'name'                  => $this->output['user_data']->data->currency->name,
                        'code'                  => $this->output['user_data']->data->currency->code,
                        'rate'                  => $this->output['user_data']->data->currency->rate,
                    ],
                    'send_money'                => $this->output['user_data']->data->send_money,
                    'fees'                      => $this->output['user_data']->data->fees,
                    'convert_amount'            => $this->output['user_data']->data->convert_amount,
                    'payable_amount'            => $this->output['user_data']->data->payable_amount,
                    'remark'                    => $this->output['user_data']->data->remark,
                ]),
                'trx_id'                        => $trx_id,
                'request_amount'                => $this->output['user_data']->data->send_money,
                'exchange_rate'                 => $output['amount']->sender_cur_rate,
                'payable'                       => $output['amount']->total_amount,
                'fees'                          => $output['amount']->total_charge,
                'convert_amount'                => $output['amount']->convert_amount,
                'will_get_amount'               => $output['amount']->will_get,
                
                'remark'                        => $output['gateway']->name,
                'details'                       => "COMPLETED",
                'status'                        => global_const()::REMITTANCE_STATUS_PENDING,
                'attribute'                     => PaymentGatewayConst::SEND,
                'created_at'                    => now(),
            ]);
            if($this->output['user_data']->data->coupon_id != 0){
                $user   = auth()->user();
                $user->update([
                    'coupon_status'     => 1,
                ]);
                
                AppliedCoupon::create([
                    'user_id'   => $user->id,
                    'coupon_id'   => $this->output['user_data']->data->coupon_id,
                    'transaction_id'   => $id,
                ]);
            }
            
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        $this->output['trx_id'] = $trx_id;
        return $id;
    }

   

   

    

    public function removeTempDataSsl($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }
    //for api
    public function sslcommerzInitApi($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getSslCredentials($output);
        $reference = generateTransactionReference();
        $amount = $output['amount']->total_amount ? number_format($output['amount']->total_amount,2,'.','') : 0;
        $currency = $output['currency']['currency_code']??"USD";

        if(auth()->guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
            $user_email = $user->email;
            $user_phone = $user->full_mobile ?? '';
            $user_name = $user->firstname.' '.$user->lastname ?? '';
        }
        $post_data = array();
        $post_data['store_id'] = $credentials->store_id??"";
        $post_data['store_passwd'] = $credentials->store_password??"";
        $post_data['total_amount'] =$amount;
        $post_data['currency'] = $currency;
        $post_data['tran_id'] =  $reference;
        $post_data['success_url'] =  route('api.send.remittance.ssl.success',"?r-source=".PaymentGatewayConst::APP);
        $post_data['fail_url'] = route('api.send.remittance.ssl.fail',"?r-source=".PaymentGatewayConst::APP);
        $post_data['cancel_url'] = route('api.send.remittance.ssl.cancel',"?r-source=".PaymentGatewayConst::APP);
        # $post_data['multi_card_name'] = "mastercard,visacard,amexcard";  # DISABLE TO DISPLAY ALL AVAILABLE
        
        # EMI INFO
        $post_data['emi_option'] = "1";
        $post_data['emi_max_inst_option'] = "9";
        $post_data['emi_selected_inst'] = "9";

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $user->fullname??"Test Customer";
        $post_data['cus_email'] = $user->email??"test@test.com";
        $post_data['cus_add1'] = $user->address->country??"Dhaka";
        $post_data['cus_add2'] = $user->address->address??"Dhaka";
        $post_data['cus_city'] = $user->address->city??"Dhaka";
        $post_data['cus_state'] = $user->address->state??"Dhaka";
        $post_data['cus_postcode'] = $user->address->zip??"1000";
        $post_data['cus_country'] = $user->address->country??"Bangladesh";
        $post_data['cus_phone'] = $user->full_mobile??"01711111111";
        $post_data['cus_fax'] = "";



        # PRODUCT INFORMATION
        $post_data['product_name'] = "Send Remittance";
        $post_data['product_category'] = "Send Remittance";
        $post_data['product_profile'] = "Send Remittance";
        # SHIPMENT INFORMATION
        $post_data['shipping_method'] = "NO";

         $data = [
            'request_data'    => $post_data,
            'amount'          => $amount,
            'email'           => $user_email,
            'tx_ref'          => $reference,
            'currency'        =>  $currency,
            'customer'        => [
                'email'        => $user_email,
                "phone_number" => $user_phone,
                "name"         => $user_name
            ],
            "customizations" => [
                "title"       => "Send Remittance",
                "description" => dateFormat('d M Y', Carbon::now()),
            ]
        ];

        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->sandbox_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        # REQUEST SEND TO SSLCOMMERZ
        $direct_api_url = $link_url."/gwprocess/v4/api.php";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $direct_api_url );
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


        $content = curl_exec($handle );
        $result = json_decode( $content,true);
        if( $result['status']  != "SUCCESS"){
            throw new Exception($result['failedreason']);
        }

        $data['link'] = $result['GatewayPageURL'];
        $data['trx'] =  $reference;

        $this->sslJunkInsert($data);
        return $data;

    }

}
