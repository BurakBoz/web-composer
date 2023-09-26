<?php
/**
 * Web Composer © 2023 by Burak Boz is licensed under Attribution 4.0 International.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by/4.0/
 */
@header("Content-type: text/html;charset=utf8");
@header("X-Software: " . WebComposer::$agent);
@error_reporting(E_ALL);
@ini_set('display_errors', 1);
@set_time_limit(600);
@ini_set('max_execution_time', 600);
@ini_set('memory_limit', '-1');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
if (function_exists('apache_setenv'))
{
    @apache_setenv('no-gzip', '1');
    @apache_setenv('dont-vary', '1');
}

WebComposer::init([
    'command' => 'install',
    //'--no-dev' => true,
    '--optimize-autoloader' => true,
    '--no-interaction' => true,
    '--no-progress' => true,
    //'--verbose' => true,
]);

class WebComposer
{
    public static $version = "0.0.1";
    public static $agent = "WebComposer/v0.0.1";
    public static $maxScanDepth = 3;
    public static $path = __DIR__ . "/";
    public static $composerPhar = 'composer.phar';
    public static $composerJson = 'composer.json';
    public static function init($params = [], $path = null, $composerJson = null, $composerPhar = null)
    {
        !is_null($path) && is_dir($path) && self::$path = $path;
        !is_null($composerJson) && file_exists($composerJson) && self::$composerJson = $composerJson;
        !is_null($composerPhar) && file_exists($composerPhar) && self::$composerPhar = $composerPhar;
        self::header();
        register_shutdown_function(static function(){ WebComposer::footer(); });
        echo "<line><success>".WebComposer::$agent." - PHP ".PHP_VERSION."</success></line>";
        self::scanJson();
        self::scanPhar();
        self::downloadComposer();
        if(!self::checkFile(self::$composerPhar))
        {
            exit("<error>composer.phar file is missing.</error>");
        }
        self::runComposer($params);
    }
    public static function runComposer($params = [])
    {
        require_once 'phar://' . str_replace('\\', '/', self::$composerPhar) . '/src/bootstrap.php';
        if(function_exists('putenv'))
        {
            @\putenv('COMPOSER_HOME=' . self::$path . '/vendor/bin/composer');
            @\putenv('COMPOSER_DISABLE_XDEBUG_WARN=1');
        }
        create_output_class();
        $output = new HtmlOutput();
        if(empty($params))
        {
            $params = [
                'command' => 'install',
                '--no-dev' => true,
                '--optimize-autoloader' => true,
                '--no-interaction' => true,
                '--no-progress' => true
                //'--verbose' => true
            ];
        }
        try {
            $input = new Symfony\Component\Console\Input\ArrayInput($params);
            $application = new Composer\Console\Application();
            $application->setAutoExit(false);
            $application->run($input, $output);
        } catch (Exception $ex) {
            $output->writeln($ex->getMessage());
        }
    }
    public static function disableOutputBuffering()
    {
        ob_implicit_flush(true);
        while (ob_get_level() > 0)
        {
            $level = ob_get_level();
            ob_end_clean();
            if (ob_get_level() === $level)
            {
                break;
            }
        }
    }
    public static function convertLinks($str)
    {
        $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
        return preg_replace_callback("#$pattern#i", static function($matches) {
            $input = $matches[0];
            $url = preg_match('!^https?://!i', $input) ? $input : "http://$input";
            return '<a href="' . htmlspecialchars($url) . '" rel="noopener noreferrer nofollow" target="_blank">' . htmlspecialchars($input)."</a>";
        }, $str);
    }

