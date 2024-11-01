<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class XHWechatWCPaymentGateway extends WC_Payment_Gateway {
    private $config;
    
	public function __construct() {
		//支持退款
		array_push($this->supports,'refunds');

		$this->id = XH_WC_WeChat_ID;
		$this->icon =XH_WC_WeChat_URL. '/images/logo.png';
		$this->has_fields = false;
		
		$this->method_title = '微信支付'; // checkout option title
	    $this->method_description='企业版本支持微信原生支付（H5公众号）、微信登录、微信红包推广/促销、微信收货地址同步、微信退款等功能。若需要企业版本，请访问<a href="http://www.wpweixin.net" target="_blank">http://www.wpweixin.net</a> ';
	   
		$this->init_form_fields ();
		$this->init_settings ();
		
		$this->title = $this->get_option ( 'title' );
		$this->description = $this->get_option ( 'description' );
		
		$lib = XH_WC_WeChat_DIR.'/lib';
		
		if(!class_exists('WechatPaymentApi')){
		    include_once ($lib . '/WxPay.Data.php');
    		include_once ($lib . '/WxPay.Api.php');
    		include_once ($lib . '/WxPay.Exception.php');
    		include_once ($lib . '/WxPay.Notify.php');
    		include_once ($lib . '/WxPay.Config.php');
    		
    		if(!class_exists('ILogHandler')){
    		  include_once ($lib . '/log.php');
    		}
		}
		
		$this->config =new WechatPaymentConfig ($this->get_option('wechatpay_appID'),  $this->get_option('wechatpay_mchId'), $this->get_option('wechatpay_key'));
	}
	function init_form_fields() {
	    $this->form_fields = array (
	        'enabled' => array (
	            'title' => __ ( 'Enable/Disable', 'wechatpay' ),
	            'type' => 'checkbox',
	            'label' => __ ( 'Enable WeChatPay Payment', 'wechatpay' ),
	            'default' => 'no'
	        ),
	        'title' => array (
	            'title' => __ ( 'Title', 'wechatpay' ),
	            'type' => 'text',
	            'description' => __ ( 'This controls the title which the user sees during checkout.', 'wechatpay' ),
	            'default' => __ ( 'WeChatPay', 'wechatpay' ),
	            'css' => 'width:400px'
	        ),
	        'description' => array (
	            'title' => __ ( 'Description', 'wechatpay' ),
	            'type' => 'textarea',
	            'description' => __ ( 'This controls the description which the user sees during checkout.', 'wechatpay' ),
	            'default' => __ ( "Pay via WeChatPay, if you don't have an WeChatPay account, you can also pay with your debit card or credit card", 'wechatpay' ),
	            //'desc_tip' => true ,
	            'css' => 'width:400px'
	        ),
	        'wechatpay_appID' => array (
	            'title' => __ ( 'Application ID', 'wechatpay' ),
	            'type' => 'text',
	            'description' => __ ( 'Please enter the Application ID,If you don\'t have one, <a href="https://pay.weixin.qq.com" target="_blank">click here</a> to get.', 'wechatpay' ),
	            'css' => 'width:400px'
	        ),
	        'wechatpay_mchId' => array (
	            'title' => __ ( 'Merchant ID', 'wechatpay' ),
	            'type' => 'text',
	            'description' => __ ( 'Please enter the Merchant ID,If you don\'t have one, <a href="https://pay.weixin.qq.com" target="_blank">click here</a> to get.', 'wechatpay' ),
	            'css' => 'width:400px'
	        ),
	        'wechatpay_key' => array (
	            'title' => __ ( 'WeChatPay Key', 'wechatpay' ),
	            'type' => 'text',
	            'description' => __ ( 'Please enter your WeChatPay Key; this is needed in order to take payment.', 'wechatpay' ),
	            'css' => 'width:400px',
	            //'desc_tip' => true
	        ),
	        'exchange_rate'=> array (
	            'title' => __ ( 'Exchange Rate', 'wechatpay' ),
	            'type' => 'text',
	            'default'=>1,
	            'description' =>  __ ( "Please set current currency against Chinese Yuan exchange rate, eg if your currency is US Dollar, then you should enter 6.19", 'wechatpay' ),
	            'css' => 'width:80px;',
	            'desc_tip' => true
	        )
	    );
	
	}
	
	public function process_payment($order_id) {
	    $order = new WC_Order ( $order_id );
	    return array (
	        'result' => 'success',
	        'redirect' => $order->get_checkout_payment_url ( true )
	    );
	}
	
	public  function woocommerce_wechatpay_add_gateway( $methods ) {
	    $methods[] = $this;
	    return $methods;
	}
	
	/**
	 * 
	 * @param WC_Order $order
	 * @param number $limit
	 * @param string $trimmarker
	 */
	public  function get_order_title($order,$limit=32,$trimmarker='...'){
	    $id = method_exists($order, 'get_id')?$order->get_id():$order->id;
		$title="#{$id}|".get_option('blogname');
		
		$order_items =$order->get_items();
		if($order_items&&count($order_items)>0){
		    $title="#{$id}|";
		    $index=0;
		    foreach ($order_items as $item_id =>$item){
		        $title.= $item['name'];
		        if($index++>0){
		            $title.='...';
		            break;
		        }
		    }    
		}
		
		return apply_filters('xh_wechat_wc_get_order_title', mb_strimwidth ( $title, 0,32, '...','utf-8'));
	}
	
	public function get_order_status() {
		$order_id = isset($_POST ['orderId'])?$_POST ['orderId']:'';
		$order = new WC_Order ( $order_id );
		$isPaid = ! $order->needs_payment ();
	
		echo json_encode ( array (
		    'status' =>$isPaid? 'paid':'unpaid',
		    'url' => $this->get_return_url ( $order )
		));
		
		exit;
	}
	
	function wp_enqueue_scripts() {
		$orderId = get_query_var ( 'order-pay' );
		$order = new WC_Order ( $orderId );
		$payment_method = method_exists($order, 'get_payment_method')?$order->get_payment_method():$order->payment_method;
		if ($this->id == $payment_method) {
			if (is_checkout_pay_page () && ! isset ( $_GET ['pay_for_order'] )) {
			    
			    wp_enqueue_script ( 'XH_WECHAT_JS_QRCODE', XH_WC_WeChat_URL. '/js/qrcode.js', array (), XH_WC_WeChat_VERSION );
				wp_enqueue_script ( 'XH_WECHAT_JS_CHECKOUT', XH_WC_WeChat_URL. '/js/checkout.js', array ('jquery','XH_WECHAT_JS_QRCODE' ), XH_WC_WeChat_VERSION );
				
			}
		}
	}
	
	public function check_wechatpay_response() {
	    if(defined('WP_USE_THEMES')&&!WP_USE_THEMES){
	        return;
	    }
	    
		$xml = isset($GLOBALS ['HTTP_RAW_POST_DATA'])?$GLOBALS ['HTTP_RAW_POST_DATA']:'';	
		if(empty($xml)){
		    $xml = file_get_contents("php://input");
		}
		
		if(empty($xml)){
		    return ;
		}
		
		$xml = trim($xml);
		if(substr($xml, 0,4) !='<xml'){
		    return;
		}
		
		//排除非微信回调
		if(strpos($xml, 'transaction_id')===false
		    ||strpos($xml, 'appid')===false
		    ||strpos($xml, 'mch_id')===false){
		        return;
		}
		// 如果返回成功则验证签名
		try {
		    $result = WechatPaymentResults::Init ( $xml );
		    if (!$result||! isset($result['transaction_id'])) {
		        return;
		    }
		    
		    $transaction_id=$result ["transaction_id"];
		    $order_id = $result['attach'];
		    
		    $input = new WechatPaymentOrderQuery ();
		    $input->SetTransaction_id ( $transaction_id );
		    $query_result = WechatPaymentApi::orderQuery ( $input, $this->config );
		    if ($query_result['result_code'] == 'FAIL' || $query_result['return_code'] == 'FAIL') {
                throw new Exception(sprintf("return_msg:%s ;err_code_des:%s "), $query_result['return_msg'], $query_result['err_code_des']);
            }
            
            if(!(isset($query_result['trade_state'])&& $query_result['trade_state']=='SUCCESS')){
                throw new Exception("order not paid!");
            }
		  
		    $order = new WC_Order ( $order_id );
		    if($order->needs_payment()){
		          $order->payment_complete ($transaction_id);
		    }
		    
		    $reply = new WechatPaymentNotifyReply ();
		    $reply->SetReturn_code ( "SUCCESS" );
		    $reply->SetReturn_msg ( "OK" );
		    
		    WxpayApi::replyNotify ( $reply->ToXml () );
		    exit;
		} catch ( WechatPaymentException $e ) {
		    return;
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = ''){		
		$order = new WC_Order ($order_id );
		if(!$order){
			return new WP_Error( 'invalid_order','错误的订单' );
		}
	
		$trade_no =$order->get_transaction_id();
		if (empty ( $trade_no )) {
			return new WP_Error( 'invalid_order', '未找到微信支付交易号或订单未支付' );
		}
	
		$total = $order->get_total ();
		//$amount = $amount;
        $preTotal = $total;
        $preAmount = $amount;
        
		$exchange_rate = floatval($this->get_option('exchange_rate'));
		if($exchange_rate<=0){
			$exchange_rate=1;
		}
			
		$total = round ( $total * $exchange_rate, 2 );
		$amount = round ( $amount * $exchange_rate, 2 );
      
        $total = ( int ) ( $total  * 100);
		$amount = ( int ) ($amount * 100);
        
		if($amount<=0||$amount>$total){
			return new WP_Error( 'invalid_order',__('Invalid refused amount!' ,XH_WECHAT) );
		}
	
		$transaction_id = $trade_no;
		$total_fee = $total;
		$refund_fee = $amount;
	
		$input = new WechatPaymentRefund ();
		$input->SetTransaction_id ( $transaction_id );
		$input->SetTotal_fee ( $total_fee );
		$input->SetRefund_fee ( $refund_fee );
	
		$input->SetOut_refund_no ( $order_id.time());
		$input->SetOp_user_id ( $this->config->getMCHID());
	
		try {
			$result = WechatPaymentApi::refund ( $input,60 ,$this->config);
			if ($result ['result_code'] == 'FAIL' || $result ['return_code'] == 'FAIL') {
				Log::DEBUG ( " XHWechatPaymentApi::orderQuery:" . json_encode ( $result ) );
				throw new Exception ("return_msg:". $result ['return_msg'].';err_code_des:'. $result ['err_code_des'] );
			}
	
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_order',$e->getMessage ());
		}
	
		return true;
	}

	/**
	 * 
	 * @param WC_Order $order
	 */
	function receipt_page($order_id) {
	    $order = new WC_Order($order_id);
	    if(!$order||$order->is_paid()){
	       return;
	    }
	    
		$input = new WechatPaymentUnifiedOrder ();
		$input->SetBody ($this->get_order_title($order) );
	
		$input->SetAttach ( $order_id );
		$input->SetOut_trade_no ( md5(date ( "YmdHis" ).$order_id ));    
		$total = $order->get_total ();
        
		$exchange_rate = floatval($this->get_option('exchange_rate'));
		if($exchange_rate<=0){
		    $exchange_rate=1;
		}
		
		$total = round ($total * $exchange_rate, 2 );
        $totalFee = ( int ) ($total * 100);
        
		$input->SetTotal_fee ( $totalFee );
		
		$date = new DateTime ();
		$date->setTimezone ( new DateTimeZone ( 'Asia/Shanghai' ) );
		$startTime = $date->format ( 'YmdHis' );
		$input->SetTime_start ( $startTime );
		$input->SetNotify_url (get_option('siteurl') );
	
		$input->SetTrade_type ( "NATIVE" );
		$input->SetProduct_id ($order_id );
		try {
		    $result = WechatPaymentApi::unifiedOrder ( $input, 60, $this->config );
		} catch (Exception $e) {
		    echo $e->getMessage();
		    return;
		}
		$error_msg=null;
		if((isset($result['result_code'])&& $result['result_code']=='FAIL')
		    ||
		    (isset($result['return_code'])&&$result['return_code']=='FAIL')){
		    
		    $error_msg =  "return_msg:".$result['return_msg']." ;err_code_des: ".$result['err_code_des'];
	
		}
		
		$url =isset($result['code_url'])? $result ["code_url"]:'';
		
		echo  '<input type="hidden" id="xh-wechat-payment-pay-url" value="'.$url.'"/>';
		
		?>
		<style type="text/css">

        .pay-weixin-design{ display: block;background: #fff;/*padding:100px;*/overflow: hidden;}
          .page-wrap {padding: 50px 0;min-height: auto !important;  }
          .pay-weixin-design #WxQRCode{width:196px;height:auto}
          .pay-weixin-design .p-w-center{ display: block;overflow: hidden;margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;}
          .pay-weixin-design .p-w-center h3{    font-family: Arial,微软雅黑;margin: 0 auto 10px;display: block;overflow: hidden;}
          .pay-weixin-design .p-w-center h3 font{ display: block;font-size: 14px;font-weight: bold;    float: left;margin: 10px 10px 0 0;}
          .pay-weixin-design .p-w-center h3 strong{position: relative;text-align: center;line-height: 40px;border: 2px solid #3879d1;display: block;font-weight: normal;width: 130px;height: 44px; float: left;}
          .pay-weixin-design .p-w-center h3 strong #img1{margin-top: 10px;display: inline-block;width: 22px;vertical-align: top;}
          .pay-weixin-design .p-w-center h3 strong span{    display: inline-block;font-size: 14px;vertical-align: top;}
          .pay-weixin-design .p-w-center h3 strong #img2{    position: absolute;right: 0;bottom: 0;}
          .pay-weixin-design .p-w-center h4{font-family: Arial,微软雅黑;      margin: 0; font-size: 14px;color: #666;}
          .pay-weixin-design .p-w-left{ display: block;overflow: hidden;float: left;}
          .pay-weixin-design .p-w-left p{ display: block;width:196px;background:#00c800;color: #fff;text-align: center;line-height:2.4em; font-size: 12px; }
          .pay-weixin-design .p-w-left img{ margin-bottom: 10px;}
          .pay-weixin-design .p-w-right{ margin-left: 50px; display: block;float: left;}
        </style>
        		
        <div class="pay-weixin-design">
        
             <div class="p-w-center">
                <h3>
        		   <font>支付方式已选择微信支付</font>
        		   <strong>
        		      <img id="img1" src="<?php print XH_WC_WeChat_URL?>/images/weixin.png">
        			  <span>微信支付</span>
        			  <img id="img2" src="<?php print XH_WC_WeChat_URL?>/images/ep_new_sprites1.png">
        		   </strong>
        		</h3>
        	    <h4>通过微信首页右上角扫一扫，或者在“发现-扫一扫”扫描二维码支付。本页面将在支付完成后自动刷新。</h4>
        	    <span style="color:red;"><?php print $error_msg?></span>
        	 </div>
        		
             <div class="p-w-left">		  
        		<div  id="xh-wechat-payment-pay-img" style="width:200px;height:200px;padding:10px;" data-oid="<?php echo $order_id;?>"></div>
        		<p>使用微信扫描二维码进行支付</p>
        		
             </div>
        
        	 <div class="p-w-right">
        
        	    <img src="<?php print XH_WC_WeChat_URL?>/images/ep_sys_wx_tip.jpg">
        	 </div>
        
        </div>
		
		<?php 
	}
}

?>
