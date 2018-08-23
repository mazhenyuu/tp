<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Think;
/**
 * ThinkPHP内置的Dispatcher类
 * 完成URL解析、路由和调度
 */
class Dispatcher {

    /**
     * URL映射到控制器
     * @access public
     * @return void
     */
    static public function dispatch() {
        $varPath        =   C('VAR_PATHINFO');///s
        $varAddon       =   C('VAR_ADDON');
        $varModule      =   C('VAR_MODULE'); ///m
        $varController  =   C('VAR_CONTROLLER');///c
        $varAction      =   C('VAR_ACTION');///a
        $urlCase        =   C('URL_CASE_INSENSITIVE');///true

        ///var_dump($varPath,$varAddon,$varModule,$varController,$varAction,$urlCase);
        ///string(1) "s" string(5) "addon" string(1) "m" string(1) "c" string(1) "a" bool(false)
        /*
         * dump($_SERVER);die;默认是没有PATH_INFO 兼容模式s=***存入$_GET，pathinfo模式/Home/Ok/Index在index.php后边索引会存入!!
        pathinfo模式 http://www.com/mooc_video/tp/index.php/Home/Index/Index----$_SERVER['PATH_INFO']默认会存到$_SERVER中
        普通模式    http://www.com/mooc_video/tp/index.php?m=Home&c=Index&a=Index
        兼容模式    http://www.com/mooc_video/tp/index.php?s=Home/Index/index---下面的判断会存入到PATH_INFO中
        没有后缀模式 http://www.com/mooc_video/tp/index.php
        */

        if(isset($_GET[$varPath])) { // 判断URL里面是否有兼容模式参数
            $_SERVER['PATH_INFO'] = $_GET[$varPath];
            unset($_GET[$varPath]);
        }elseif(IS_CLI){ // CLI模式下 index.php module/controller/action/params/...
            $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
        }

        // 开启子域名部署
        if(C('APP_SUB_DOMAIN_DEPLOY')) {
            $rules      = C('APP_SUB_DOMAIN_RULES');
            if(isset($rules[$_SERVER['HTTP_HOST']])) { // 完整域名或者IP配置
                define('APP_DOMAIN',$_SERVER['HTTP_HOST']); // 当前完整域名
                $rule = $rules[APP_DOMAIN];
            }else{
                if(strpos(C('APP_DOMAIN_SUFFIX'),'.')){ // com.cn net.cn 
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -3);
                }else{
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);                    
                }
                if(!empty($domain)) {
                    $subDomain = implode('.', $domain);
                    define('SUB_DOMAIN',$subDomain); // 当前完整子域名
                    $domain2   = array_pop($domain); // 二级域名
                    if($domain) { // 存在三级域名
                        $domain3 = array_pop($domain);
                    }
                    if(isset($rules[$subDomain])) { // 子域名
                        $rule = $rules[$subDomain];
                    }elseif(isset($rules['*.' . $domain2]) && !empty($domain3)){ // 泛三级域名
                        $rule = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    }elseif(isset($rules['*']) && !empty($domain2) && 'www' != $domain2 ){ // 泛二级域名
                        $rule      = $rules['*'];
                        $panDomain = $domain2;
                    }
                }                
            }