    public static function downloadComposer()
    {
        if (!self::checkFile(self::$composerPhar))
        {
            echo "<line><info>Downloading composer.phar</info></line>";
            $download = self::downloadFile('https://getcomposer.org/composer.phar', self::$composerPhar);
            echo $download
                ? "<success>composer.phar downloaded!</success>"
                : "<error>Error: cannot download composer.phar!</error>";
            return $download;
        }
        return true;
    }
    public static function scanPhar()
    {
        for ($i=0;$i<=self::$maxScanDepth;$i++)
        {
            $currentPath = self::$path . ($i > 0 ? str_repeat("../",$i) : "");
            if (file_exists($currentPath . self::$composerPhar))
            {
                echo "<line><info>Found composer.phar on $currentPath</info></line>";
                self::$composerPhar = $currentPath . self::$composerPhar;
                return true;
            }
        }
        return false;
    }
    public static function scanJson()
    {
        for ($i=0;$i<=self::$maxScanDepth;$i++)
        {
            $currentPath = self::$path . ($i > 0 ? str_repeat("../",$i) : "");
            if (file_exists($currentPath . self::$composerJson))
            {
                echo "<line><info>Current working directory: $currentPath</info></line>";
                @chdir($currentPath);
                self::$composerJson = $currentPath . self::$composerJson;
                self::$path = $currentPath;
                return true;
            }
        }
        return false;
    }
    public static function downloadFile($url, $file, $options = [])
    {
        return self::curlDownload($url, $file, $options) || self::fopenDownload($url, $file);
    }
    private static function checkFile($file)
    {
        @\clearstatcache(true, $file);
        @\clearstatcache(false, $file);
        if((@\filesize($file))<1)
        {
            @\unlink($file);
            return false;
        }
        return true;
    }
    private static function fopenDownload($url, $file)
    {
        if(!ini_get("allow_url_fopen"))
        {
            return false;
        }
        $context = stream_context_create([
            "http" => [
                "user_agent" => self::$agent
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        $data = file_get_contents($url, false, $context);
        if(!file_put_contents($file, $data))
        {
            return false;
        }
        return self::checkFile($file);
    }
    private static function curlDownload($url, $file, array $options = [])
    {
        if(!function_exists('curl_init'))
        {
            return false;
        }
        $fp = @\fopen($file, 'wb');
        if(!@\is_resource($fp)) return false;
        $ch = @\curl_init();
        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => self::$agent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        @\curl_setopt_array($ch,@\array_replace($defaultOptions,$options));
        $status = @\curl_exec($ch);
        $hs = @\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @\curl_close($ch);
        @\fclose($fp);
        return $status && @\in_array($hs, [200, 301, 302], true) && self::checkFile($file);
    }
    public static function header()
    {
        echo <<<HTML
<!-- 
Web Composer © 2023 by Burak Boz is licensed under Attribution 4.0 International. To view a copy of this license, visit http://creativecommons.org/licenses/by/4.0/
-->
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<meta name="generator">
<title>Web Composer Installer</title>
<style>
html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,b,u,i,center,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td,article,aside,canvas,details,embed,figure,figcaption,footer,header,hgroup,main,menu,nav,output,ruby,section,summary,time,mark,audio,video{margin:0;padding:0;border:0;font-size:100%;font:inherit;vertical-align:baseline}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section{display:block}[hidden]{display:none}body{line-height:1}menu,ol,ul{list-style:none}blockquote,q{quotes:none}blockquote:before,blockquote:after,q:before,q:after{content:'';content:none}table{border-collapse:collapse;border-spacing:0}
.aligner{display:flex;align-items:center;justify-content:center}.aligner-item{max-width: 99%; margin-top: 10px;}.aligner-item--top{align-self:flex-start}.aligner-item--bottom{align-self:flex-end}
warning,error,info,comment,success{ padding: 3px; display: inline-block; border: 1px solid #ffffff1c; min-height: 16px;}
line{padding: 3px; display: block; color: #ded1d1; }
warning { 
    background: #42381b;
    color: #d8b801;
     }
    
error{ 
    background: #513036;
    color: #ff8c8e;
}
info{
    background: #303a51;
    color:#a6d3ff;
}
comment{ background: #305148;
    color:#b9baff;
     }
success{ background: #304751; color:#58e0ad; }
html,body { 
background: #434343; 
background-image: linear-gradient(to bottom, #434343 0%, black 100%);
text-align: left;
min-height: 100%;
min-height: 100dvh;
color: #e1e1e1;
font-size: 1em;
}
a { text-decoration: none; color:#b9baff; }
header { 
    padding: 10px;
    font-weight: bold;
    box-shadow: rgba(50, 50, 93, 0.25) 0px 30px 60px -12px inset, rgba(0, 0, 0, 0.3) 0px 18px 36px -18px inset;
    background-image: linear-gradient(-20deg, #6e45e2 0%, #88d3ce 100%);
    display: block;
    margin: 0 0 1em 0;
    text-align: center;
 }
 header a { color:#1c1c1e; }
footer { margin-top: 2em; border-radius: 20px; padding: 5px; }
</style>
</head>
<body>
<header class="aligner">
    <div class="aligner-item--bottom">
        <h1>
            <a href="https://github.com/BurakBoz/web-composer" target="_blank">
                WebComposer
                <svg height="16px" width="16px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 291.32 291.32" xml:space="preserve"><g><path style="fill:#000;" d="M145.66,0C65.219,0,0,65.219,0,145.66c0,80.45,65.219,145.66,145.66,145.66 s145.66-65.21,145.66-145.66C291.319,65.219,226.1,0,145.66,0z M186.462,256.625c-0.838-11.398-1.775-25.518-1.83-31.235 c-0.364-4.388-0.838-15.549-11.434-22.677c42.068-3.523,62.087-26.774,63.526-57.499c1.202-17.497-5.754-32.883-18.107-45.3 c0.628-13.282-0.401-29.023-1.256-35.941c-9.486-2.731-31.608,8.949-37.79,13.947c-13.037-5.062-44.945-6.837-64.336,0 c-13.747-9.668-29.396-15.64-37.926-13.974c-7.875,17.452-2.813,33.948-1.275,35.914c-10.142,9.268-24.289,20.675-20.447,44.572 c6.163,35.04,30.816,53.94,70.508,58.564c-8.466,1.73-9.896,8.048-10.606,10.788c-26.656,10.997-34.275-6.791-37.644-11.425 c-11.188-13.847-21.23-9.832-21.849-9.614c-0.601,0.218-1.056,1.092-0.992,1.511c0.564,2.986,6.655,6.018,6.955,6.263 c8.257,6.154,11.316,17.27,13.2,20.438c11.844,19.473,39.374,11.398,39.638,11.562c0.018,1.702-0.191,16.032-0.355,27.184 C64.245,245.992,27.311,200.2,27.311,145.66c0-65.365,52.984-118.348,118.348-118.348S264.008,80.295,264.008,145.66 C264.008,196.668,231.69,239.992,186.462,256.625z"/></g></svg>
            </a>
        </h1>
    </div>
</header>
<main class="aligner">
<div class="aligner-item">
HTML;
    }
    public static function footer()
    {
        echo <<<HTML
</div>
</main>
<footer class="aligner">
    <line>
        <warning>
            <p xmlns:cc="http://creativecommons.org/ns#" xmlns:dct="http://purl.org/dc/terms/"><a property="dct:title" rel="cc:attributionURL" href="https://github.com/BurakBoz/web-composer">Web Composer</a> by <a rel="cc:attributionURL dct:creator" property="cc:attributionName" href="https://www.burakboz.net">Burak Boz</a> is licensed under <a href="http://creativecommons.org/licenses/by/4.0/?ref=chooser-v1" target="_blank" rel="license noopener noreferrer" style="display:inline-block;">Attribution 4.0 International<img style="height:22px!important;margin-left:3px;vertical-align:text-bottom;" src="https://mirrors.creativecommons.org/presskit/icons/cc.svg?ref=chooser-v1"><img style="height:22px!important;margin-left:3px;vertical-align:text-bottom;" src="https://mirrors.creativecommons.org/presskit/icons/by.svg?ref=chooser-v1"></a></p>
        </warning>
    </line>
</footer>
</body>
</html>
HTML;
    }
}
function create_output_class()
{
    class HtmlOutput extends \Symfony\Component\Console\Output\Output
    {
        public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter = null)
        {
            parent::__construct($verbosity, $decorated, $formatter);
            WebComposer::disableOutputBuffering();
        }
        protected function terminalOutputParser($text)
        {
            return WebComposer::convertLinks(strtr(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), [
                "&lt;info&gt;"  => "<info>",
                "&lt;/info&gt;" => "</info>",
                "&lt;warning&gt;"  => "<warning>",
                "&lt;/warning&gt;" => "</warning>",
                "&lt;error&gt;"  => "<error>",
                "&lt;/error&gt;" => "</error>",
                "&lt;comment&gt;"  => "<comment>",
                "&lt;/comment&gt;" => "</comment>",
                "&lt;success&gt;"  => "<success>",
                "&lt;/success&gt;" => "</success>",
                "&lt;br&gt;" => "<br>",
            ]));
        }

        public function writeln($messages, $options = 0)
        {
            $this->write($messages, true, $options);
        }

        public function write($messages, $newline = false, $options = self::OUTPUT_NORMAL)
        {
            $this->doWrite($messages, $newline);
        }

        protected function doWrite($message, $newline = false)
        {
            echo "<line>";
            if(is_array($message))
            {
                foreach ($message as $single)
                {
                    if(is_array($single))
                    {
                        $this->doWrite($single, $newline);
                    }
                }
            }
            else
            {
                $message = str_replace("\n", "<br>\n", $message);
                echo $this->terminalOutputParser($message);
            }
            if ($newline) {
                echo "<br>\n";
            }
            echo "</line>";
            if (ob_get_length())
            {
                ob_flush();
                flush();
            }
        }
    }
}
