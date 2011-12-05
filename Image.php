<?php
 /**
 * Image
 *
 * @copyright   Copyright 2011 Sergey Cherepanov. (http://cherepanov.org.ua)
 * @author      Sergey Cherepanov (sergey@cherepanov.org.ua)
 * @date        05.12.11
 */

class Image_Exception extends Exception
{
    
}

class Image
{
    const IMAGE_TYPE_JPG = 'jpg';
    const IMAGE_TYPE_GIF = 'gif';
    const IMAGE_TYPE_PNG = 'png';

    const RESIZE_METHOD_FIT   = 'fit';
    const RESIZE_METHOD_CROP  = 'crop';
    const RESIZE_METHOD_SCALE = 'scale';

    protected $queue              = array();
    protected $createEmpty        = false;
    protected $imageChanged       = false;

    /** @var resource */
    protected $sourceImage;
    protected $sourceImagePath    = null;
    protected $sourceImageType    = null;

    protected $sourceImageWidth   = null;
    protected $sourceImageHeight  = null;

    /** @var resource */
    protected $newImage;
    protected $newImagePath       = null;
    
    protected $imageType       = null;
    protected $quality    = 75;

    protected $newImageWidth     = null;
    protected $newImageHeight    = null;

    protected $backgroundColor = 'alpha';
    protected $resizeMethod    = 'fit';
    protected $align           = 'center';
    protected $verticalAlign   = 'middle';

    protected $_fontDir = 'fonts';

    public function __construct($filePath = null)
    {
        if ($filePath) {
            $this->setSourceImagePath($filePath);
        }
    }

    /**
     * @return null|string
     */
    public function getSourceImagePath()
    {
        return $this->sourceImagePath;
    }

