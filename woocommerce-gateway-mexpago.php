<?php
/**
 * Plugin Name: MexPago Pasarela de Pago para WC
 * Description: Habilitar MexPago como un método de pago directo válido para Woocomerce.
 * Author: MexPago
 * Author URI: http://www.mexpago.com/
 * Version: 1.0.2
 * Text Domain: wc-gateway-mexpago
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2020 MexPago, MexPago and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   wc-gateway-mexpago
 * @author    MexPago
 * @category  Admin
 * @copyright Copyright (c) 2020, MexPago, MexPago and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_mexpago_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Mexpago';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_mexpago_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_mexpago_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gateway_mexpago' ) . '">' . __( 'Configuración', 'wc-gateway-mexpago' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_mexpago_gateway_plugin_links' );


/**
 * MexPago Payment Gateway
 *
 * Provides a MexPago Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Mexpago
 * @extends		WC_Payment_Gateway
 * @version		1.0.1
 * @package		WooCommerce/Classes/Payment
 * @author 		MexPago
 */
add_action( 'plugins_loaded', 'wc_mexpago_gateway_init', 11 );

function wc_mexpago_gateway_init() {
	class WC_Gateway_Mexpago extends WC_Payment_Gateway {

		public function __construct() {
	  
			$this->id                 = 'mexpago';
			$this->plugin_url		  = $this->plugin_url();
			//$this->icon               = apply_filters('woocommerce_mexpago_icon', $this->plugin_url.'/assets/images/mexpago-154x32.png');
			$this->has_fields         = false;
			$this->method_title       = __( 'MexPago', 'wc-gateway-mexpago' );
			$this->method_description = __( 'Permite realizar pagos a través de MexPago.', 'wc-gateway-mexpago' );
			
			$icon_html =  'https://www.mexpago.com/img/mexpago-154x32.png" /><a class="about_paypal" onclick="javascript:window.open(\'https://www.mexpago.com\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__( '¿Qué es MexPago?', 'woocommerce' ) . '</a><img';
			$this->icon               = apply_filters('woocommerce_mexpago_icon', $icon_html, $this->id);
			
			global $wp;
			$this->homeurl 			  = home_url($wp->request).'/wc-api/'.$this->id;
						
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );
			$this->instructions_efectivo = $this->get_option( 'instructions_efectivo' );
			$this->llave		= $this->get_option( 'llave_comercio' );
			$this->llavepr		= $this->get_option( 'llaveprv_comercio' );
			$this->modo_prueba	= $this->get_option( 'modo_prueba' );
			$this->tipo_pago	= '';
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
						
			// Callback
            add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_callback' ) );
			add_action( 'woocommerce_api_' . $this->id .'_efectivo', array( $this, 'check_callback_efectivo' ) );
			
		}

	
		/**
		 * Initialize Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_mexpago_form_fields', array(
				'enabled' => array(
					'title'   => __( 'Habilitar/Deshabilitar', 'wc-gateway-mexpago' ),
					'type'    => 'checkbox',
					'label'   => __( 'Habilitar MexPago', 'wc-gateway-mexpago' ),
					'default' => 'yes'),
				'title' => array(
					'title'       => __( 'Título', 'wc-gateway-mexpago' ),
					'type'        => 'text',
					'description' => __( 'Título para el método de pago que visualiza el cliente durante el checkout.', 'wc-gateway-mexpago' ),
					'default'     => __( 'MexPago', 'wc-gateway-mexpago' ),
					'desc_tip'    => true),
				'description' => array(
					'title'       => __( 'Descripción', 'wc-gateway-mexpago' ),
					'type'        => 'textarea',
					'description' => __( 'Descripción para el método de pago que visualiza el cliente durante el checkout.', 'wc-gateway-mexpago' ),
					'default'     => __( 'Por favor realiza el pago a través del portal MexPago.', 'wc-gateway-mexpago' ),
					'desc_tip'    => true),				
				'instructions' => array(
					'title'       => __( 'Instrucciones', 'wc-gateway-mexpago' ),
					'type'        => 'textarea',
					'description' => __( 'Instrucciones que son agregadas en la pagina de cierre de pedido.', 'wc-gateway-mexpago' ),
					'default'     => __( 'El pago ha sido validado.', 'wc-gateway-mexpago' ),
					'desc_tip'    => true),	
				'instructions_efectivo' => array(
					'title'       => __( 'Instrucciones pago Efectivo', 'wc-gateway-mexpago' ),
					'type'        => 'textarea',
					'description' => __( 'Instrucciones que son agregadas en la pagina de cierre de pedido en caso de Pago en efectivo OXXO', 'wc-gateway-mexpago' ),
					'default'     => __( 'Orden en espera de pago en tiendas OXXO.', 'wc-gateway-mexpago' ),
					'desc_tip'    => true),					
				'modo_prueba' => array(
					'title'   => __( 'Habilitar/Deshabilitar modo de prueba', 'wc-gateway-mexpago' ),
					'type'    => 'checkbox',
					'label'   => __( 'Habilitar MexPago en modo de prueba', 'wc-gateway-mexpago' ),
					'default' => 'yes'),
				'llave_comercio' => array(
					'title'       => __( 'Llave MexPago', 'wc-gateway-mexpago' ),
					'type'        => 'text',
					'description' => __( 'Llave de acceso a MexPago', 'wc-gateway-mexpago' ),
					'default'     => '',
					'desc_tip'    => true),
				'llaveprv_comercio' => array(
					'title'       => __( 'Llave privada MexPago', 'wc-gateway-mexpago' ),
					'type'        => 'text',
					'description' => __( 'Llave privada de acceso a MexPago', 'wc-gateway-mexpago' ),
					'default'     => '',
					'desc_tip'    => true),
				'url_retorno' => array(
					'title'       => __( 'Url retorno a WooCommerce', 'wc-gateway-mexpago' ),
					'type'        => 'text',
					'class'		  => 'disabled',
					'description' => __( 'Url retorno a WooCommerce', 'wc-gateway-mexpago' ),
					'default'     => $this->homeurl,
					'desc_tip'    => true
					),
			) );
		}
		
		
		
		
		/**
		 * Obtiene datos de orden y configuraciones
		 * @param int $order_id
		 * @return strin $url_code
		 */
		public function generar_url_mexpago($order_id){
			
			$redirect_url = '';
			$orderwc = '';
			$parametros = '';
			$jsonArticulos = '';
			$url_code ='';
			$decimals=2;
			
			// Obtener una instancia WC_Order de la orden recibida
			$orderwc = wc_get_order($order_id);		
			$ordertotalwc = $orderwc->get_total();
			
			$fecha= date('Y-m-d H:i:s');

			//Obtener configuraciones
			$llavemx = $this->get_option('llave_comercio');
			
			
			if( $this->get_option('modo_prueba') == 'yes' ){
				$redirect_url = 'https://dev.mexpago.com/app/pagoOnline';		
				
			}
			else {
				$redirect_url = 'https://mexpago.com/app/pagoOnline';		
				
			}
			
							
			$parametros.= 'monto='.urlencode ($ordertotalwc);
			$parametros.= '&noTransaccion='.urlencode ($order_id);
			$parametros.= '&fecha='.urlencode ($fecha);
			$parametros.= '&llave='.urlencode ($llavemx);	
			$parametros.= '&canal='.urlencode ('WooCommerce');	
			$parametros.= '&articulos=';			
			
			$jsonArticulos.= '{"articulos":[';		

			foreach ($orderwc->get_items() as $item_id => $item_data) {
				$product = $item_data->get_product();
				$product_name = $product->get_name();
				$item_total = $item_data->get_total();
				
				$jsonArticulos.='{"descripcion":"'.$product_name.'",';
				$jsonArticulos.='"monto":"'.$item_total.'"},'; 
			}
			
			if((version_compare(WC_VERSION,'3.0','<')?$orderwc->get_total_shipping():$orderwc->get_shipping_total())>0){
				$jsonArticulos.='{"descripcion":"Envío",';
				$jsonArticulos.='"monto":"'.round((version_compare(WC_VERSION,'3.0','<')?$orderwc->get_total_shipping():$orderwc->get_shipping_total()), $decimals ).'"},'; 
			}
			
			if (sizeof($orderwc-> get_tax_totals()) > 0) {
				foreach ($orderwc-> get_tax_totals() as $item) {
					$jsonArticulos.='{"descripcion":"'.$item->label.'",';
					$jsonArticulos.='"monto":"'.$item->amount.'"},'; 
				}
			}			
			
			$jsonArticulos= substr($jsonArticulos, 0, -1).']}';
			$jsonArticulos= urlencode ($jsonArticulos);
			
			$url_code= $redirect_url.'?'.$parametros.$jsonArticulos;
			
			return $url_code;
		}
	
		/**
		 * Redirecciona a MexPago al generar el pedido desde WooCommerce
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
				
			$url_mx_code=$this->generar_url_mexpago($order_id);
			
			return array(
				'result' 	=> 'success',
				'redirect'	=> $url_mx_code
			);
		}
		
		/**
		 * Retorna string de timezone PHP que se encuentra configurado
		 * @return string PHP timezone valido
		 */
		public function wp_get_timezone() {

			// if site timezone string exists, return it
			if ( $timezone = get_option( 'timezone_string' ) )
				return $timezone;

			// get UTC offset, if it isn't set then return UTC
			if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) )
				return 'UTC';

			// adjust UTC offset from hours to seconds
			$utc_offset *= 3600;

			// attempt to guess the timezone string from the UTC offset
			if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
				return $timezone;
			}

			// last try, guess timezone string manually
			$is_dst = date( 'I' );

			foreach ( timezone_abbreviations_list() as $abbr ) {
				foreach ( $abbr as $city ) {
					if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
						return $city['timezone_id'];
				}
			}
			
			// fallback to UTC
			return 'UTC';
		}
		
		/**
		 * Callback desde MexPago con el pago realizado.
		 */
		public function check_callback() {
			
			global $woocommerce;
			
			//obtener datos de entrada
			if(isset($_GET['numeroTransaccion'])){
				$numeroTransaccion = sanitize_text_field($_GET['numeroTransaccion']);
			}
			$order = new WC_Order($numeroTransaccion);
			$montowc = $order->get_total();
			
			$estatusPago= 'estauspago';
			if(isset($_GET['pago'])){
				$estatusPago = sanitize_text_field($_GET['pago']);
			}	
			
			if(isset($_GET['tipoPago'])){
				$this->tipo_pago = sanitize_text_field($_GET['tipoPago']);
			}
			
			
			if ($order->get_status() == 'completed'||$order->get_status() == 'processing'){
				
				// redireccionar a confirmación de pedido WC 
					$urllocation = 'Location: '.$order->get_checkout_order_received_url();			
					header($urllocation);
				
			}else{
				
				if($estatusPago=='aprobado' || ($estatusPago == 'pendiente' && $this->tipo_pago == 'efectivoOxxo')){
					//obtener fecha de la orden WC
					$fecha= $order->get_date_created('view');

					//obtener zona horaria configurada WC
					$zonah_wc= $this->wp_get_timezone();

					try {
						// obtener objeto datetime de la zona horaria configurada WC
						$fechawc = new DateTime( $fecha, new DateTimeZone($zonah_wc));
						
						//convertir a CDMX (GMT-5) 
						$fechawc->setTimezone(new DateTimeZone('America/Mexico_City'));
						$fechawcFormat = date_format($fechawc,"Y-m-d");
					} catch ( Exception $e ) {
						
					}
					
					//obtener configuraciones
					if( $this->get_option('modo_prueba') == 'yes' ){
						$url = 'https://dev.mexpago.com/rest/APIWEB/transaccion/validarNoTransaccion';		
					}
					else {
						$url = 'https://mexpago.com/rest/APIWEB/transaccion/validarNoTransaccion';		
					}
					
									
					$llavemx = $this->get_option('llave_comercio');		
					$llavepr = $this->get_option('llaveprv_comercio');
					
					//Validacion API MexPago
					$jsonParms='{"llave":"'.$llavemx.'",';
					$jsonParms.='"llavePrivada":"'.$llavepr.'",';
					$jsonParms.='"noTransaccion":"'.$numeroTransaccion.'",';
					$jsonParms.='"fecha":"'.$fechawcFormat.'"}'; 
					
					$response = wp_remote_post( $url, array(
						'method'      => 'POST',
						'timeout'     => 45,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,
						'headers'     => array('Authorization' => 'Basic Og==','Content-Type' => 'application/json'),
						'body'        => $jsonParms,
						'cookies'     => array()
						)
					);
					 
					if ( is_wp_error( $response ) ) {
						// redireccionar a checkout WC 
						wp_safe_redirect(wc_get_checkout_url());
						exit;
						
					} else {
						$body = wp_remote_retrieve_body( $response );
						$jsonp = json_decode($body);		
						
						if(isset($jsonp->estatus)){
							$estatusVal = sanitize_text_field($jsonp->estatus);
						}
						if(isset($jsonp->monto)){
							$montoVal = sanitize_text_field($jsonp->monto);
						}
						
						if ($estatusVal=='APROBADA' && $montoVal==$montowc){
							
							// limpiar carrito 
							WC()->cart->empty_cart();			
						
							// actualizar estatus pagado woocomerce
							$order->payment_complete();
							
							$prodvitdesc=0;
							$cantprod=0;
							
							foreach ($order->get_items() as $item_id => $item_data) {
								$product = $item_data->get_product();
								$product_virtual = $product->is_virtual();
								$product_descargable = $product->is_downloadable();
								
								if($product_virtual && $product_descargable) {
									$prodvitdesc = $prodvitdesc+1;
								}
								$cantprod = $cantprod+1;
							}
							
							if($cantprod == $prodvitdesc){
								$order->update_status( 'completed','',false);
							}						
							
							
							// redireccionar a confirmación de pedido WC 
							$urllocation = 'Location: '.$order->get_checkout_order_received_url();			
							header($urllocation);
							
						}else if ($estatusVal=='Pendiente' && $montoVal==$montowc && ($estatusPago == 'pendiente' && $this->tipo_pago == 'efectivoOxxo')){
							
							//Agregar campo con tipo de pago
							$order->update_meta_data('tipoPagoMexPago', $this->tipo_pago);
							$order->save();
												
							$order->update_status( 'on-hold','Espera pago OXXO',false);
							
							$urllocation = 'Location: '.$order->get_checkout_order_received_url();			
							header($urllocation);
							exit;
							
						}else{
							
							// redireccionar a checkout WC 
							wp_safe_redirect(wc_get_checkout_url());
							exit;
						}
					}
				}else if($estatusPago!='aprobado' && $this->tipo_pago != 'efectivoOxxo'){
					//redireccionar a checkout WC 
					wp_safe_redirect(wc_get_checkout_url());
					exit;					
				}
			}
		}
		
		/**
		 * Callback desde MexPago posteo pago efectivo.
		 */
		public function check_callback_efectivo() {
			
			global $woocommerce;
			
			//obtener datos de entrada
			if(isset($_GET['numeroTransaccion'])){
				$numeroTransaccion = sanitize_text_field($_GET['numeroTransaccion']);
			}
			
			$order = new WC_Order($numeroTransaccion);
			//$order->update_status( 'completed','posteo pago',false);
			
			
			$montowc = $order->get_total();
			
			$estatusPago= 'estatuspago';
			if(isset($_GET['pago'])){
				$estatusPago = sanitize_text_field($_GET['pago']);
			}	
			
			if(isset($_GET['tipoPago'])){
				$this->tipo_pago = sanitize_text_field($_GET['tipoPago']);
			}			
			
			if ($order->get_status() == 'on-hold' && $estatusPago=='aprobado' && $this->tipo_pago == 'efectivoOxxo'){
				
				//obtener fecha de la orden WC
				$fecha= $order->get_date_created('view');

				//obtener zona horaria configurada WC
				$zonah_wc= $this->wp_get_timezone();

				try {
					// obtener objeto datetime de la zona horaria configurada WC
					$fechawc = new DateTime( $fecha, new DateTimeZone($zonah_wc));
					
					//convertir a CDMX (GMT-5) 
					$fechawc->setTimezone(new DateTimeZone('America/Mexico_City'));
					$fechawcFormat = date_format($fechawc,"Y-m-d");
				} catch ( Exception $e ) {
					
				}
				
				//obtener configuraciones
				if( $this->get_option('modo_prueba') == 'yes' ){
					$url = 'https://dev.mexpago.com/rest/APIWEB/transaccion/validarNoTransaccion';
				}
				else {
					$url = 'https://mexpago.com/rest/APIWEB/transaccion/validarNoTransaccion';
				}
				
				
								
				$llavemx = $this->get_option('llave_comercio');		
				$llavepr = $this->get_option('llaveprv_comercio');
				
				//Validacion API MexPago
				$jsonParms='{"llave":"'.$llavemx.'",';
				$jsonParms.='"llavePrivada":"'.$llavepr.'",';
				$jsonParms.='"noTransaccion":"'.$numeroTransaccion.'",';
				$jsonParms.='"fecha":"'.$fechawcFormat.'"}'; 
				
				$response = wp_remote_post( $url, array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array('Authorization' => 'Basic Og==','Content-Type' => 'application/json'),
					'body'        => $jsonParms,
					'cookies'     => array()
					)
				);
				 
				if ( is_wp_error( $response ) ) {
					exit;
					
				} else {
					
					$body = wp_remote_retrieve_body( $response );
					$jsonp = json_decode($body);		
					
					if(isset($jsonp->estatus)){
						$estatusVal = sanitize_text_field($jsonp->estatus);
					}
					if(isset($jsonp->monto)){
						$montoVal = sanitize_text_field($jsonp->monto);
					}
					
					if ($estatusVal=='APROBADA' && $montoVal==$montowc){
						
						// limpiar carrito 
						WC()->cart->empty_cart();			
					
						// actualizar estatus pagado woocomerce
						$order->payment_complete();
						
						$prodvitdesc=0;
						$cantprod=0;
						
						foreach ($order->get_items() as $item_id => $item_data) {
							$product = $item_data->get_product();
							$product_virtual = $product->is_virtual();
							$product_descargable = $product->is_downloadable();
							
							if($product_virtual && $product_descargable) {
								$prodvitdesc = $prodvitdesc+1;
							}
							$cantprod = $cantprod+1;
						}
						
						if($cantprod == $prodvitdesc){
							$order->update_status( 'completed','Posteo MexPago virtuales descargables',false);
							$order->add_order_note( 'Posteo MexPago virtuales descargables','',false);
							$order->save();
							
						}else{
							$order->update_status( 'processing','Posteo MexPago',false);
							$order->add_order_note( 'Posteo MexPago','',false);
							$order->save();
							
						}		
					}else{
						exit;
					}
				}
			}else{
				exit;
			}
			
		}
		
		
		/**
		 * Agregar datos configurados a página confirmación de pedido WC .
		 */
		public function thankyou_page($order_id) {
			
			global $woocommerce;
			$orderTK = new WC_Order($order_id);
			
			$vartipoPago = '';
			$vartipoPago = get_post_meta($order_id,'tipoPagoMexPago',true);
			
			if ( $this->instructions && $vartipoPago != 'efectivoOxxo' ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}else if ( $this->instructions_efectivo && $vartipoPago == 'efectivoOxxo' ) {
				echo wpautop( wptexturize( $this->instructions_efectivo ) );
			}			
		}
		
		/**
		 * Agregar datos configurados a página confirmación de pedido WC .
		 */
		public function thankyou_page_efectivo_OXXO() {
			
			
			
		}
		
		function plugin_url() {
			return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
		}
	
  } // end \WC_Gateway_Mexpago class
}