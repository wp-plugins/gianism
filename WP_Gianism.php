<?php
/**
 * Utility Class for WP Gianism.
 * @package wp_gianism
 */
class WP_Gianism{
	
	/**
	 * @var Facebook_Controller
	 */
	public $fb = null;
	
	/**
	 * @var Twitter_Controller
	 */
	public $twitter = null;
	
	/**
	 * @var Google_Controller
	 */
	public $google = null;
	
	/**
	 * @var string
	 */
	private $version;
	
	/**
	 * @var string
	 */
	protected $name = "wp_gianism";
	
	/**
	 * @var string
	 */
	private $domain = 'wp-gianism';
	
	/**
	 * @var string
	 */
	public $dir;
	
	/**
	 * @var string
	 */
	public $url;
	
	/**
	 * @var array
	 */
	protected $admin_error = array();
	
	/**
	 * @var array
	 */
	protected $admin_message = array();
	
	/**
	 * @var array
	 */
	protected $container = array();
	
	/**
	 * @var array
	 */
	public $option = array();
	
	/**
	 * @var string
	 */
	public $message_post_type = 'gianism_message';
	
	/**
	 * オプション初期値
	 * @var array
	 */
	protected $default_option = array(
		'fb_enabled' => 0,
		'fb_app_id' => '',
		'fb_app_secret' => '',
		'fb_fan_gate' => 0,
		'tw_enabled' => 0,
		"tw_screen_name" => "",
		"tw_consumer_key" => "",
		"tw_consumer_secret" => "",
		"tw_access_token" => "",
		"tw_access_token_secret" => "",
		"ggl_enabled" => 0,
		"ggl_consumer_key" => "",
		"ggl_consumer_secret" => "",
		"ggl_redirect_uri" => ""
	);
	
	/**
	 * 
	 * @param string $base_file
	 * @param string $version 
	 */
	public function __construct($base_file, $version) {
		//Setup Configs
		$this->dir = plugin_dir_path($base_file);
		$this->url = plugin_dir_url($base_file);
		$this->version = $version;
		$saved_option = get_option($this->name."_option");
		foreach($this->default_option as $key => $value){
			if(!isset($saved_option[$key])){
				$this->option[$key] = $value;
			}else{
				$this->option[$key] = $saved_option[$key];
			}
		}
		//Register Hooks
		add_action("init", array($this, "init"));
		add_action("admin_init", array($this, "admin_init"));
		add_action("admin_menu", array($this, "admin_menu"));
		add_action("admin_notice", array($this, "admin_notice"));
		//Add i18n
		load_plugin_textdomain($this->domain, false, basename($this->dir).DIRECTORY_SEPARATOR."language");
	}
	
	/**
	 * Common Hook
	 */
	public function init(){
		//Facebook
		if($this->is_enabled('facebook')){
			require_once $this->dir."/sdks/facebook/facebook_controller.php";
			$this->fb = new Facebook_Controller($this->option);
		}
		//Twitter
		if($this->is_enabled('twitter')){
			require_once $this->dir."/sdks/twitter/twitter_controller.php";
			$this->twitter = new Twitter_Controller($this->option);
		}
		//Google
		if($this->is_enabled("google")){
			require_once $this->dir."/sdks/google/google_controller.php";
			$this->google = new Google_Controller($this->option);
		}
		if($this->is_enabled()){
			//Create Post type
			$this->create_message_post_type();
			//Show Login button on profile page
			add_action('show_user_profile', array($this, 'show_user_profile'));
			add_action('show_user_profile', array($this, 'show_direct_message'));
			//Show Login button on login page
			add_action('login_form', array($this, 'show_login_form'));
			//Show Register button on Register page
			add_action('register_form', array($this, 'show_regsiter_form'));
			//Load CSS
			add_action('admin_print_styles', array($this, 'enqueue_style'));
			add_action('wp_print_styles', array($this, 'enqueue_style'));
			//Add Ajax Action
			add_action('wp_ajax_wpg_ajax', array($this, 'ajax'));
		}
		//Add Assets
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
	}
	
	public function create_message_post_type(){
		register_post_type($this->message_post_type,array(
			'public' => false
		));
	}
	
	public function show_direct_message(){
		global $user_ID, $user_email, $user_identity;
		if(!defined("IS_PROFILE_PAGE")){
			return;
		}
		?>
		<h3><?php printf($this->_('Message to %s'), $user_identity); ?></h3>
		<?php
		$query = new WP_Query("post_type={$this->message_post_type}&author={$user_ID}&posts_per_page=-1&post_status=publish,private");
		if($query->have_posts()): ?>
		<div class="wpg-message">
			<table class="form-table">
				<tbody>
		<?php while($query->have_posts()): $query->the_post(); ?>
					<tr>
						<th>
							<?php the_title(); ?><br />
							<small><?php the_time('Y-m-d H:i:s'); ?></small>
						</th>
						<td><?php the_content(); ?></td>
						<td>
							<a href="#<?php the_ID(); ?>" class="button delete"><?php $this->e('Delete'); ?></a>
						</td>
					</tr>
		<?php endwhile; wp_reset_query(); ?>
				</tbody>
			</table>
		</div>
		<?php else:
			printf("<p>%s</p>", $this->_('No message.'));
		endif;
	}
	
