<?php
/*
  Plugin Name: plugin load filter
  Description: Dynamically activate the selected plugins for each page. Response will be faster by filtering plugins.
  Version: 2.0.0
  Plugin URI: http://celtislab.net/wp_plugin_load_filter
  Author: enomoto@celtislab
  Author URI: http://celtislab.net/
  License: GPLv2
  Text Domain: plf
  Domain Path: /languages
 */

$Plf_setting = new Plf_setting();

class Plf_setting {
    
    private $plugins_inf = '';  //active plugin/module infomation
    private $editem = false; 
    private $filter = array();  //filter option data
    private $tab_num = 0;
    
    /***************************************************************************
     * Style Sheet
     **************************************************************************/
    function plf_css() { ?>
    <style type="text/css">
    .plugins-list { max-width : 480px; min-height: 80px; max-height: 320px; overflow: auto; border: 1px solid #CEE1EF; padding:0.5em 0.5em;}
    ul.plugins-list ul { margin-left: 20px; }
    ul.plugins-list li { margin: 0; padding: 0; line-height: 22px; word-wrap: break-word;}
    </style>
    <?php }    

    function jquery_tab_css() { ?>
    <style type="text/css">
    .ui-helper-reset { margin: 0; padding: 0; border: 0; outline: 0; line-height: 1.5; text-decoration: none; font-size: 100%; list-style: none; }
    .ui-helper-clearfix:before, .ui-helper-clearfix:after { content: ""; display: table; }
    .ui-helper-clearfix:after { clear: both; }
    .ui-helper-clearfix { zoom: 1; }
    .ui-tabs { position: relative; padding: .2em; zoom: 1; } /* position: relative prevents IE scroll bug (element with position: relative inside container with overflow: auto appear as "fixed") */
    .ui-tabs .ui-tabs-nav { margin: 1px 8px; padding: .2em .2em; }
    .ui-tabs .ui-tabs-nav li { list-style: none; float: left; position: relative; top: 0; margin: 1px .3em 0 0; border-bottom: 0; padding: 0; white-space: nowrap; }
    .ui-tabs .ui-tabs-nav li a { float: left; text-decoration: none; }
    .ui-tabs .ui-tabs-nav li.ui-tabs-active { margin-bottom: -1px; padding-bottom: 1px; }
    .ui-tabs .ui-tabs-panel { display: block; border-width: 0;  background: none; }
    .ui-tabs .ui-tabs-nav a { margin: 8px 10px; }
    .ui-state-default, .ui-widget-content .ui-state-default, .ui-widget-header .ui-state-default { border: 1px solid #dddddd; background-color: #f4f4f4; font-weight: bold; color: #0073ea; }
    .ui-state-default a, .ui-state-default a:link, .ui-state-default a:visited { color: #0073ea; text-decoration: none; }
    .ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover, .ui-state-focus, .ui-widget-content .ui-state-focus,.ui-widget-header .ui-state-focus { border: 1px solid #0073ea; background-color: #0073ea; font-weight: bold; color: #ffffff; }
    .ui-state-active, .ui-widget-content .ui-state-active, .ui-widget-header .ui-state-active { border: 1px solid #dddddd; background-color: #0073ea; font-weight: bold; color: #ffffff; }
    .ui-state-hover a, .ui-state-hover a:hover, .ui-state-hover a:link, .ui-state-hover a:visited { color: #ffffff; text-decoration: none; }
    .ui-state-active a, .ui-state-active a:link, .ui-state-active a:visited { color: #ffffff; text-decoration: none; }
    </style>
    <?php }    
    
    /***************************************************************************
     * Plugin Load Filter Option Setting
     **************************************************************************/

    public function __construct() {
        
        load_plugin_textdomain('plf', false, basename( dirname( __FILE__ ) ).'/languages' );
        
        $this->filter = get_option('plf_option');
        if(is_admin()) {
            add_action( 'plugins_loaded', array(&$this, 'plf_admin_start'), 9999 );
            add_action( 'update_option_plf_option', array(&$this, 'plf_transient_clear') );
            add_action( 'update_option_active_plugins', array(&$this, 'plf_transient_clear') );
            add_action( 'update_option_jetpack_active_modules', array(&$this, 'plf_transient_clear') );
            add_action( 'update_option_celtispack_active_modules', array(&$this, 'plf_transient_clear') );
            add_action( 'add_meta_boxes', array(&$this, 'load_meta_boxes'), 10, 2 );
            //plf-filter must-use plagin activete check
            if(wp_mkdir_p( WPMU_PLUGIN_DIR )){
                if ( !file_exists( WPMU_PLUGIN_DIR . "/plf-filter.php" )) { 
                    @copy(__DIR__ . '/mu-plugins/plf-filter.php', WPMU_PLUGIN_DIR . '/plf-filter.php');
                }
            }
            register_deactivation_hook( __FILE__, 'Plf_setting::deactivation' );
            register_uninstall_hook(__FILE__, 'Plf_setting::uninstall');
        }
        add_action( 'save_post', array(&$this, 'plf_transient_clear') );
        add_action( 'deleted_post', array(&$this, 'plf_transient_clear') );
        add_action( 'wp_ajax_plugin_load_filter', array(&$this, 'plf_ajax_postidfilter'));
    }

    //Plugin Deactivate
    public static function deactivation() {
        if ( file_exists( WPMU_PLUGIN_DIR . "/plf-filter.php" )) { 
            @unlink( WPMU_PLUGIN_DIR . '/plf-filter.php' );
        }
    }
    //Plugin Uninstall
    public static function uninstall() {
        delete_option('plf_queryvars');
        delete_option('plf_option' );
    }
    
    //Disable the transient cache by changing the update time
    public function plf_transient_clear() {
        $this->filter['updated'] = strtotime("now");
        update_option('plf_option', $this->filter );
    }

    //Plugin Load Filter admin setting start 
    public function plf_admin_start() {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        
        $this->plugins_inf = get_plugins();
        $packplug = array();
        foreach ( $this->plugins_inf as $plugin_key => $a_plugin ) {
            if(is_plugin_inactive( $plugin_key )){
                unset($this->plugins_inf[$plugin_key]);
            }
        }
        //jetpack active module 
        if(method_exists('Jetpack', 'get_module')){
            $modules = Jetpack::get_active_modules();
            $modules = array_diff( $modules, array( 'vaultpress' ) );
            foreach ( $modules as $key => $module_name ) {
                if(!empty($module_name)){
                    $module = Jetpack::get_module( $module_name );
                    if(!empty($module))
                        $this->plugins_inf['jetpack_module/' . $module_name] = $module;
                }
            }
        }
        //celtispack active module 
        if(method_exists('Celtispack', 'get_module')){
            $modules = Celtispack::get_active_modules();
            foreach ( $modules as $key => $module_name ) {
                if(!empty($module_name)){
                    $this->plugins_inf['celtispack_module/' . $module_name] = Celtispack::get_module( $module_name );
                }
            }
        }
        if ( empty( $this->plugins_inf ) ) 
            return;

        $this->action_posts();  //POST, GET Request Action
        add_action('admin_menu', array(&$this, 'plf_option_menu')); 
    }
    
    //Plugins sub menu add
    public function plf_option_menu() {
        $page = add_plugins_page( 'Plugin Load Filter', __('Plugin Load Filter', 'plf'), 'manage_options', 'plugin_load_filter_admin_manage_page', array(&$this,'plf_option_page'));
        add_action('admin_print_scripts-'.$page,  array(&$this, 'plf_scripts'));    
    }

    //Plugin Load Filter setting page script 
    function plf_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-widget' );
        wp_enqueue_script( 'jquery-ui-tabs' );
        add_action( 'admin_head', array(&$this, 'plf_css' ));
        add_action( 'admin_head', array(&$this, 'jquery_tab_css' ));
        add_action( 'admin_footer', array(&$this, 'activetab_script' ));
    }
    
    //plugin filter option action request (add, update, delete)
    function action_posts() {
        if (current_user_can( 'edit_plugins' )) {
            if( isset($_POST['edit_regist_filter']) ) {
                if(isset($_POST['plf_option']['_tag'])){
                    check_admin_referer('plugin_load_filter');
                    $option["plugins"] = '';
                    if(isset($_POST['plf_option']['plugins'])){
                        $plugins = array();
                        foreach($_POST['plf_option']['plugins'] as $plugin => $val) { 
                            $plugins[] = $plugin;
                        }
                        $option["plugins"] = implode(",", $plugins);
                    }
                    $this->filter[$_POST['plf_option']['_tag']] = $option;
                    $this->filter['updated'] = strtotime("now");
                    update_option('plf_option', $this->filter );
                }
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page'));
                exit;
            }
            elseif( isset($_POST['clear_regist_filter']) ) {
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page'));
                exit;
            }
            else if( isset($_POST['new_page_filter']) || isset($_POST['edit_page_filter']) ) {
                if(isset($_POST['plf_option']['tag'])){
                    check_admin_referer('plugin_load_filter');
                    $option["plugins"] = '';
                    if(isset($_POST['plf_option']['plugins'])){
                        $plugins = array();
                        foreach($_POST['plf_option']['plugins'] as $plugin => $val) { 
                            $plugins[] = $plugin;
                        }
                        $option["plugins"] = implode(",", $plugins);
                    }
                    $this->filter['group'][$_POST['plf_option']['tag']] = $option;
                    $this->filter['updated'] = strtotime("now");
                    update_option('plf_option', $this->filter );
                }
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page&action=tab_1'));
                exit;
            } 
            elseif( isset($_POST['clear_page_filter']) ) {
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page&action=tab_1'));
                exit;
            }
            else if (!empty($_GET['action'])) {
                if( $_GET['action']=='edit_filter_table') {
                    $this->editem =  $_GET['item'];
                    $this->tab_num = (0 === strpos($_GET['item'], '_'))? 0 : 1;
                }
                elseif( $_GET['action']=='del_filter_table') {
                    if( !empty( $_GET['item'])){
                        check_admin_referer( 'plugin_load_filter' );
                        $tab_action = '';
                        if(0 === strpos($_GET['item'], '_')){
                            unset($this->filter[$_GET['item']]);
                        }
                        else {
                            unset($this->filter['group'][$_GET['item']]);
                            $tab_action = '&action=tab_1';
                        }
                        $this->filter['updated'] = strtotime("now");
                        update_option('plf_option', $this->filter );
                    }
                    header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page'.$tab_action));
                    exit;
                } 
                elseif( $_GET['action']=='tab_1') {
                    $this->tab_num = 1;
                }
            }
        }
    }

    public function filter_stat( $sw, $stat) {
        if( $sw === 'exclude') {
            if(empty($stat))
                $str = '<span style="color: #339966;">"Page filter plugins Activate"</span>';
            else
                $str = '<span style="color: #ff0000;">' . $stat. '</span>';
        }
        elseif( $sw === 'include') {
            if(empty($stat))
                $str = '<span style="color: #ff0000;">"Page filter plugins Deactivate"</span>';
            else
                $str = '<span style="color: #339966;">' . $stat. '</span>';
        }
        else
            $str = $stat;
        return $str;
    }   

    //Plugin or Module key to name
    // $type : smart/csv/list/tree
    public function pluginkey_to_name( $infkey, $type='smart') {

        $name = '';
        if(strpos($infkey, 'jetpack_module/') !== false){
            if(!empty($this->plugins_inf[$infkey]['name'])){
                $m_mark = ($type !== 'list')? '-' : 'Jetpack-';
                if($type === 'smart') {
                    if(empty($this->filter['_pagefilter']['plugins']) || strpos($this->filter['_pagefilter']['plugins'], 'jetpack/') === false)
                        $name = $m_mark . $this->plugins_inf[$infkey]['name'];
                }
                else
                    $name = $m_mark . $this->plugins_inf[$infkey]['name'];
            }
        }
        elseif(strpos($infkey, 'celtispack_module/') !== false){
            if(!empty($this->plugins_inf[$infkey]['Name'])){
                $m_mark = ($type !== 'list')? '-' : 'Celtispack-';
                if($type === 'smart') {
                    if(empty($this->filter['_pagefilter']['plugins']) || strpos($this->filter['_pagefilter']['plugins'], 'celtispack/') === false)
                        $name = $m_mark . $this->plugins_inf[$infkey]['Name'];
                }
                else
                    $name = $m_mark . $this->plugins_inf[$infkey]['Name'];
            }
        }
        else {
            if(!empty($this->plugins_inf[$infkey]['Name']))
                $name = $this->plugins_inf[$infkey]['Name'];
        }
        return($name);
    } 

    //Checkbox
	static function checkbox($name, $value, $label = '') {
        return "<label><input type='checkbox' name='$name' value='1' " . checked( $value, 1, false ).  "/> $label</label>";
	}
    //DropdownList
	static function dropdown($name, $items, $selected, $args=null) {
		$defaults = array( 'id' => $name, 'class' => "", 'multiple' => false );
		foreach($items as $key => &$value) {
			if (is_array($value))
				$value = array_shift($value);
		}
		$opt = wp_parse_args($args, $defaults);
		$name = ($name) ? "name='$name'" : "";
        $id   = ($opt['id']) ? "id='${opt['id']}'" : "";
        $class = ($opt['class']) ? "class='${opt['class']}'" : "";
		$multiple = ($opt['multiple']) ? "multiple='multiple'" : "";

		$html = "<select $name $id $class $multiple >";
		foreach ((array)$items as $key => $label) {
			$key = esc_attr($key);
			$label = esc_attr($label);
			$html .= "<option value='$key' " . selected($selected, $key, false) . ">$label</option>";
		}
		$html .= "</select>";
		return $html;
	}
    
    //Plugin Select Checkbox (plugin-modules tree)
    // $plugins : array : all active plugins 
    // $checked_cvplugins : csv string : checked plugins
    function plugins_checklist( $plugins, $checked_cvplugins ) {
        
        $nplugins = $jmodules = $cmodules =  array();
        $type = 'tree';
        foreach ( $plugins as $p_key => $p_data ) {
            $p_name = $this->pluginkey_to_name($p_key, $type);
            if(empty($p_name))
                continue;
            if(strpos($p_key, 'jetpack_module/') !== false)
                $jmodules[] = $p_key;
            else if(strpos($p_key, 'celtispack_module/') !== false)
                $cmodules[] = $p_key;
            else 
                $nplugins[] = $p_key; 
        }
        $chkplugins = array_map("trim", explode(',', $checked_cvplugins));
        $html =  '<ul class="plugins-list">';
        foreach ( $nplugins as $p_key ) {
            $p_name = $this->pluginkey_to_name($p_key, $type);                
            $checked = in_array( $p_key, $chkplugins ) ? true : false;
            $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';

            $modules = array();
            if(strpos($p_key, 'jetpack/') !== false)
                $modules = $jmodules;
            else if(strpos($p_key, 'celtispack/') !== false)
                $modules = $cmodules;
            if(!empty($modules)){
                $html .= '<ul class="modules-list">';
                foreach ( $modules as $m_key ) {
                    $m_name = $this->pluginkey_to_name($m_key, $type);
                    $mchecked = ($checked || in_array( $m_key, $chkplugins )) ? true : false;
                    $html .= '<li>' . self::checkbox("plf_option[plugins][$m_key]", $mchecked, esc_attr($m_name)) . '</li>';
                }
                $html .= '</ul>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    //Plugin pagefilter Selected Checkbox (plugin and modules list)
    // $plugins : array : all active plugins 
    // $select_cvplugins  : csv string : pagefilter selected plugins 
    // $checked_cvplugins : csv string : checked plugins
    function pagefilter_plugins_checklist( $plugins, $select_cvplugins, $checked_cvplugins ) {
        
        if(empty($select_cvplugins))
            return __('Page Filter is not registered', 'plf');
        
        $nplugins = $jmodules = $cmodules = $allmodule = array();
        $type = 'list';
        foreach ( $plugins as $p_key => $p_data ) {
            $p_name = $this->pluginkey_to_name($p_key, $type);
            if(empty($p_name))
                continue;
            if(strpos($p_key, 'jetpack_module/') !== false)
                $jmodules[] = $p_key;
            else if(strpos($p_key, 'celtispack_module/') !== false)
                $cmodules[] = $p_key;
            else {
                if(strpos($p_key, 'jetpack/') !== false && strpos($select_cvplugins, $p_key) !== false)
                    $allmodule['jetpack'] = $p_key;
                else if(strpos($p_key, 'celtispack/') !== false && strpos($select_cvplugins, $p_key) !== false)
                    $allmodule['celtispack'] = $p_key;
                else
                    $nplugins[] = $p_key; 
            }
        }
        $selplugins = array_map("trim", explode(',', $select_cvplugins));
        $chkplugins = array_map("trim", explode(',', $checked_cvplugins));
        $html =  '<ul class="plugins-list">';
        foreach ( $nplugins as $p_key ) {
            if(in_array( $p_key, $selplugins )) {
                $p_name = $this->pluginkey_to_name($p_key, $type);                
                $checked = in_array( $p_key, $chkplugins ) ? true : false;
                $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
            }
        }
        foreach ( $jmodules as $p_key ) {
            if(!empty($allmodule['jetpack']) || in_array( $p_key, $selplugins )){
                $p_name = $this->pluginkey_to_name($p_key, $type);                
                $checked = in_array( $p_key, $chkplugins ) ? true : false;
                $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
            }
        }
        foreach ( $cmodules as $p_key ) {
            if(!empty($allmodule['celtispack']) || in_array( $p_key, $selplugins )){
                $p_name = $this->pluginkey_to_name($p_key, $type);                
                $checked = in_array( $p_key, $chkplugins ) ? true : false;
                $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }
    
    //Plugin Filter Table 
    public function plf_table($tags, $default) {
    ?>
    <table class="widefat">
        <thead>
           <tr><th width="12%"><?php _e('Type'); ?></th><th width="76%"><?php _e('Plugins'); ?></th><th width="12%" colspan="2">&nbsp;</th></tr>
        </thead>
        <tbody>
        <?php
        if(!empty($tags)){
            foreach( $tags as $ptag => $val ) {
                $opt = wp_parse_args( $val,  $default);
                $plugins = array_map("trim", explode(',', $opt['plugins']));
                $plist = '';
                $type = ($ptag == '_pagefilter')? 'smart' : 'csv';
                foreach ( $plugins as $p_key ) {
                    $p_name = $this->pluginkey_to_name($p_key, $type);
                    if(!empty($p_name)){
                        $sep = (empty($plist))? '' : ', ';
                        $plist .= $sep . esc_attr($p_name);
                    }
                }
                if(!empty($ptag)){
                    echo '<tr id="plf_filter_' .$ptag. '">';
                    echo '<td>'.$ptag.'</td>';
                    echo '<td>'. $this->filter_stat($opt['filter'], $plist).'</td>';
                    echo '<td><a class="view" href="'. wp_nonce_url("plugins.php?page=plugin_load_filter_admin_manage_page&amp;action=edit_filter_table&amp;item=$ptag", "plugin_load_filter") . '">' . __( 'Edit' ) . '</a></td>';
                    echo '<td><a class="delete" href="'. wp_nonce_url("plugins.php?page=plugin_load_filter_admin_manage_page&amp;action=del_filter_table&amp;item=$ptag", "plugin_load_filter") . '">' . __( 'Delete' ) . '</a></td>';
                    echo '</tr>';
                }
            }
        }
        ?>
        </tbody>
    </table>
    <?php
    }
    
    //Option Setting Form Display
    public function plf_option_page() {
    ?>
    <h2><?php _e('Plugin Load Filter Settings', 'plf'); ?></h2>
    <p></p>
    <div id="plf-setting-tabs">
        <ul>
            <li><a href="#plf-registration-tab" ><?php _e('Filter Registration', 'plf'); ?></a></li>
            <li><a href="#plf-activation-tab" ><?php _e('Page Filter Activation', 'plf'); ?></a></li>
        </ul>
        <form method="post" >
		<?php wp_nonce_field( 'plugin_load_filter'); ?>
        <div id="plf-registration-tab" >
            <?php
            $default = array( 'filter' => 'exclude', 'plugins' => '');
            $base = array();
            foreach($this->filter as $item => $val){
                if(0 === strpos($item, '_'))
                    $base[$item] = $this->filter[$item];
            }
            $this->plf_table($base, $default);

            $itemtag = '';
            $option = $default;
            if( !empty($this->editem) ) {
                $itemtag = $this->editem;
                $option = $this->filter[$itemtag];
            }
            $option = wp_parse_args( $option, $default);
            ?>
            <br />
            <p><strong>[ Admin Filter ]</strong><br />
              <?php _e('Register the plugins to be used only in admin mode.', 'plf'); ?><br />
            </p>
            <p><strong>[ Desktop/Mobile Filter ]</strong><br />
              <?php _e('Register the plugins to be used only in desktop/moble device. (wp_is_mobile function use)', 'plf'); ?><br />
            </p>
            <p><strong>[ Page Filter ]</strong><br />
              <?php _e('Register the plugins for selecting whether to activate each Page type or Post.', 'plf'); ?><br />
              <?php _e('Page Filter registration plugins are once blocked, but is activated by "Page filter Activation" setting.', 'plf'); ?><br />
            </p>
            <p><?php _e('<strong>[Note]</strong> Running condition is unknown plugin, please do not register. Unregistered plugins are usually loaded.', 'plf') ?></p>
            <table width="100%" cellspacing="2" cellpadding="5" class="editform form-table">
                <tbody>
                <tr>
                    <th valign="top" scope="row"><label for="plugin"><?php _e('Filter Type', 'plf'); ?>:</label></th>
                    <td>
                        <label><input type="radio" name="plf_option[_tag]" value="_admin" <?php checked('_admin', $itemtag); ?>/><?php _e('Admin', 'plf'); ?></label>
                        <label><input type="radio" name="plf_option[_tag]" value="_desktop" <?php checked('_desktop', $itemtag); ?>/><?php _e('Desktop', 'plf'); ?></label>
                        <label><input type="radio" name="plf_option[_tag]" value="_mobile" <?php checked('_mobile', $itemtag); ?>/><?php _e('Mobile', 'plf'); ?></label>
                        <label><input type="radio" name="plf_option[_tag]" value="_pagefilter" <?php checked('_pagefilter',  $itemtag); ?>/><?php _e('Page Filter', 'plf'); ?></label>
                        <br />
                        <?php echo $this->plugins_checklist( $this->plugins_inf, $option['plugins'] ); ?>
                    </td>
                </tr>
                <tr>
                </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" name="clear_regist_filter" value="<?php _e('Clear', 'plf'); ?>" />&nbsp;&nbsp;&nbsp;
                <input type="submit" class="button-primary" name="edit_regist_filter" value="<?php _e('Entry &raquo;', 'plf'); ?>" />
            </p>
        </div>
        <div id="plf-activation-tab" >
            <?php
            $default = array( 'filter' => 'include', 'plugins' => '');
            $cgroup = (!empty($this->filter['group']))? $this->filter['group'] : array();
            $pgfilter = (!empty($this->filter['_pagefilter']['plugins']))? $this->filter['_pagefilter']['plugins'] : array();
            $this->plf_table( $cgroup, $default);
            $itemtag = '';
            $option = $default;
            $action = 'new_page_filter';
            if( !empty($this->editem) ) {
                $itemtag = $this->editem;
                $option = $cgroup[$itemtag];
                $action = 'edit_page_filter';
            }
            $option = wp_parse_args( $option, $default);
            ?>
            <br />
            <p><?php _e('Select the plugin from "Page Filter" registration to activate in each Page type.', 'plf') ?><br />
               <?php _e('Can be selected plugins to activate from Post content editing screen.', 'plf') ?>
            </p>
            <table width="100%" cellspacing="2" cellpadding="5" class="editform form-table">
                <tbody>
                <tr>
                    <th valign="top" scope="row"><label for="plugintag"><?php _e( 'Page type', 'plf'); ?></label></th>
                    <td><?php
                        $slist = array('home'       => __('Home/Front-page', 'plf'),
                                      'archive'     => __('Archive page', 'plf'),
                                      'search'      => __('Search page', 'plf'),
                                      'attachment'  => __('Attachment page', 'plf'),
                                      'page'        => __('Page', 'plf'),
                                      'post'        => __('Post : Standard', 'plf'),
                                      'post-aside'  => __('Post : Aside', 'plf'),
                                      'post-image'  => __('Post : Image', 'plf'),
                                      'post-video'  => __('Post : Video', 'plf'),
                                      'post-quote'  => __('Post : Quote', 'plf'),
                                      'post-link'   => __('Post : Link', 'plf'),
                                      'post-gallery'=> __('Post : Gallery', 'plf'),
                                      'post-status' => __('Post : Status', 'plf'),
                                      'post-audio'  => __('Post : Audio', 'plf'),
                                      'post-chat'   => __('Post : Chat', 'plf'));
                        $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
                        foreach ( $post_types as $post_type ) {
                            if(!empty($post_type))
                                $slist[$post_type] = __('Custom Post : ', 'plf') . $post_type;
                        }
                        echo self::dropdown('plf_option[tag]', $slist, $itemtag );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th valign="top" scope="row"><label for="plugin"><?php _e('Activate Plugins', 'plf'); ?>:</label></th>
                    <td>
                        <?php echo $this->pagefilter_plugins_checklist( $this->plugins_inf, $pgfilter, $option['plugins'] ); ?>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" name="clear_page_filter" value="<?php _e('Clear', 'plf'); ?>" />&nbsp;&nbsp;&nbsp;
                <input type="submit" class="button-primary" name="<?php echo $action ?>" value="<?php _e('Entry &raquo;', 'plf'); ?>" />
            </p>
        </div>
        </form>
    </div>
    <?php
    }

    /***************************************************************************
     * Meta box
     * Individual of the plug-in filter meta box for Post/Page/CustomPost
     **************************************************************************/
    function load_meta_boxes( $post_type, $post ) {
        if ( current_user_can('edit_plugins', $post->ID) ) { 
          	add_meta_box( 'pluginfilterdiv', __( 'Page Filter Plugin', 'plf' ), array(&$this, 'plf_meta_box'), null, 'side'  );
            add_action( 'admin_head', array(&$this, 'plf_css' ));
            add_action( 'admin_footer', array(&$this, 'plf_meta_script' ));
        }
    }

    function plf_meta_box( $post, $box ) {     
        if(is_object($post)){
            $default = array( 'filter' => 'default', 'plugins' => '');
            $myfilter = get_post_meta( $post->ID, '_plugin_load_filter', true );
            $option = (!empty($myfilter))? $myfilter : $default;
            $option = wp_parse_args( $option, $default);
			$ajax_nonce = wp_create_nonce( 'plugin_load_filter-' . $post->ID );
            $pgfilter = (!empty($this->filter['_pagefilter']['plugins']))? $this->filter['_pagefilter']['plugins'] : array();
            ?>
        <div id="plugin-filter-select">
            <label><input type="radio" name="pagefilter" value="default" <?php checked('default', $option['filter']); ?>/><?php _e('Not Use', 'plf' ); ?></label>
            <label><input type="radio" name="pagefilter" value="include" <?php checked('include', $option['filter']); ?>/><?php _e('Activate Plugins', 'plf'); ?></label>
            <br />
            <div id="page-filter-stat">
            <?php echo $this->pagefilter_plugins_checklist( $this->plugins_inf, $pgfilter, $option['plugins'] ); ?>
            </div>
            <?php echo '<p class="hide-if-no-js"><a id="plugin-filter-submit" class="button" href="#pluginfilterdiv" onclick="WPAddPagePluginLoadFilter(\'' . $ajax_nonce . '\');return false;" >'. __('Save') .'</a></p>'; ?>
        </div>
        <?php
        }
    }    

    //wp_ajax_plugin_load_filter called function
    function plf_ajax_postidfilter() {
        if ( isset($_POST['post_id']) ) {
            $pid = (int) $_POST['post_id'];
            if ( !current_user_can( 'edit_plugins', $pid ) )
                wp_die( -1 );            
            check_ajax_referer( "plugin_load_filter-$pid" );
            
            $option["filter"] = (empty($_POST['filter']))? 'default' : $_POST['filter'];
            $option["plugins"] = '';
            if('default' == $option["filter"])
                delete_post_meta( $pid, '_plugin_load_filter');
            else {
                $plugins = array();
                if( preg_match_all('/plf_option\[plugins\]\[(.+?)\]/u', $_POST['plugins'], $matches)){
                    if(!empty($matches[1])){ 
                        foreach ($matches[1] as $plugin){
                            $plugins[] = $plugin;
                        }
                        $option["plugins"] = implode(",", $plugins);
                    }
                }
                update_post_meta( $pid, '_plugin_load_filter', $option );
            }
            //transient cache clear
            $this->plf_transient_clear();
            
            $pgfilter = (!empty($this->filter['_pagefilter']['plugins']))? $this->filter['_pagefilter']['plugins'] : array();
            $html = $this->pagefilter_plugins_checklist( $this->plugins_inf, $pgfilter, $option['plugins'] );
            wp_send_json_success($html);
        }
        wp_die( 0 );
    }

    /***************************************************************************
     * Javascript 
     **************************************************************************/
    function activetab_script() { ?>
    <script type='text/javascript' >
    /* <![CDATA[ */
    var plf_activetab = <?php echo $this->tab_num; ?>
    /* ]]> */
    jQuery(document).ready(function ($) { plf_setting_tabs(); function plf_setting_tabs(){ $('#plf-setting-tabs').tabs({ active:plf_activetab, }); }});    
    </script>  
    <?php }
    
    function plf_meta_script() { ?>
    <script type='text/javascript' >
    WPAddPagePluginLoadFilter = function(nonce){
        jQuery.ajax({ type: 'POST', url: ajaxurl, 
            data: {action: "plugin_load_filter", post_id : jQuery( '#post_ID' ).val(), _ajax_nonce: nonce, filter: jQuery("input[name='pagefilter']:checked").val(), plugins: jQuery('.plugins-list li input:checked').map(function(){ return jQuery(this).attr("name"); }).get().join(','), }, dataType: 'json', 
            success: function(response, dataType) { jQuery('#page-filter-stat').html(response.data); }
        });
        return false; };
    </script>  
    <?php }
}