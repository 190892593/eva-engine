<?php
/**
 * EvaEngine Cloud Image Class
 * Make Image transformations by url
 * Usage almost as same as Cloudinary http://cloudinary.com/documentation/image_transformations
 *
 * @link      https://github.com/AlloVince/eva-engine
 * @copyright Copyright (c) 2012 AlloVince (http://avnpc.com/)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @author    AlloVince
 */
error_reporting(E_ALL);

// Check php version
if( version_compare(phpversion(), '5.3.3', '<') ) {
  printf('PHP 5.3.3 is required, you have %s', phpversion());
  exit(1);
}

/** Public functions */
function p($r, $usePr = false)
{
    echo '<pre>' . var_dump($r, true) . '</pre>';
}

$libPath = __DIR__ . '/../../../vendor';
set_include_path(implode(PATH_SEPARATOR, array(
    '.',
    $libPath,
    get_include_path(),
)));

require_once 'PHPThumb/src/ThumbLib.inc.php';

class EvaCloudImage
{
    
    protected $relativePath;
    protected $subPath;
    protected $pathlevel;
    protected $sourceImageName;
    protected $targetImageName;
    protected $uniqueTargetImageName;
    protected $imageNameArgs = array();

    protected $sourceImage;
    protected $targetImage;
    protected $url;


    protected $options = array(
        'engine' => 'GD', //or imageMagick
        'sourceRootPath' => '',
        'thumbFileRootPath' => '',
        'thumbUrlRootPath' => '',
        'smallSizeWidth' => '',
        'smallSizeHeight' => '',
        'mediumSizeWidth' => '',
        'mediumSizeHeight' => '',
        'largeSizeWidth' => '',
        'largeSizeHeight' => '',
        'maxAllowWidth' => '',
        'maxAllowHeight' => '',
        'watermark' => array(
            'enable' => false,
            'enableWidth' => 500,
            'enableHeight' => 400,
            'position' => '',
            'text' => 'watermark',
            'font' => '',
            'fontfile' => '',
        ),
        'saveImage' => true,
        'allowExpendResize' => false,
        'fileSizeLimit' => 1048576,  //1MB = 1 048 576 bytes
    );

    protected $argMapping = array(
        'w' => 'width',
        'h' => 'height',
        'q' => 'quality',
        'c' => 'crop',
        'x' => 'x',
        'y' => 'y',
        'r' => 'rotate',
    );
    protected $transferParameters = array(
        'width' => null,
        'height' => null,
        'quality' => null, 
        'crop' => null,
        'x' => null,
        'y' => null,
        'rotate' => null,
    );
    protected $transferParametersMerged = false;

    public function getUniqueUrl()
    {
        $url = $this->url;
        $urlArray = explode('/', $url);
        array_pop($urlArray);
        array_push($urlArray, $this->getUniqueTargetImageName());

        return implode('/', $urlArray);
    }

    protected function argsToParameters()
    {
        $args = $this->imageNameArgs;
        $argMapping = $this->argMapping;
        $params = array();
        foreach($args as $arg){
            if(!$arg){
                continue;
            }
            if(strpos($arg, '_') !== 1){
                continue;
            }
            $argKey = $arg{0};
            if(isset($argMapping[$argKey])){
                $params[$argMapping[$argKey]] = substr($arg, 2);
            }
        }

        return $params;
    }

    protected function parametersToString(array $params = array())
    {
        $params = $params ? $params : $this->getTransferParameters();
        $argMapping = array_flip($this->argMapping);
        $args = array();
        foreach($params as $key => $param){
            if(!$param){
                continue;
            }
            if(isset($argMapping[$key])){
                $args[$key] = $argMapping[$key] . '_' . $param;
            }
        }
        ksort($args);
        return implode(',', $args);
    }

    public function getTransferParameters()
    {
        if(true === $this->transferParametersMerged){
            return $this->transferParameters;
        }

        $params = $this->argsToParameters();
        $this->transferParametersMerged = true;
        return $this->transferParameters = array_merge($this->transferParameters, $params);
    }


    protected function getCurrentUrl()
    {
        $pageURL = 'http';

        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on"){
            $pageURL .= "s";
        }
        $pageURL .= "://";