	/**
	 * Public Hook
	 */
	public function template_redirect(){
		
	}
	
	
	
	/**
	 * Action hook for admin panel.
	 */
	public function admin_init(){
		//Execute when option updated.
		if($this->verify_nonce('option')){
			$this->option = array(
				'fb_enabled' => ($this->post('fb_enabled') == 1) ? 1 : 0,
				'fb_app_id' => (string)$this->post('fb_app_id'),
				'fb_app_secret' => (string)$this->post('fb_app_secret'),
				'fb_fan_gate' => (int)$this->post('fb_fan_gate'),
				'tw_screen_name' => (string)$this->post('tw_screen_name'),
				'tw_enabled' => (string)($this->post('tw_enabled') == 1) ? 1 : 0,
				"tw_consumer_key" => (string)$this->post('tw_consumer_key'),
				"tw_consumer_secret" => (string)$this->post('tw_consumer_secret'),
				"tw_access_token" => (string)$this->post('tw_access_token'),
				"tw_access_token_secret" => (string)$this->post('tw_access_token_secret'),
				'ggl_enabled' => ($this->post('ggl_enabled') == 1) ? 1 : 0,
				"ggl_consumer_key" => (string)$this->post('ggl_consumer_key'),
				"ggl_consumer_secret" => (string)$this->post('ggl_consumer_secret'),
				"ggl_redirect_uri" => (string)$this->post('ggl_redirect_uri')
			);
			if(update_option("{$this->name}_option", $this->option)){
				$this->add_message($this->_('Option updated.'));
			}else{
				$this->add_message($this->_('Option failed to update.'), true);
			}
		}
	}
	
	/**
	 * Hook for admin_menu
	 * @return void
	 */
	public function admin_menu(){
		add_users_page($this->_('External Service'), $this->_("External Service"), 'edit_users', 'gianism', array($this, 'render'));
		//Create plugin link
		add_filter('plugin_action_links', array($this, 'plugin_page_link'), 10, 2);
		add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
		
	}
	
	/**
	 * Render options page
	 * @return void
	 */
	public function render(){
		require_once $this->dir.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."setting.php";
	}
	
	/**
	 * Render Profile Options
	 * @param WP_User $profileuser
	 * @return void
	 */
	public function show_user_profile($profileuser){
		?>
		<h3><?php $this->e('External Service'); ?></h3>
		<table class="form-table">
			<tbody>
				<?php do_action('gianism_user_profile', $profileuser);?>
			</tbody>
		</table>
		<?php
	}
	
	/**
	 * Show Login Form
	 * @return void
	 */
	public function show_login_form(){
		?>
		<p id="wpg-login">
			<?php do_action('gianism_login_form');?>
		</p>
		<?php
	}
	
	/**
	 * Show registeration button on register form.
	 * @return void
	 */
	public function show_regsiter_form(){
		?>
		<p id="wpg-regsiter">
			<?php do_action('gianism_regsiter_form');?>
		</p>
		<?php
	}
	
	/**
	 * Enqueue Javascripts on admin panel
	 * @param string $hook
	 */
	public function enqueue_scripts($hook){
		wp_enqueue_script('jquery');
		if(defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE){
			wp_enqueue_script('wpg-ajax', $this->url."/assets/message-manager.js", array('jquery'), $this->version);
			wp_localize_script('wpg-ajax', 'WPG', array(
				'endpoint' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('wpg_ajax'),
				'action' => 'wpg_ajax',
				'deleteConfirm' => $this->_('You really delete this message?'),
				'deleteFailed' => $this->_('You cannot delete this message.'),
				'deleteComplete' => $this->_('No message.')
			));
		}
	}
	
	/**
	 * Manage Ajax Request
	 * @global int $user_ID 
	 */
	public function ajax(){
		global $user_ID;
		if(wp_verify_nonce($this->request('_wpnonce'), 'wpg_ajax')){
			switch($this->request('type')){
				default:
					$post = wp_get_single_post($this->request('post_id'));
					$json = array('status' => false);
					if($post && $post->post_author == $user_ID){
						wp_delete_post($post->ID);
						$json['status'] = true;
					}
					header('Content-Type: application/json; charset=utf-8');
					echo json_encode($json);
					die();
					break;
			}
		}
	}
	