            if(!empty($rule)) {
                // 子域名部署规则 '子域名'=>array('模块名[/控制器名]','var1=a&var2=b');
                if(is_array($rule)){
                    list($rule,$vars) = $rule;
                }
                $array      =   explode('/',$rule);
                // 模块绑定
                define('BIND_MODULE',array_shift($array));
                // 控制器绑定         
                if(!empty($array)) {
                    $controller  =   array_shift($array);
                    if($controller){
                        define('BIND_CONTROLLER',$controller);
                    }
                }
                if(isset($vars)) { // 传入参数
                    parse_str($vars,$parms);
                    if(isset($panDomain)){
                        $pos = array_search('*', $parms);
                        if(false !== $pos) {
                            // 泛域名作为参数
                            $parms[$pos] = $panDomain;
                        }                         
                    }                   
                    $_GET   =  array_merge($_GET,$parms);
                }
            }
        }

        /*如果不是兼容模式或者pathinfo模式的判断
        *http://www.com/mooc_video/tp/index.php
        *http://www.com/mooc_video/tp/index.php?m=Home&c=Index&a=Index
        *http://www.com/mooc_video/tp/index.php?s=Home/Index/index
        *http://www.com/mooc_video/tp/index.php/Home/Index/Index
        *http://www.com/mooc_video/tp/index.php?123=Home/Index/index
        */
        //dump($_SERVER['PATH_INFO']);die;


        // 分析PATHINFO信息
        if(!isset($_SERVER['PATH_INFO'])) {
            $types   =  explode(',',C('URL_PATHINFO_FETCH'));
            foreach ($types as $type){
                ///判断字符串中是否首位是：
                if(0===strpos($type,':')) {// 支持函数判断
                    $_SERVER['PATH_INFO'] =   call_user_func(substr($type,1));
                    break;
                }elseif(!empty($_SERVER[$type])) {
                    ///判断$_SERVER中是否有--字符串
                    $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type],$_SERVER['SCRIPT_NAME']))?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME']))   :  $_SERVER[$type];
                    break;
                }
            }
        }

        $depr = C('URL_PATHINFO_DEPR');
        define('MODULE_PATHINFO_DEPR',  $depr);

        /*只有兼容模式和pathinfo模式不为空--其他为空
         *dump($_SERVER['PATH_INFO']);die;
         * */

        if(empty($_SERVER['PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = '';
            define('__INFO__','');
            define('__EXT__','');
        }else{
            ///Home/Index/index或者/Home/Index/Index
            define('__INFO__',trim($_SERVER['PATH_INFO'],'/'));
            // URL后缀
            ///获取后缀并转为小写
            define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'],PATHINFO_EXTENSION)));
            $_SERVER['PATH_INFO'] = __INFO__;
            ///如果是多入口？？？
            if(!defined('BIND_MODULE') && (!C('URL_ROUTER_ON') || !Route::check())){
                if (__INFO__ && C('MULTI_MODULE')){ // 获取模块名
                    $paths      =   explode($depr,__INFO__,2);
                    /*
                     * array(2) {
                      [0] => string(4) "Home"
                      [1] => string(11) "Index/index"
                    }
                     * */
                    ///配置文件里没有添加则为null
                    $allowList  =   C('MODULE_ALLOW_LIST'); // 允许的模块列表
                    ///如果是module.html  把扩展替换为空
                    $module     =   preg_replace('/\.' . __EXT__ . '$/i', '',$paths[0]);
                    if( empty($allowList) || (is_array($allowList) && in_array_case($module, $allowList))){
                        $_GET[$varModule]       =   $module;
                        $_SERVER['PATH_INFO']   =   isset($paths[1])?$paths[1]:'';
                    }
                }
            }             
        }
        /*
         *var_dump( $_GET[$varModule],$_SERVER['PATH_INFO']);die;
         * *pathinfo和兼容模式输出string(4) "Home" string(11) "Index/index" 或者string(4) "Home" string(11) "Index/Index"
         * 其他模式为null活'';
         */

        // URL常量
        /*获取REQUEST_URI除域名外其他部分
         * /mooc_video/tp/index.php---没有path的跳到默认Home模块
         * /mooc_video/tp/index.php?m=Home&c=Index&a=Index-->普通模式直接将url参数存在$_GET参数中["m"=>"Admin","c"=>"Index","a"=>"Index"]
         * /mooc_video/tp/index.php?s=Home/Index/index--->兼容模式 会跳到指定的模块下Home或者Admin
         * /mooc_video/tp/index.php/Home/Index/Index--->pathinfo模式 会跳到指定的模块下Home或者Admin
         * /mooc_video/tp/index.php/Home/Index/Index.html--->pathinfo模式 会跳到指定的模块下Home或者Admin
         * /mooc_video/tp/index.php?123=Admin/Index/index---如此输入错误的$_GET[$varModule]中没有模块--跳到默认Home模块
         * */
        define('__SELF__',strip_tags($_SERVER[C('URL_REQUEST_URI')]));
        // 获取模块名称
        define('MODULE_NAME', defined('BIND_MODULE')? BIND_MODULE : self::getModule($varModule));
        ///dump(MODULE_NAME);DIE;
        /*如果是普通模式--直接过去url中的模块，pathinfo和兼容模式存在$_GET[$varmodule]中（通过拆分/后第一个获取）
         * 其他情况默认请求Home
         * */

        // 检测模块是否存在
        if( MODULE_NAME && (defined('BIND_MODULE') || !in_array_case(MODULE_NAME,C('MODULE_DENY_LIST')) ) && is_dir(APP_PATH.MODULE_NAME)){
            // 定义当前模块路径
            define('MODULE_PATH', APP_PATH.MODULE_NAME.'/');
            // 定义当前模块的模版缓存路径
            C('CACHE_PATH',CACHE_PATH.MODULE_NAME.'/');
            // 定义当前模块的日志目录
	        C('LOG_PATH',  realpath(LOG_PATH).'/'.MODULE_NAME.'/');

            // 模块检测
            Hook::listen('module_check');

            // 加载模块配置文件
            if(is_file(MODULE_PATH.'Conf/config'.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/config'.CONF_EXT));
            // 加载应用模式对应的配置文件
            if('common' != APP_MODE && is_file(MODULE_PATH.'Conf/config_'.APP_MODE.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/config_'.APP_MODE.CONF_EXT));
            // 当前应用状态对应的配置文件
            if(APP_STATUS && is_file(MODULE_PATH.'Conf/'.APP_STATUS.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/'.APP_STATUS.CONF_EXT));

            // 加载模块别名定义
            if(is_file(MODULE_PATH.'Conf/alias.php'))
                Think::addMap(include MODULE_PATH.'Conf/alias.php');
            // 加载模块tags文件定义
            if(is_file(MODULE_PATH.'Conf/tags.php'))
                Hook::import(include MODULE_PATH.'Conf/tags.php');
            // 加载模块函数文件
            if(is_file(MODULE_PATH.'Common/function.php'))
                include MODULE_PATH.'Common/function.php';
            
            $urlCase        =   C('URL_CASE_INSENSITIVE');
            // 加载模块的扩展配置文件
            load_ext_file(MODULE_PATH);
        }else{
            E(L('_MODULE_NOT_EXIST_').':'.MODULE_NAME);
        }

        if(!defined('__APP__')){
	        $urlMode        =   C('URL_MODEL');
	        if($urlMode == URL_COMPAT ){// 兼容模式判断
	            define('PHP_FILE',_PHP_FILE_.'?'.$varPath.'=');
	        }elseif($urlMode == URL_REWRITE ) {
	            $url    =   dirname(_PHP_FILE_);
	            if($url == '/' || $url == '\\')
	                $url    =   '';
	            define('PHP_FILE',$url);
	        }else {
	            define('PHP_FILE',_PHP_FILE_);
	        }
	        // 当前应用地址
	        define('__APP__',strip_tags(PHP_FILE));
	    }
	    /**
	    echo PHP_FILE;die;  /mooc_video/tp/index.php
        */

        // 模块URL地址
        $moduleName    =   defined('MODULE_ALIAS')? MODULE_ALIAS : MODULE_NAME;
        ///__MODULE__
        define('__MODULE__',(defined('BIND_MODULE') || !C('MULTI_MODULE'))? __APP__ : __APP__.'/'.($urlCase ? strtolower($moduleName) : $moduleName));
        ///echo __MODULE__;die; /mooc_video/tp/index.php/Home


        ///pathinfo模式或者兼容模式
        if('' != $_SERVER['PATH_INFO'] && (!C('URL_ROUTER_ON') ||  !Route::check()) ){   // 检测路由规则 如果没有则按默认规则调度URL
            Hook::listen('path_info');
            // 检查禁止访问的URL后缀
            if(C('URL_DENY_SUFFIX') && preg_match('/\.('.trim(C('URL_DENY_SUFFIX'),'.').')$/i', $_SERVER['PATH_INFO'])){
                send_http_status(404);
                exit;
            }

            ///注意此处由于169行的处理$_SERVER['PATH_INFO']已经只剩下"控制器和方法"了
            // 去除URL后缀
            $_SERVER['PATH_INFO'] = preg_replace(C('URL_HTML_SUFFIX')? '/\.('.trim(C('URL_HTML_SUFFIX'),'.').')$/i' : '/\.'.__EXT__.'$/i', '', $_SERVER['PATH_INFO']);
            $depr   =   C('URL_PATHINFO_DEPR');
            $paths  =   explode($depr,trim($_SERVER['PATH_INFO'],$depr));

            if(!defined('BIND_CONTROLLER')) {// 获取控制器
                if(C('CONTROLLER_LEVEL')>1){// 控制器层次
                    $_GET[$varController]   =   implode('/',array_slice($paths,0,C('CONTROLLER_LEVEL')));
                    $paths  =   array_slice($paths, C('CONTROLLER_LEVEL'));
                }else{
                    ///解析控制器--写入$_GET['c']===>最后通过调用类中方法赋值给常量
                    $_GET[$varController]   =   array_shift($paths);
                }
            }
            ///dump($_GET);die;

            ///解析方法---写入$_GET['a']===>最后通过调用类中方法赋值给常量
            if(!defined('BIND_ACTION')){
                $_GET[$varAction]  =   array_shift($paths);
            }
            // 解析剩余的URL参数
            $var  =  array();
            if(C('URL_PARAMS_BIND') && 1 == C('URL_PARAMS_BIND_TYPE')){
                // URL参数按顺序绑定变量
                $var    =   $paths;
            }else{
                preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$var){$var[$match[1]]=strip_tags($match[2]);}, implode('/',$paths));
            }
            $_GET   =  array_merge($var,$_GET);
        }

        // 获取控制器的命名空间（路径）
        define('CONTROLLER_PATH',   self::getSpace($varAddon,$urlCase));

        // 获取控制器和操作名
        define('CONTROLLER_NAME',   defined('BIND_CONTROLLER')? BIND_CONTROLLER : self::getController($varController,$urlCase));
        define('ACTION_NAME',       defined('BIND_ACTION')? BIND_ACTION : self::getAction($varAction,$urlCase));

        // 当前控制器的UR地址
        $controllerName    =   defined('CONTROLLER_ALIAS')? CONTROLLER_ALIAS : CONTROLLER_NAME;
        define('__CONTROLLER__',__MODULE__.$depr.(defined('BIND_CONTROLLER')? '': ( $urlCase ? parse_name($controllerName) : $controllerName )) );

        // 当前操作的URL地址
        define('__ACTION__',__CONTROLLER__.$depr.(defined('ACTION_ALIAS')?ACTION_ALIAS:ACTION_NAME));

        //保证$_REQUEST正常取值
        $_REQUEST = array_merge($_POST,$_GET,$_COOKIE);	// -- 加了$_COOKIE.  保证哦..
    }

    /**
     * 获得控制器的命名空间路径 便于插件机制访问
     */
    static private function getSpace($var,$urlCase) {
        $space  =   !empty($_GET[$var])?strip_tags($_GET[$var]):'';
        unset($_GET[$var]);
        return $space;
    }

    /**
     * 获得实际的控制器名称
     */
    static private function getController($var,$urlCase) {
        $controller = (!empty($_GET[$var])? $_GET[$var]:C('DEFAULT_CONTROLLER'));
        unset($_GET[$var]);
        if($maps = C('URL_CONTROLLER_MAP')) {
            if(isset($maps[strtolower($controller)])) {
                // 记录当前别名
                define('CONTROLLER_ALIAS',strtolower($controller));
                // 获取实际的控制器名
                return   ucfirst($maps[CONTROLLER_ALIAS]);
            }elseif(array_search(strtolower($controller),$maps)){
                // 禁止访问原始控制器
                return   '';
            }
        }

        if($urlCase) {
            // URL地址不区分大小写
            // 智能识别方式 user_type 识别到 UserTypeController 控制器
            $controller = parse_name($controller,1);
        }
        return strip_tags(ucfirst($controller));
    }

    /**
     * 获得实际的操作名称
     */
    static private function getAction($var,$urlCase) {
        $action   = !empty($_POST[$var]) ?
            $_POST[$var] :
            (!empty($_GET[$var])?$_GET[$var]:C('DEFAULT_ACTION'));
        unset($_POST[$var],$_GET[$var]);
        if($maps = C('URL_ACTION_MAP')) {
            if(isset($maps[strtolower(CONTROLLER_NAME)])) {
                $maps =   $maps[strtolower(CONTROLLER_NAME)];
                if(isset($maps[strtolower($action)])) {
                    // 记录当前别名
                    define('ACTION_ALIAS',strtolower($action));
                    // 获取实际的操作名
                    if(is_array($maps[ACTION_ALIAS])){
                        parse_str($maps[ACTION_ALIAS][1],$vars);
                        $_GET   =   array_merge($_GET,$vars);
                        return $maps[ACTION_ALIAS][0];
                    }else{
                        return $maps[ACTION_ALIAS];
                    }
                    
                }elseif(array_search(strtolower($action),$maps)){
                    // 禁止访问原始操作
                    return   '';
                }
            }
        }
        return strip_tags( $urlCase? strtolower($action) : $action );
    }

    /**
     * 获得实际的模块名称
     */
    static private function getModule($var) {
        $module   = (!empty($_GET[$var])?$_GET[$var]:C('DEFAULT_MODULE'));
        unset($_GET[$var]);
        if($maps = C('URL_MODULE_MAP')) {
            if(isset($maps[strtolower($module)])) {
                // 记录当前别名
                define('MODULE_ALIAS',strtolower($module));
                // 获取实际的模块名
                return   ucfirst($maps[MODULE_ALIAS]);
            }elseif(array_search(strtolower($module),$maps)){
                // 禁止访问原始模块
                return   '';
            }
        }
        return strip_tags(ucfirst($module));
    }

}
