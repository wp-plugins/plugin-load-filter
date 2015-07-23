<?php
/*
  Plugin Name: plugin load filter
  Description: Dynamically activate the selected plugins for each page. Response will be faster by filtering plugins.
  Version: 2.2.0
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

    #registration-table input[type=radio], #activation-table input[type=checkbox] {  height: 25px; width: 25px; opacity: 0;}    
    .dashicons:before { font-size: 24px; }    
    .radio-green label, .radio-red label, .altcheckbox label { color: #ddd; margin-left: -28px; }
    .radio-green input[type="radio"]:checked + label { color: #339966; }   
    .radio-red input[type="radio"]:checked + label { color: #ff0000; }   
    .altcheckbox input[type="checkbox"]:checked + label { color: #339966; }   
    </style>
    <?php }    
    
    /***************************************************************************
     * Plugin Load Filter Option Setting
     **************************************************************************/

    public function __construct() {
        
        load_plugin_textdomain('plf', false, basename( dirname( __FILE__ ) ).'/languages' );
        
        $this->filter = get_option('plf_option');
        if(!empty($this->filter['updated'])){
            $this->filter['updated'] = null; //v2.2.0 not used option clear
        }
        
        if(is_admin()) {
            add_action( 'plugins_loaded', array(&$this, 'plf_admin_start'), 9999 );
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
                if(isset($_POST['plfregist'])){
                    check_admin_referer('plugin_load_filter');
                    foreach( array('_admin', '_desktop', '_mobile', '_pagefilter') as $item){
                        $plugins = array();
                        foreach ( $_POST['plfregist'] as $p_key => $val ) {
                            if($val == $item)
                                $plugins[$p_key] = $val;
                        }
                        if($item == '_pagefilter'){
                            //If all modules is specified filter, in some cases you want to deactivate plugin itself.
                            $jbase = $cbase = '';
                            $jall = $call = true;
                            foreach ( $_POST['plfregist'] as $p_key => $val ) {
                                if(strpos($p_key, 'jetpack/') !== false)
                                    $jbase = $p_key;
                                else if(strpos($p_key, 'celtispack/') !== false)
                                    $cbase = $p_key;
                                else if(strpos($p_key, 'jetpack_module/') !== false){
                                    if($val != '_pagefilter' && $val != '_admin'){
                                        $jall = false;
                                    }
                                }
                                else if(strpos($p_key, 'celtispack_module/') !== false){
                                    if($val != '_pagefilter' && $val != '_admin')
                                        $call = false;
                                }
                            }
                            if(!empty($jbase) && $jall === false)
                                unset($plugins[$jbase]);
                            if(!empty($cbase) && $call === false)
                                unset($plugins[$cbase]);
                        }
                        $option["plugins"] = implode(",", array_keys($plugins));
                        $this->filter[$item] = $option;
                    }
                    update_option('plf_option', $this->filter );
                }
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page'));
                exit;
            }
            elseif( isset($_POST['clear_regist_filter']) ) {
                check_admin_referer('plugin_load_filter');
                foreach( array('_admin', '_desktop', '_mobile', '_pagefilter') as $item){
                    $this->filter[$item] = '';
                }
                update_option('plf_option', $this->filter );
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page'));
                exit;
            }
            else if(isset($_POST['edit_activate_page_filter']) ) {
                if(isset($_POST['plfactive'])){
                    check_admin_referer('plugin_load_filter');
                    $group = array_keys($_POST['plfactive']);
                    foreach( $group as $item){
                        $plugins = array();
                        foreach ( $_POST['plfactive'][$item] as $p_key => $val ) {
                            if($val == '1')
                                $plugins[] = $p_key;
                        }
                        $option["plugins"] = implode(",", $plugins);
                        $this->filter['group'][$item] = $option;
                    }
                    update_option('plf_option', $this->filter );
                }
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page&action=tab_1'));
                exit;
            } 
            elseif( isset($_POST['clear_activate_page_filter']) ) {
                check_admin_referer('plugin_load_filter');
                $group = array_keys($_POST['plfactive']);
                foreach( $group as $item){
                    $this->filter['group'][$item] = '';
                }
                update_option('plf_option', $this->filter );
                header('Location: ' . admin_url('plugins.php?page=plugin_load_filter_admin_manage_page&action=tab_1'));
                exit;
            }
            if(!empty($_GET['action']) && $_GET['action']=='tab_1') {
                $this->tab_num = 1;
            }
        }
    }

    //Plugin or Module key to name
    // $type : list/smart/tree
    public function pluginkey_to_name( $infkey, $type='list') {

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
	static function altcheckbox($name, $value, $label = '') {
        return "<input type='hidden' name='$name' value='0'><input type='checkbox' name='$name' value='1' " . checked( $value, 1, false ).  "/><label> $label</label>";
	}
    
    //Plugin pagefilter Selected Checkbox (plugin and modules list)
    // $plugins : array : all active plugins 
    // $select_cvplugins  : csv string : pagefilter selected plugins 
    // $checked_cvplugins : csv string : checked plugins
    function pagefilter_plugins_checklist( $plugins, $select_cvplugins, $checked_cvplugins ) {
        
        if(empty($select_cvplugins))
            return __('Page Filter is not registered', 'plf');
        
        $nplugins = $jmodules = $cmodules = array();
        foreach ( $plugins as $p_key => $p_data ) {
            $p_name = $this->pluginkey_to_name($p_key);
            if(empty($p_name))
                continue;
            if(strpos($p_key, 'jetpack_module/') !== false)
                $jmodules[] = $p_key;
            else if(strpos($p_key, 'celtispack_module/') !== false)
                $cmodules[] = $p_key;
            else 
                $nplugins[] = $p_key; 
        }
        $selplugins = array_map("trim", explode(',', $select_cvplugins));
        $chkplugins = array_map("trim", explode(',', $checked_cvplugins));
        $html =  '<ul class="plugins-list">';
        foreach ( $nplugins as $p_key ) {
            if(strpos($p_key, 'jetpack/') !== false){
                foreach ( $jmodules as $p_key ) {
                    if(in_array( $p_key, $selplugins )){
                        $p_name = $this->pluginkey_to_name($p_key);                
                        $checked = in_array( $p_key, $chkplugins ) ? true : false;
                        $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
                    }
                }
            }
            else if(strpos($p_key, 'celtispack/') !== false){
                foreach ( $cmodules as $p_key ) {
                    if(in_array( $p_key, $selplugins )){
                        $p_name = $this->pluginkey_to_name($p_key);                
                        $checked = in_array( $p_key, $chkplugins ) ? true : false;
                        $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
                    }
                }
            }
            else if(in_array( $p_key, $selplugins )) {
                $p_name = $this->pluginkey_to_name($p_key);                
                $checked = in_array( $p_key, $chkplugins ) ? true : false;
                $html .= '<li>' . self::checkbox("plf_option[plugins][$p_key]", $checked, esc_attr($p_name)) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    public function plfregist_item($key, $val) {
        $p_name = $this->pluginkey_to_name($key);
        $opt_name = "plfregist[$key]";
        ?>
        <tr id="plfregist_<?php echo $key; ?>">
          <td><?php echo $p_name; ?></td>
          <td class="radio-green"><input type="radio" name="<?php echo $opt_name; ?>" value='' <?php checked('', $val); ?>/><label><span class="dashicons dashicons-admin-plugins"></span></label></td>
          <td class="radio-red"><input type="radio" name="<?php echo $opt_name; ?>" value="_admin" <?php checked('_admin', $val); ?>/><label><span class="dashicons dashicons-admin-plugins"></span></label></td>
          <td class="radio-red"><input type="radio" name="<?php echo $opt_name; ?>" value="_desktop" <?php checked('_desktop', $val); ?>/><label><span class="dashicons dashicons-admin-plugins"></span></label></td>
          <td class="radio-red"><input type="radio" name="<?php echo $opt_name; ?>" value="_mobile" <?php checked('_mobile', $val); ?>/><label><span class="dashicons dashicons-admin-plugins"></span></label></td>
          <td class="radio-red"><input type="radio" name="<?php echo $opt_name; ?>" value="_pagefilter" <?php checked('_pagefilter', $val); ?>/><label><span class="dashicons dashicons-admin-plugins"></span></label></td>
        </tr>
        <?php
    }
    
    //Filterring plugins select   
    public function plfregist_table($plugins, $filter) {
    ?>
    <table id="registration-table" class="widefat">
        <thead>
           <tr><th ><?php _e('Plugins'); ?></th>
               <th ><?php _e('Normal Load', 'plf'); ?></th>
               <th ><?php _e('Admin', 'plf'); ?></th>
               <th ><?php _e('Desktop', 'plf'); ?></th>
               <th ><?php _e('Mobile', 'plf'); ?></th>
               <th ><?php _e('Page Filter', 'plf'); ?></th>
           </tr>
        </thead>
        <tbody>
        <?php
        //plugins filter registoration table
        $plist = array();
        foreach ( $plugins as $p_key => $val ) {
            $name = $this->pluginkey_to_name($p_key);
            if(!empty($name)) 
                $plist[$p_key] = '';
        }
        foreach( array('_admin', '_desktop', '_mobile', '_pagefilter') as $item){
            $parr = (!empty($filter[$item]['plugins'])) ? array_map("trim", explode(',', $filter[$item]['plugins'])) : array();
            foreach ( $plist as $p_key => $val ) {
                if(empty($val) && in_array($p_key, $parr))
                    $plist[$p_key] = $item;
            }
        }
        $jlist = $clist = array();
        foreach ( $plist as $p_key => $val ) {
            if(strpos($p_key, 'jetpack_module/') !== false){
                $jlist[$p_key] = $plist[$p_key];
                unset($plist[$p_key]);
            }
            else if(strpos($p_key, 'celtispack_module/') !== false){
                $clist[$p_key] = $plist[$p_key];
                unset($plist[$p_key]);
            }
        }
        foreach ( $plist as $p_key => $val ) {
            $modules = array();
            if(strpos($p_key, 'jetpack/') !== false)
                $modules = $jlist;
            else if(strpos($p_key, 'celtispack/') !== false)
                $modules = $clist;
            else
                $this->plfregist_item($p_key, $val);
            if(!empty($modules)){
                echo "<input type='hidden' name='plfregist[$p_key]' value='_pagefilter'>";
                foreach ( $modules as $m_key => $val) {
                    $this->plfregist_item($m_key, $val);
                }
            }
        }
        ?>
        </tbody>
    </table>
    <?php
    }

    //Activate plugins select from Page Filter  
    public function plfactive_table($plugins, $select_cvplugins, $filter) {
        if(empty($select_cvplugins))
            return;
        
    ?>
    <table id="activation-table" class="widefat">
        <thead>
           <tr><th ><?php _e('Plugins'); ?></th>
               <th ><span title="<?php _e('Home/Front-page', 'plf'); ?>" class="dashicons dashicons-admin-home"></span><br /><span style="font-size:xx-small">Home</span></th>
               <th ><span title="<?php _e('Archive page', 'plf'); ?>" class="dashicons dashicons-list-view"></span><br /><span style="font-size:xx-small">Archive</span></th>
               <th ><span title="<?php _e('Search page', 'plf'); ?>" class="dashicons dashicons-search"></span><br /><span style="font-size:xx-small">Search</span></th>
               <th ><span title="<?php _e('Attachment page', 'plf'); ?>" class="dashicons dashicons-media-default"></span><br /><span style="font-size:xx-small">Attach</span></th>
               <th ><span title="<?php _e('Page', 'plf'); ?>" class="dashicons dashicons-admin-page"></span><br /><span style="font-size:xx-small">Page</span></th>
               <th ><span title="<?php _e('Post : Standard', 'plf'); ?>" class="dashicons dashicons-admin-post"></span><br /><span style="font-size:xx-small">Post</span></th>
               <th ><span title="<?php _e('Post : Image', 'plf'); ?>" class="dashicons dashicons-format-image"></span><br /><span style="font-size:xx-small">Image</span></th>
               <th ><span title="<?php _e('Post : Gallery', 'plf'); ?>" class="dashicons dashicons-format-gallery"></span><br /><span style="font-size:xx-small">Gallery</span></th>
               <th ><span title="<?php _e('Post : Video', 'plf'); ?>" class="dashicons dashicons-format-video"></span><br /><span style="font-size:xx-small">Video</span></th>
               <th ><span title="<?php _e('Post : Audio', 'plf'); ?>" class="dashicons dashicons-format-audio"></span><br /><span style="font-size:xx-small">Audio</span></th>
               <th ><span title="<?php _e('Post : Aside', 'plf'); ?>" class="dashicons dashicons-format-aside"></span><br /><span style="font-size:xx-small">Aside</span></th>
               <th ><span title="<?php _e('Post : Quote', 'plf'); ?>" class="dashicons dashicons-format-quote"></span><br /><span style="font-size:xx-small">Quote</span></th>
               <th ><span title="<?php _e('Post : Link', 'plf'); ?>" class="dashicons dashicons-admin-links"></span><br /><span style="font-size:xx-small">Link</span></th>
               <th ><span title="<?php _e('Post : Status', 'plf'); ?>" class="dashicons dashicons-format-status"></span><br /><span style="font-size:xx-small">Status</span></th>
               <th ><span title="<?php _e('Post : Chat', 'plf'); ?>" class="dashicons dashicons-format-chat"></span><br /><span style="font-size:xx-small">Chat</span></th>
               <?php
                $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
                foreach ( $post_types as $post_type ) {
                    if(!empty($post_type)){
                       $title = __('Custom Post : ', 'plf') . $post_type;
                       echo "<th ><span title='$title' style='font-size:xx-small'>$post_type</span></th>";
                    }
                }
               ?>
           </tr>
        </thead>
        <tbody>
        <?php
        $nplugins = $jmodules = $cmodules = $allmodule = array();
        foreach ( $plugins as $p_key => $p_data ) {
            $p_name = $this->pluginkey_to_name($p_key);
            if(empty($p_name))
                continue;
            if(strpos($p_key, 'jetpack_module/') !== false)
                $jmodules[] = $p_key;
            else if(strpos($p_key, 'celtispack_module/') !== false)
                $cmodules[] = $p_key;
            else 
                $nplugins[] = $p_key; 
        }
        $selplugins = array_map("trim", explode(',', $select_cvplugins));
        $chklist = array('home', 'archive', 'search', 'attachment', 'page', 'post', 
                         'post-image', 'post-gallery', 'post-video', 'post-audio', 'post-aside', 'post-quote', 'post-link', 'post-status', 'post-chat');   
        $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
        foreach ( $post_types as $post_type ) {
            $chklist[] = $post_type;
        }
        foreach ( $nplugins as $p_key ) {
            if(strpos($p_key, 'jetpack/') !== false){
                foreach ( $jmodules as $p_key ) {
                    if(in_array( $p_key, $selplugins )){
                        $p_name = $this->pluginkey_to_name($p_key);                
                        echo "<tr><td>$p_name</td>";
                        foreach($chklist as $pgtype){
                            $checked = (empty($filter['group'][$pgtype]['plugins']) || false === strpos($filter['group'][$pgtype]['plugins'], $p_key))? false : true;
                            echo '<td class="altcheckbox">' . self::altcheckbox("plfactive[$pgtype][$p_key]", $checked, '<span class="dashicons dashicons-admin-plugins"></span>') . '</td>';
                        }
                        echo "</tr>";
                    }
                }
            }
            else if(strpos($p_key, 'celtispack/') !== false){                
                foreach ( $cmodules as $p_key ) {
                    if(in_array( $p_key, $selplugins )){
                        $p_name = $this->pluginkey_to_name($p_key);                
                        echo "<tr><td>$p_name</td>";
                        foreach($chklist as $pgtype){
                            $checked = (empty($filter['group'][$pgtype]['plugins']) || false === strpos($filter['group'][$pgtype]['plugins'], $p_key))? false : true;
                            echo '<td class="altcheckbox">' . self::altcheckbox("plfactive[$pgtype][$p_key]", $checked, '<span class="dashicons dashicons-admin-plugins"></span>') . '</td>';
                        }
                        echo "</tr>";
                    }
                }
            }
            else if(in_array( $p_key, $selplugins )) {
                $p_name = $this->pluginkey_to_name($p_key);                
                echo "<tr><td>$p_name</td>";
                foreach($chklist as $pgtype){
                    $checked = (empty($filter['group'][$pgtype]['plugins']) || false === strpos($filter['group'][$pgtype]['plugins'], $p_key))? false : true;
                    echo '<td class="altcheckbox">' . self::altcheckbox("plfactive[$pgtype][$p_key]", $checked, '<span class="dashicons dashicons-admin-plugins"></span>') . '</td>';
                }
                echo "</tr>";
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
            <?php $this->plfregist_table($this->plugins_inf, $this->filter); ?>
            <br />
            <p><?php _e('If you want to filter the loading of plugins, click select <span class="dashicons dashicons-admin-plugins"></span> mark of the filter type.', 'plf') ?></p>
            <p><strong>[ Admin Filter ]</strong><br />
              <?php _e('Plugins to be used only in admin mode.', 'plf'); ?><br />
            </p>
            <p><strong>[ Desktop/Mobile Filter ]</strong><br />
              <?php _e('Plugins to be used only in desktop/moble device. (wp_is_mobile function use)', 'plf'); ?><br />
            </p>
            <p><strong>[ Page Filter ]</strong><br />
              <?php _e('Plugins for selecting whether to activate each Page type or Post.', 'plf'); ?><br />
              <?php _e('Selected page filter plugins are once blocked, but is activated by "Page filter Activation" setting.', 'plf'); ?><br />
            </p>
            <p class="submit">
                <input type="submit" class="button-primary" name="clear_regist_filter" value="<?php _e('Clear', 'plf'); ?>" />&nbsp;&nbsp;&nbsp;
                <input type="submit" class="button-primary" name="edit_regist_filter" value="<?php _e('Filter Entry &raquo;', 'plf'); ?>" />
            </p>
        </div>
        <div id="plf-activation-tab" >
            <?php
            $pgfilter = (!empty($this->filter['_pagefilter']['plugins']))? $this->filter['_pagefilter']['plugins'] : array();
            if(!empty($pgfilter)){
                $this->plfactive_table($this->plugins_inf, $pgfilter, $this->filter);
                ?>
                <br />
                <p><?php _e('Select plugins to be activated for each page type by clicking on <span class="dashicons dashicons-admin-plugins"></span> mark from "page filter" registered plugins.', 'plf') ?><br />
                   <?php _e('You can also select plugins to activate from Post/Page content editing screen.', 'plf') ?>
                </p>
                <p class="submit">
                  <input type="submit" class="button-primary" name="clear_activate_page_filter" value="<?php _e('Clear', 'plf'); ?>" />&nbsp;&nbsp;&nbsp;
                  <input type="submit" class="button-primary" name="edit_activate_page_filter" value="<?php _e('Activate Plugin Entry &raquo;', 'plf'); ?>" />
                </p>
                <?php
            }
            else {
                ?>
                <br />
                <p><span style="color: #ff0000;"><?php _e('Page Filter is not registered', 'plf') ?></span></p>
                <?php
            }
            ?>
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