        if ($_SERVER["SERVER_PORT"] != "80"){
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        }
        else {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    protected function getRelativePath()
    {
        if($this->relativePath){
            return $this->relativePath;
        }

        $options = $this->options;
        $relativePath = str_replace($options['thumbUrlRootPath'], '', $options['thumbFileRootPath']);
        if($relativePath) {
            $relativePath = trim($relativePath, '/\\');
        }

        return $this->relativePath = $relativePath;
    }

    protected function getSubPath($urlPath = null)
    {
        if(!empty($this->subPath)){
            return $this->subPath;
        }

        if(!$urlPath){
            $url = $this->url;
            $url = parse_url($url);
            $urlPath = $url['path'];
        }

        if(!$urlPath){
            return $this->subPath = '';
        }

        $relativePath = '/' . str_replace('\\', '/', $this->getRelativePath());
        $filePath = str_replace($relativePath, '', $urlPath);
        $filePath = trim(str_replace('/', DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);

        $pathArray = explode(DIRECTORY_SEPARATOR, $filePath);
        //remove file extension
        array_pop($pathArray);
        $this->pathlevel = count($pathArray);
        $filePath = implode(DIRECTORY_SEPARATOR, $pathArray);
        return $this->subPath = $filePath;
    }

    public function getTargetImage()
    {
        if($this->targetImage){
            return $this->targetImage;
        }

        $options = $this->options;
        $subPath = $this->getSubPath();
        $fileName = $this->getTargetImageName();
        $uniqueName = $this->getUniqueTargetImageName();

        return $this->targetImage = $options['thumbFileRootPath'] . DIRECTORY_SEPARATOR . $subPath . DIRECTORY_SEPARATOR . $uniqueName;
    }

    public function getTargetImageName($urlPath = null)
    {
        if($this->targetImageName){
            return $this->targetImageName;
        }
        $url = $this->url;
        $url = parse_url($url);
        $urlPath = $url['path'];

        $urlArray = explode('/', $urlPath);
        $fileName = $urlArray[count($urlArray) - 1];
        $fileNameArray = $fileName ? explode('.', $fileName) : array();
        if(!$fileNameArray || !isset($fileNameArray[1]) || !$fileNameArray[0] || !$fileNameArray[1]){
            throw new InvalidArgumentException('File name not correct');
        }

        return $this->targetImageName = $fileName;
    }

    public function getUniqueTargetImageName()
    {
        if($this->uniqueTargetImageName){
            return $this->uniqueTargetImageName;
        }

        $sourceImageName = $this->getSourceImageName();

        $argString = $this->parametersToString();
        if(!$argString){
            return $this->uniqueTargetImageName = $sourceImageName;
        }
        $nameArray = explode('.', $sourceImageName);
        $nameExt = array_pop($nameArray);
        $nameFinal = array_pop($nameArray);
        $nameFinal .= ',' . $argString;
        array_push($nameArray, $nameFinal, $nameExt);
        $uniqueName = implode('.', $nameArray);
        return $this->uniqueTargetImageName = $uniqueName;
    }

    public function getSourceImage()
    {
        if($this->sourceImage){
            return $this->sourceImage;
        }

        $url = $this->url;
        $options = $this->options;

        $url = parse_url($url);
        if(!$url || !$url['path']){
            throw new InvalidArgumentException('Url not able to parse');
        }

        $sourceImageName = $this->getSourceImageName($url['path']);
        $subPath = $this->getSubPath($url['path']);
        return $this->sourceImage = $options['sourceRootPath'] . DIRECTORY_SEPARATOR . $subPath . DIRECTORY_SEPARATOR . $sourceImageName; 
    }

    public function getSourceImageName($urlPath = null)
    {
        if($this->sourceImageName){
            return $this->sourceImageName;
        }

        if(!$urlPath){
            $url = $this->url;
            $url = parse_url($url);
            $urlPath = $url['path'];
        }

        $urlArray = explode('/', $urlPath);
        $fileName = $urlArray[count($urlArray) - 1];
        $fileNameArray = $fileName ? explode('.', $fileName) : array();
        if(!$fileNameArray || count($fileNameArray) < 2){
            throw new InvalidArgumentException('File name not correct');
        }

        $this->targetImageName = $fileName;

        $fileExt = array_pop($fileNameArray);

        //TODO : add ext check

        $fileNameMain = implode('.', $fileNameArray);
        $fileNameArray = explode(',', $fileNameMain);
        if(!$fileExt || !$fileNameArray || !$fileNameArray[0]){
            throw new InvalidArgumentException('File name not correct');
        }

        $fileNameMain = array_shift($fileNameArray);
        $this->imageNameArgs = $fileNameArray;
        return $this->sourceImageName = $fileNameMain . '.' . $fileExt;
    }

    public function show()
    {
        $sourceImage = $this->getSourceImage();
        $targetImage = $this->getTargetImage();

        if(false === file_exists($sourceImage)){
            return header('HTTP/1.1 404 Not Found');
        }

        $url = $this->getUniqueUrl();
        if($this->url != $url){
            //header("HTTP/1.1 301 Moved Permanently");
            //return header('Location:' . $url);
        }

        //$this->prepareDirectoryStructure($targetImage, $this->pathlevel);
        $thumb = PhpThumbFactory::create($sourceImage);
        $this->transferImage($thumb);
        $thumb->show(); 
        //$thumb->save($targetImage)->show(); 
    }

    public function transferImage(GdThumb $thumb)
    {
        $params = $this->getTransferParameters();
        if($params['width'] || $params['height']) {

            //Convert string to float or int
            $params['width'] = $params['width'] ? $params['width'] + 0 : null;
            $params['height'] = $params['height'] ? $params['height'] + 0 : null;

            if(is_int($params['width']) && is_int($params['height'])){
                $thumb->resize($params['width'], $params['height']);
            } elseif(is_int($params['width']) || is_int($params['height'])){
                //resize by fixed number first
                $params['width'] = !$params['width'] || is_float($params['width']) ? 0 : $params['width'];
                $params['height'] = !$params['height'] || is_float($params['height']) ? 0 : $params['height'];
                $thumb->resize($params['width'], $params['height']);
            } else {
                $percent = $params['width'];
                $percent = !$percent || $percent > 0 && $percent < $params['height'] ? $params['height'] : $percent;
                $percent = $percent * 100;
                $thumb->resizePercent($percent);
            }

        }

        if($params['rotate']){
            $allowRotate = array('CW', 'CCW');
            if(is_numeric($params['rotate'])){
                $thumb->rotateImageNDegrees($params['rotate']);
            } elseif(in_array($params['rotate'], $allowRotate)) {
                $thumb->rotateImage($params['rotate']);
            }
        }

        if($params['quality']){
            $thumb->setOptions(array(
                'jpegQuality' => $params['quality']
            ));
        }
        return $thumb;
    }

    public function __construct($url = null, array $options = array())
    {
        $url = $url ? $url : $this->getCurrentUrl();
        $this->url = $url;
        $this->options = $options = array_merge($this->options, $options);
        //$params = $this->getTransferParameters();
        //$paramsString = $this->parametersToString($params);
        //p($paramsString);
    }


    /**
     * Prepares a directory structure for the given file(spec)
     * using the configured directory level.
     *
     * @param string $file
     * @return void
     * @throws Exception\RuntimeException
     */
    protected function prepareDirectoryStructure($file, $level = '')
    {
        //$level   = $this->pathlevel;

        // Directory structure is required only if directory level > 0
        if (!$level) {
            return;
        }

        // Directory structure already exists
        $pathname = dirname($file);
        if (file_exists($pathname)) {
            return;
        }

        $perm     = 0700;
        $umask    = false;

        if ($umask !== false && $perm !== false) {
            $perm = $perm & ~$umask;
        }

        //ErrorHandler::start();

        if ($perm === false || $level == 1) {
            // build-in mkdir function is enough

            $umask = ($umask !== false) ? umask($umask) : false;
            $res   = mkdir($pathname, ($perm !== false) ? $perm : 0777, true);

            if ($umask !== false) {
                umask($umask);
            }

            if (!$res) {
                $oct = ($perm === false) ? '777' : decoct($perm);
                //$err = ErrorHandler::stop();
                throw new Exception\RuntimeException(
                    "mkdir('{$pathname}', 0{$oct}, true) failed", 0, $err
                );
            }

            if ($perm !== false && !chmod($pathname, $perm)) {
                $oct = decoct($perm);
                //$err = ErrorHandler::stop();
                throw new Exception\RuntimeException(
                    "chmod('{$pathname}', 0{$oct}) failed", 0, $err
                );
            }

        } else {
            // build-in mkdir function sets permission together with current umask
            // which doesn't work well on multo threaded webservers
            // -> create directories one by one and set permissions

            // find existing path and missing path parts
            $parts = array();
            $path  = $pathname;
            while (!file_exists($path)) {
                array_unshift($parts, basename($path));
                $nextPath = dirname($path);
                if ($nextPath === $path) {
                    break;
                }
                $path = $nextPath;
            }

            // make all missing path parts
            foreach ($parts as $part) {
                $path.= DIRECTORY_SEPARATOR . $part;

                // create a single directory, set and reset umask immediatly
                $umask = ($umask !== false) ? umask($umask) : false;
                $res   = mkdir($path, ($perm === false) ? 0777 : $perm, false);
                if ($umask !== false) {
                    umask($umask);
                }

                if (!$res) {
                    $oct = ($perm === false) ? '777' : decoct($perm);
                    //$err = ErrorHandler::stop();
                    throw new Exception\RuntimeException(
                        "mkdir('{$path}', 0{$oct}, false) failed"
                    );
                }

                if ($perm !== false && !chmod($path, $perm)) {
                    $oct = decoct($perm);
                    //$err = ErrorHandler::stop();
                    throw new Exception\RuntimeException(
                        "chmod('{$path}', 0{$oct}) failed"
                    );
                }
            }
        }

        //ErrorHandler::stop();
    }
}

$cloudImage = new EvaCloudImage(null, include 'config.php');
$cloudImage->show();