    /**
     * @param string $path
     * @return Image
     */
    public function setSourceImagePath($path)
    {
        $this->sourceImagePath = $path;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getNewImagePath()
    {
        return $this->newImagePath;
    }

    /**
     * @param string $path
     * @return Image
     */
    public function setNewImagePath($path)
    {
        $this->newImagePath = $path;
        return $this;
    }

    /**
     * @param int $value
     * @return Image
     */
    public function setQuality($value)
    {
        $this->quality = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getSourceImageWidth()
    {
        return $this->sourceImageWidth;
    }

    /**
     * @return int
     */
    public function getSourceImageHeight()
    {
        return $this->sourceImageHeight;
    }

    /**
     * @return int|null
     */
    public function getWidth()
    {
        if ($this->newImage) {
            return $this->newImageWidth;
        } else {
            return $this->sourceImageWidth;
        }
    }

    /**
     * @return null
     */
    public function getHeight()
    {
        if ($this->newImage) {
            return $this->newImageHeight;
        } else {
            return $this->sourceImageHeight;
        }
    }

    /**
     * @return string
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * @param string $color
     * @return Image
     */
    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
        return $this;
    }

    public function createEmptyImage($width = 1, $height = 1)
    {
        if ($width < 1 || $height < 1) {
            throw new Image_Exception('Please define correct image size.');
        }
        $this->sourceImageWidth  = $width;
        $this->sourceImageHeight = $height;
        $this->createEmpty = true;
    }

    /**
     * @return Image
     */
    protected function _createEmptyImage()
    {
        $width  = $this->getSourceImageWidth();
        $height = $this->getSourceImageHeight();
        $this->sourceImage = imagecreatetruecolor($width, $height);
        $this->fill($this->sourceImage, $width, $height, $this->getBackgroundColor());
        return $this;
    }

    protected function sendHeaders()
    {
        $type  = $this->getImageType();
        switch ($type) {
            case self::IMAGE_TYPE_JPG:
                header ("content-type: image/jpeg");
                break;
            case self::IMAGE_TYPE_PNG:
                header ("content-type: image/png");
                break;
            case self::IMAGE_TYPE_GIF:
                header ("content-type: image/gif");
                break;
        }
        return $this;
    }

    /**
     * @throws Image_Exception
     * @param bool $save
     * @return Image
     */
    protected function output($save = false)
    {
        $this->getSourceImage();
        $this->_applyChanges();

        $image = $this->getImage();
        $type  = $this->getImageType();

        if (!is_resource($image)){
            throw new Image_Exception('Image resource not defined.');
        }

        if (!$save) {
            // Send http headers
            $this->sendHeaders();
        } else {
            if (!$this->getNewImagePath()) {
                throw new Image_Exception('Destination path not defined.');
            }
        }

        // Save or render image
        switch ($type) {
            case self::IMAGE_TYPE_JPG:
                $quality = (int) $this->quality;
                imagejpeg(
                    $image,
                    ($save ? $this->getNewImagePath() : null),
                    $quality
                );
                break;
            case self::IMAGE_TYPE_PNG:
                $quality = (int) $this->quality % 10;
                imagepng(
                    $image,
                    ($save ? $this->getNewImagePath() : null),
                    $quality
                );
                break;
            case self::IMAGE_TYPE_GIF:
                imagegif(
                    $image,
                    ($save ? $this->getNewImagePath() : null)
                );
                break;
        }
        return $this;
    }

    /**
     * @return Image
     */
    public function render()
    {
        $this->output();
        return $this;
    }

    /**
     * @param null $filePath
     * @return Image
     */
    public function save($filePath = null)
    {
        if ($filePath) {
            $this->setNewImagePath($filePath);
        }
        $this->output(true);
        return $this;
    }

    public function clear()
    {
        if ($this->sourceImage) {
            imagedestroy($this->sourceImage);
        }
        if ($this->newImage) {
            imagedestroy($this->newImage);
        }
        return $this;
    }

    protected function _openSourceImage()
    {
        $filePath = $this->getSourceImagePath();
        if ($filePath && is_file($filePath)) {
            $this->clear();
            $imageInfo = getimagesize($filePath);
            $this->sourceImageWidth  = $imageInfo[0];
            $this->sourceImageHeight = $imageInfo[1];

            switch($imageInfo[2]) {
                case 1:
                    $this->sourceImage = imagecreatefromgif($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_GIF;
                    break;
                case 2:
                    $this->sourceImage = imagecreatefromjpeg($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_JPG;
                    break;
                case 3:
                    $this->sourceImage = imagecreatefrompng($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_PNG;
                    break;
            }
        }
        return $this;
    }

    protected function _addQueue($queueKey, $methodName, $arguments = array())
    {
        $this->queue[$queueKey] = array(
            'method'   => $methodName,
            'arguments' => $arguments,
        );
    }

    protected function _applyChanges()
    {
        if ($this->imageChanged) {
            foreach ($this->queue as $task) {
                $this->{$task['method']}($task['arguments']);
            }
            $this->imageChanged = false;
        }
    }

    public function resize($width = null, $height = null, $method = null)
    {
        $this->imageChanged = true;

        if ($width) {
            $this->newImageWidth = intval($width);
        }
        if ($height) {
            $this->newImageHeight = intval($height);
        }
        if ($method) {
            $this->resizeMethod = $method;
        }
        $this->_addQueue('resize', '_resize');

    }

    protected function _resize()
    {
        $sourceImage       = $this->sourceImage;
        $sourceImageWidth  = $this->sourceImageWidth;
        $sourceImageHeight = $this->sourceImageHeight;

        $newImageWidth     = $this->newImageWidth;
        $newImageHeight    = $this->newImageHeight;

        $method            = $this->resizeMethod;
        $align             = $this->align;
        $verticalAlign     = $this->verticalAlign;
        $background        = $this->backgroundColor;


        if ($sourceImage && ($newImageWidth | $newImageHeight)) {
            if ($this->newImage) {
                imagedestroy($this->newImage);
            }

            if (!$newImageHeight) {
                // if not set height output
                $newImageHeight   = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
            } elseif (!$newImageWidth) {
                //if not set width output
                $newImageWidth    = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
            }

            $this->newImageWidth  = $newImageWidth;
            $this->newImageHeight = $newImageHeight;

            if ($newImageWidth > $sourceImageWidth && $newImageHeight > $sourceImageHeight):
                //if source image less output image
                $newWidth  = $sourceImageWidth;
                $newHeight = $sourceImageHeight;

                if (!$newImageWidth):
                    $newImageWidth  = $sourceImageWidth;
                endif;

                if(!$newImageHeight):
                    $newImageHeight = $sourceImageHeight;
                endif;
            else:
                //if source image greater output image
                $newWidth   = $newImageWidth;
                $newHeight  = $newImageHeight;

                $fix_width  = (($sourceImageWidth / $newImageWidth * $newImageHeight) >= $sourceImageHeight);
                $fix_height = (($sourceImageHeight / $newImageHeight * $newImageWidth) >= $sourceImageWidth);


                switch($method):
                    case self::RESIZE_METHOD_FIT:
                    default:
                        if ($fix_width) {
                            //fit to width
                            $newHeight = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
                        } elseif ($fix_height) {
                            //fit to height
                            $newWidth  = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
                        }
                        break;
                    case self::RESIZE_METHOD_CROP:
                        if ($fix_width) {
                            //crop height
                            $newWidth  = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
                        } elseif($fix_height) {
                            //crop width
                            $newHeight = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
                        }
                        break;
                    case self::RESIZE_METHOD_SCALE:
                        //continue
                        break;
                endswitch;
            endif;

            switch ($align) {
                case('center'):default:
                    $newImageX = round($newImageWidth / 2 - $newWidth / 2);
                    break;
                case('left'):default:
                    $newImageX = 0;
                    break;
                case('right'):default:
                    $newImageX = $newImageWidth - $newWidth;
                    break;
            }

            switch ($verticalAlign) {
                case('middle'):default:
                    $newImageY = round(($newImageHeight / 2) - ($newHeight / 2));
                    break;
                case('top'):
                    $newImageY = 0;
                    break;
                case('bottom'):
                    $newImageY = $newImageHeight - $newHeight;
                    break;
            }

            $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);
            $this->fill($newImage, $newImageWidth, $newImageHeight, $background);

            imagecopyresampled($newImage, $sourceImage, $newImageX, $newImageY, 0, 0, $newWidth, $newHeight, $sourceImageWidth, $sourceImageHeight);
            $this->newImage = $newImage;

        }
        return $this;
    }

    

    private function fill($resource, $width, $height, $color = 'transparent')
    {
        if ($color == 'alpha' || $color == 'transparent') {
            imagesavealpha ($resource, true);
            imagealphablending ($resource, false);
            $fillColor = imagecolorallocatealpha($resource, 0, 0, 0, 127);
        } else {
            if (!$color) {
                $color = 'ffffff';
            }
            //$fillColor = array();
            if (strlen($color) < 6) {
                $color = preg_replace('/([\w\d]){1}/', '$1$1', substr($color, 0, 3));
            }
            preg_match_all('/[\w\d]{2}/', substr($color, 0, 6), $color);
            $fillColor = imagecolorallocate($resource, hexdec($color[0][0]), hexdec($color[0][1]), hexdec($color[0][2]));

        }
        imagefilledrectangle($resource, 0, 0, $width, $height, $fillColor);
        return $this;
    }


    public function writeText($args = array())
    {
        $this->imageChanged = true;
        $defaults = array (
            'content'    => '',
            'fontSize'   => '12',
            'fontName'   => 'arial.ttf',
            'color'      => '000000',
            'lineHeight' => null,
            'blockWidth' => null,
            'positionX'  => 0,
            'positionY'  => 0,
        );

        $args = array_merge($defaults, $args);
        $queueKey =  'writeText' . implode('', $args);
        $this->_addQueue($queueKey, '_writeText', $args);
    }

    protected function _writeText($args = array())
    {
        $content     = $args['content'];
        $fontSize    = $args['fontSize'];
        $fontName    = $args['fontName'];
        $color       = $args['color'];
        $lineHeight  = $args['lineHeight'];
        $blockWidth  = $args['blockWidth'];
        $positionX   = $args['positionX'];
        $positionY   = $args['positionY'];

        $image    = $this->getImage();
        $content  = trim($content);
        $fontPath = $this->_fontDir . '/' . $fontName;

        if (!file_exists($fontPath)) {
            throw new Image_Exception("Font file not found \"{$fontPath}\"");
        }

        if($content){
            if(!$blockWidth){
                $blockWidth = $this->getWidth();
            }
            if(!$lineHeight){
                $lineHeight = abs(7 * ($fontSize / 10));
            }
            $spaceSymbol    = imagettfbbox ($fontSize, 0, $fontPath, ' ');
            $spaceSize      = abs($spaceSymbol[2] - $spaceSymbol[0]);
            $content        = explode(' ', $content);
            /*$contentWidth   = 0;
            $contentHeight  = $fontSize;*/
            $contentBoxWidth  = $blockWidth == 'auto' ? 0 : $blockWidth;
            $contentBoxHeight = $this->getHeight();
            $contentInfo = array();
            $textColor       = array();
            if(strlen($color) < 6){
                $color = preg_replace('/([\w\d]){1}/', '$1$1', substr($color, 0, 3));
            }
            preg_match_all('/[\w\d]{2}/', $color, $textColor);
            $color = imagecolorallocate($this->getImage(), hexdec($textColor[0][0]), hexdec($textColor[0][1]), hexdec($textColor[0][2]));
            while (false !== ($word = current($content))) {
                $nextWord      = next($content);
                $wordBox       = imagettfbbox ($fontSize, 0, $fontPath, $word);
                $wordWidth     = abs($wordBox[2] - $wordBox[0]);
                $wordHeight    = abs($wordBox[7] - $wordBox[1]);
                if($blockWidth == 'auto'){
                    $contentBoxWidth     += $wordWidth;
                    if($nextWord){
                        $contentBoxWidth += $spaceSize;
                    }
                    if($contentBoxHeight < $wordHeight){
                        $contentBoxHeight = $wordHeight;
                    }
                }
                $contentInfo[] = array(
                    'width'    => $wordWidth,
                    'height'   => $wordHeight
                );
            }
            reset($content);
            reset($contentInfo);


            $position['x']        = $positionX;
            $position['y']        = $positionY + $fontSize;
            $position['width']    = 0;


            if($blockWidth == 'auto'){
                $width = $contentBoxWidth + $spaceSize;
                if($contentBoxHeight > $this->getHeight()){
                    $contentBoxHeight += $lineHeight;
                }
                $this->resize($width, $contentBoxHeight, 'scale');
            }
            //$image = $this->getImage();
            while (false !== ($word = current($content)) && false !== ($wordInfo = current($contentInfo))) {
                next($content);
                $nextWordInfo = next($contentInfo);
                imagettftext($image, $fontSize, 0, $position['x'], $position['y'], $color, $fontPath, $word);
                $position['x'] += $wordInfo['width'];
                if ($nextWordInfo) {
                    $position['x'] += $spaceSize;
                }
                if (($position['x'] + $nextWordInfo['width']) > $contentBoxWidth) {
                    $position['x'] = 0;
                    $position['y'] += $nextWordInfo['height'] + $lineHeight;
                }
            }
        }
        return $this;
    }

    /**
     * @return resource
     */
    public function getImage()
    {
        if ($this->newImage) {
            return $this->newImage;
        } else {
            return $this->getSourceImage();
        }
    }

    /**
     * @return string|null
     */
    public function getImageType()
    {
        if ($this->imageType) {
            return $this->imageType;
        } else {
            return $this->sourceImageType;
        }
    }

    /**
     * @param string $type
     * @return Image
     */
    public function setImageType($type)
    {
        $this->imageType = $type;
        return $this;
    }

    /**
     * @return resource
     */
    public function getSourceImage()
    {
        if (!$this->sourceImage) {
            if ($this->createEmpty) {
                $this->_createEmptyImage();
            } else {
                $this->_openSourceImage();
            }
        }
        return $this->sourceImage;
    }
}