	/**
	 * Enqueue CSS on both Public and Admin
	 */
	public function enqueue_style(){
		if($this->is_enabled()){
			wp_enqueue_style($this->name, $this->url."/assets/gianism-style.css", array(), $this->version);
		}
	}
	
	/**
	 * Returns whether service is enbaled.
	 * @param string $service
	 * @return boolean
	 */
	public function is_enabled($service = 'any'){
		$flg = false;
		switch($service){
			case "facebook":
				$flg = (boolean)$this->option['fb_enabled'];
				break;
			case "twitter":
				$flg = (boolean)$this->option['tw_enabled'];
				break;
			case "google":
				$flg = (boolean)$this->option['ggl_enabled'];
				break;
			default:
				$flg = (boolean)($this->option['fb_enabled'] || $this->option['tw_enabled']);
				break;
		}
		return $flg;
	}
	
	/**
	 * Returns appropriate redirect url
	 * @param string $default
	 * @param string $redirect_to
	 * @return string
	 */
	public function sanitize_redirect_to($default, $redirect_to){
		if(!empty($redirect_to)){
			$url = (string) $redirect_to;
			//Check if it has schema
			$url_splited = explode('://', $redirect_to);
			if(count($url_splited) > 1){
				$server_name = str_replace(".", '\.', $_SERVER['SERVER_NAME']);
				//has schema and in same domain.
				if(preg_match("/^https?(:\/\/{$server_name}[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/", $url)){
					$default = $url;
				}
			}else{
				//no schema and not started with absolute external path.
				if(!preg_match("/^\/\//", $url) && preg_match("/^([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/", $url)){
					$default = $url;
				}
			}
		}
		return (string) $default;
	}
	
	
	/**
	 * $_GETに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function get($key){
		if(isset($_GET[$key])){
			return $_GET[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * $_POSTに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function post($key){
		if(isset($_POST[$key])){
			return $_POST[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * $_REQUESTに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function request($key){
		if(isset($_REQUEST[$key])){
			return $_REQUEST[$key];
		}else{
			return null;
		}
	}
	
	
	/**
	 * nonce用に接頭辞をつけて返す
	 * @param string $action
	 * @return string
	 */
	public function nonce_action($action){
		return $this->name."_".$action;
	}
	
	/**
	 * wp_nonce_fieldのエイリアス
	 * @param type $action 
	 */
	public function nonce_field($action){
		wp_nonce_field($this->nonce_action($action), "_{$this->name}_nonce");
	}
	
	/**
	 * nonceが合っているか確かめる
	 * @param string $action
	 * @param string $referrer
	 * @return boolean
	 */
	public function verify_nonce($action, $referrer = false){
		if($referrer){
			return ( (wp_verify_nonce($this->request("_{$this->name}_nonce"), $this->nonce_action($action)) && $referrer == $this->request("_wp_http_referer")) );
		}else{
			return wp_verify_nonce($this->request("_{$this->name}_nonce"), $this->nonce_action($action));
		}
	}
	
		
	/**
	 * 管理画面にメッセージを表示する
	 * @return void
	 */
	public function admin_notice(){
		if(!empty($this->admin_error)){
			?><div class="error"><?php
			foreach($this->admin_error as $err){
				?><p><?php echo $err; ?></p><?php
			}
			?></div><?php
		}
		if(!empty($this->admin_message)){
			?><div class="updated"><?php
			foreach($this->admin_message as $message){
				?><p><?php echo $message; ?></p><?php
			} ?></div><?php
		}
	}
	
	/**
	 * 管理画面に表示するメッセージを追加する
	 * @param string $string
	 * @param boolean $error (optional) trueにするとエラーメッセージ
	 * @return void
	 */
	public function add_message($string, $error = false){
		if($error){
			$this->admin_error[] = (string) $string;
		}else{
			$this->admin_message[] = (string) $string;
		}
	}
	
	/**
	 * WordPressの_eのエイリアス
	 * @param string $text
	 * @return void
	 */
	public function e($text){
		_e($text, $this->domain);
	}
	
	/**
	 * WordPressの__のエイリアス
	 * @param string $text
	 * @return string
	 */
	public function _($text){
		return __($text, $this->domain);
	}
	
	/**
	 * For not called gettext
	 */
	private function ___(){
		$this->_('Connect user accounts with major web services like Facebook, twitter, etc. Stand on the shoulders of giants!');
	}
	
	/**
	 * Setup plugin links.
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_page_link($links, $file){
		if(false !== strpos($file, 'wp-gianism')){
			array_unshift($links, '<a href="'.admin_url('users.php?page=gianism').'">'.__('Settings').'</a>');
		}
		return $links;
	}
	
	public function plugin_row_meta($links, $file){
		if(false !== strpos($file, 'wp-gianism')){
			
		}
		return $links;
	}
}