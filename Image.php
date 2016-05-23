<?php

/**
 * GD2 Library
 *
 * @copyright   Copyright 2016 Serhii Cherepanov (https://github.com/SergeyCherepanov)
 * @author      Sergey Cherepanov (sergey@cherepanov.org.ua)
 * @license     Creative Commons Attribution 3.0 License
 */

namespace PhpImage;

/**
 * Class Exception
 * @package PhpImage
 */
class Exception extends \Exception
{

}

/**
 * Class Image
 * @package PhpImage
 */
class Image
{
    const IMAGE_TYPE_JPG        = 'jpg';
    const IMAGE_TYPE_GIF        = 'gif';
    const IMAGE_TYPE_PNG        = 'png';
    const RESIZE_METHOD_FIT     = 'fit';
    const RESIZE_METHOD_CROP    = 'crop';
    const RESIZE_METHOD_SCALE   = 'scale';
    const ALIGN_LEFT            = 'left';
    const ALIGN_RIGHT           = 'right';
    const ALIGN_CENTER          = 'center';
    const VERTICAL_ALIGN_TOP    = 'top';
    const VERTICAL_ALIGN_BOTTOM = 'bottom';
    const VERTICAL_ALIGN_MIDDLE = 'middle';
    const COLOR_TRANSPARENT     = 'transparent';
    const COLOR_ALPHA           = 'alpha'; // alias for transparent

    protected $queue        = [];
    protected $imageChanged = false;

    /** @var resource */
    protected $sourceImage;
    protected $sourceImagePath = null;
    protected $sourceImageType = null;

    protected $sourceImageWidth  = 1;
    protected $sourceImageHeight = 1;

    /** @var resource */
    protected $image;
    protected $imagePath   = null;
    protected $imageWidth  = null;
    protected $imageHeight = null;
    protected $imageType   = null;
    protected $quality     = 75;

    protected $backgroundColor = 'FFFFFF';
    protected $resizeMethod    = self::RESIZE_METHOD_FIT;
    protected $align           = self::ALIGN_CENTER;
    protected $verticalAlign   = self::VERTICAL_ALIGN_MIDDLE;

    protected $_fontDir = 'fonts';

    /**
     * Image constructor.
     * @param null $filePath
     */
    public function __construct($filePath = null)
    {
        $this->setSourceImagePath($filePath);
    }

    /**
     * @return null|string
     */
    public function getSourceImagePath()
    {
        return $this->sourceImagePath;
    }

    /**
     * @param null|string $path
     * @return $this
     */
    public function setSourceImagePath($path = null)
    {
        $this->imageChanged    = true;
        $this->sourceImagePath = $path;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getImagePath()
    {
        return $this->imagePath;
    }

    /**
     * @param string $path
     * @return Image
     */
    public function setImagePath($path)
    {
        $this->imagePath = $path;

        return $this;
    }

    /**
     * @param int $value
     * @return Image
     */
    public function setQuality($value)
    {
        $this->imageChanged = true;
        $this->quality      = $value;

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
        if ($this->image) {
            return $this->imageWidth;
        }

        return $this->sourceImageWidth;
    }

    /**
     * @return null
     */
    public function getHeight()
    {
        if ($this->image) {
            return $this->imageHeight;
        }

        return $this->sourceImageHeight;
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
        $this->imageChanged    = true;
        $this->backgroundColor = $color;

        return $this;
    }

    /**
     * @param int $width
     * @param int $height
     * @throws Exception
     */
    public function createEmptyImage($width = null, $height = null)
    {
        $width  = (int) $width  ?: $this->sourceImageWidth;
        $height = (int) $height ?: $this->sourceImageHeight;
        if ($width < 1 || $height < 1) {
            throw new Exception('Please define correct image size.');
        }
        $this->sourceImageWidth  = $width;
        $this->sourceImageHeight = $height;
        $this->sourceImagePath   = null;
    }

    /**
     * @return Image
     */
    protected function _createEmptyImage()
    {
        $width             = $this->getSourceImageWidth();
        $height            = $this->getSourceImageHeight();
        $this->sourceImage = imagecreatetruecolor($width, $height);
        $this->fill($this->sourceImage, $width, $height, $this->getBackgroundColor());

        return $this;
    }

    /**
     * @throws Exception
     * @param string|null $filePath
     * @return Image
     */
    public function save($filePath = null)
    {
        $this->getSourceImage();
        $this->_applyChanges();

        $image = $this->getImage();
        $type  = $this->getImageType();

        if ($filePath) {
            if (!is_dir(dirname($filePath)) || !is_writable(dirname($filePath))) {
                throw new Exception('Destination dir not found or not writable');
            }
        }

        // Save or render image
        switch ($type) {
            case self::IMAGE_TYPE_JPG:
            default:
                $quality = (int) $this->quality;
                imagejpeg(
                    $image,
                    $filePath,
                    $quality
                );
                break;
            case self::IMAGE_TYPE_PNG:
                $quality = (int) $this->quality % 10;
                imagepng(
                    $image,
                    $filePath,
                    $quality
                );
                break;
            case self::IMAGE_TYPE_GIF:
                imagegif(
                    $image,
                    $filePath
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
        $this->save();

        return $this;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        if ($this->sourceImage) {
            imagedestroy($this->sourceImage);
        }
        if ($this->image) {
            imagedestroy($this->image);
        }
        $this->queue = [];

        return $this;
    }

    /**
     * @return $this
     */
    protected function _openSourceImage()
    {
        $filePath = $this->getSourceImagePath();
        if ($filePath && is_file($filePath)) {
            $this->clear();
            $imageInfo               = getimagesize($filePath);
            $this->sourceImageWidth  = $imageInfo[0];
            $this->sourceImageHeight = $imageInfo[1];

            switch ($imageInfo[2]) {
                case 1:
                    $this->sourceImage     = imagecreatefromgif($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_GIF;
                    break;
                case 2:
                    $this->sourceImage     = imagecreatefromjpeg($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_JPG;
                    break;
                case 3:
                    $this->sourceImage     = imagecreatefrompng($filePath);
                    $this->sourceImageType = self::IMAGE_TYPE_PNG;
                    break;
            }
        }

        return $this;
    }

    /**
     * @param $queueKey
     * @param $methodName
     * @param array $arguments
     * @return $this
     */
    protected function _addJob($queueKey, $methodName, $arguments = [])
    {
        $this->queue[$queueKey] = [
            'method'    => $methodName,
            'arguments' => $arguments,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    protected function _applyChanges()
    {
        if ($this->imageChanged) {
            foreach ($this->queue as $task) {
                $this->{$task['method']}($task['arguments']);
            }
            $this->imageChanged = false;
        }

        return $this;
    }

    /**
     * @param int|null $width
     * @param int|null $height
     * @param string|null $method
     * @return $this
     */
    public function resize($width = null, $height = null, $method = null)
    {
        $this->imageChanged = true;

        if ($width) {
            $this->imageWidth = intval($width);
        }
        if ($height) {
            $this->imageHeight = intval($height);
        }
        if ($method) {
            $this->resizeMethod = $method;
        }
        $this->_addJob('resize', '_resize');

        return $this;
    }

    /**
     * Resize action
     *
     * @return $this
     */
    protected function _resize()
    {
        $sourceImage       = $this->sourceImage;
        $sourceImageWidth  = $this->sourceImageWidth;
        $sourceImageHeight = $this->sourceImageHeight;

        $newImageWidth  = $this->imageWidth;
        $newImageHeight = $this->imageHeight;

        $method        = $this->resizeMethod;
        $align         = $this->align;
        $verticalAlign = $this->verticalAlign;
        $background    = $this->backgroundColor;


        if ($sourceImage && ($newImageWidth | $newImageHeight)) {
            if ($this->image) {
                imagedestroy($this->image);
            }

            if (!$newImageHeight) {
                // if not set height output
                $newImageHeight = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
            } elseif (!$newImageWidth) {
                //if not set width output
                $newImageWidth = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
            }

            $this->imageWidth  = $newImageWidth;
            $this->imageHeight = $newImageHeight;

            if ($newImageWidth > $sourceImageWidth && $newImageHeight > $sourceImageHeight):
                //if source image less output image
                $newWidth  = $sourceImageWidth;
                $newHeight = $sourceImageHeight;

                if (!$newImageWidth):
                    $newImageWidth = $sourceImageWidth;
                endif;

                if (!$newImageHeight):
                    $newImageHeight = $sourceImageHeight;
                endif;
            else:
                //if source image greater output image
                $newWidth  = $newImageWidth;
                $newHeight = $newImageHeight;

                $fix_width  = (($sourceImageWidth / $newImageWidth * $newImageHeight) >= $sourceImageHeight);
                $fix_height = (($sourceImageHeight / $newImageHeight * $newImageWidth) >= $sourceImageWidth);

                switch ($method):
                    case self::RESIZE_METHOD_FIT:
                    default:
                        if ($fix_width) {
                            //fit to width
                            $newHeight = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
                        } elseif ($fix_height) {
                            //fit to height
                            $newWidth = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
                        }
                        break;
                    case self::RESIZE_METHOD_CROP:
                        if ($fix_width) {
                            //crop height
                            $newWidth = ceil($newImageHeight / $sourceImageHeight * $sourceImageWidth);
                        } elseif ($fix_height) {
                            //crop width
                            $newHeight = ceil($newImageWidth / $sourceImageWidth * $sourceImageHeight);
                        }
                        break;
                    case self::RESIZE_METHOD_SCALE:
                        //continue
                        break;
                endswitch;
            endif;

            switch (strtolower($align)) {
                case(self::ALIGN_CENTER):
                default:
                    $newImageX = round($newImageWidth / 2 - $newWidth / 2);
                    break;
                case(self::ALIGN_LEFT):
                    $newImageX = 0;
                    break;
                case(self::ALIGN_RIGHT):
                    $newImageX = $newImageWidth - $newWidth;
                    break;
            }

            switch ($verticalAlign) {
                case(self::VERTICAL_ALIGN_MIDDLE):
                default:
                    $newImageY = round(($newImageHeight / 2) - ($newHeight / 2));
                    break;
                case(self::VERTICAL_ALIGN_TOP):
                    $newImageY = 0;
                    break;
                case(self::VERTICAL_ALIGN_BOTTOM):
                    $newImageY = $newImageHeight - $newHeight;
                    break;
            }

            $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);
            $this->fill($newImage, $newImageWidth, $newImageHeight, $background);

            imagecopyresampled($newImage, $sourceImage, $newImageX, $newImageY, 0, 0, $newWidth, $newHeight, $sourceImageWidth, $sourceImageHeight);
            $this->image = $newImage;
        }

        return $this;
    }

    /**
     * Fill area with color
     *
     * @param $resource
     * @param int $width
     * @param int $height
     * @param string $color
     * @return $this
     */
    protected function fill($resource, $width, $height, $color = self::COLOR_TRANSPARENT)
    {
        $color = strtolower($color);
        if ($color == self::COLOR_TRANSPARENT || $color == self::COLOR_ALPHA) {
            imagesavealpha($resource, true);
            imagealphablending($resource, false);
            $fillColor = imagecolorallocatealpha($resource, 0, 0, 0, 127);
        } else {
            if (0 === strpos($color, '#')) {
                $color = preg_replace('/^#(\w+)$/', '$1', $color);
            }
            if (!$color || !in_array(strlen($color), [3, 6])) {
                $color = 'ffffff';
            }
            if (3 == strlen($color)) {
                $color = preg_replace('/([\w\d]){1}/', '$1$1', substr($color, 0, 3));
            }
            preg_match_all('/[\w\d]{2}/', $color, $color);
            $fillColor = imagecolorallocate($resource, hexdec($color[0][0]), hexdec($color[0][1]), hexdec($color[0][2]));

        }
        imagefilledrectangle($resource, 0, 0, $width, $height, $fillColor);

        return $this;
    }

    /**
     * Add write text to queue
     *
     * @param array $args
     * @return $this
     */
    public function writeText($args = [])
    {
        $this->imageChanged = true;
        $defaults           = [
            'content'       => '',
            'fontSize'      => '12',
            'fontName'      => 'arial.ttf',
            'color'         => '000000',
            'lineHeight'    => null,
            'blockWidth'    => null,
            'positionX'     => 0,
            'positionY'     => 0,
            'align'         => self::ALIGN_LEFT,
            'verticalAlign' => self::VERTICAL_ALIGN_TOP,
        ];

        $args     = array_merge($defaults, $args);
        $queueKey = 'writeText' . implode('', $args);
        $this->_addJob($queueKey, '_writeText', $args);

        return $this;
    }

    /**
     * Write text on the image
     *
     * @param array $args
     * @return $this
     * @throws Exception
     */
    protected function _writeText($args = [])
    {

        $content       = $args['content'];
        $fontSize      = $args['fontSize'];
        $fontName      = $args['fontName'];
        $color         = $args['color'];
        $lineHeight    = $args['lineHeight'];
        $blockWidth    = $args['blockWidth'];
        $positionX     = $args['positionX'];
        $positionY     = $args['positionY'];
        $align         = $args['align'];
        $verticalAlign = $args['verticalAlign'];

        $image    = $this->getImage();
        $content  = trim($content);
        $fontPath = __DIR__ . '/' . $this->_fontDir . '/' . $fontName;

        if (!is_file($fontPath)) {
            throw new Exception("Font file not found \"{$fontPath}\"");
        }

        if ($content) {
            if (!$blockWidth) {
                $blockWidth = $this->getWidth();
            }
            if (!$lineHeight) {
                $lineHeight = abs(7 * ($fontSize / 10));
            }
            $spaceSymbol = imagettfbbox($fontSize, 0, $fontPath, ' ');
            $spaceSize   = abs($spaceSymbol[2] - $spaceSymbol[0]);
            $content     = explode(' ', $content);
            /*$contentWidth   = 0;
            $contentHeight  = $fontSize;*/
            $contentBoxWidth  = $blockWidth == 'auto' ? 0 : $blockWidth;
            $contentBoxHeight = $this->getHeight();
            $contentInfo      = [];
            $textColor        = [];
            if (strlen($color) < 6) {
                $color = preg_replace('/([\w\d]){1}/', '$1$1', substr($color, 0, 3));
            }
            preg_match_all('/[\w\d]{2}/', $color, $textColor);
            $color = imagecolorallocate($this->getImage(), hexdec($textColor[0][0]), hexdec($textColor[0][1]), hexdec($textColor[0][2]));
            while (false !== ($word = current($content))) {
                $nextWord   = next($content);
                $wordBox    = imagettfbbox($fontSize, 0, $fontPath, $word);
                $wordWidth  = abs($wordBox[2] - $wordBox[0]);
                $wordHeight = abs($wordBox[7] - $wordBox[1]);
                if ($blockWidth == 'auto') {
                    $contentBoxWidth += $wordWidth;
                    if ($nextWord) {
                        $contentBoxWidth += $spaceSize;
                    }
                    //if ($contentBoxHeight < $wordHeight) {
                    $contentBoxHeight = $wordHeight;
                    //}
                }
                $contentInfo[] = [
                    'width'  => $wordWidth,
                    'height' => $wordHeight,
                ];
            }
            reset($content);
            reset($contentInfo);

            switch ($align) {
                case self::ALIGN_LEFT:
                default:
                    $position['x'] = $positionX + $spaceSize;
                    break;
                case self::ALIGN_RIGHT:
                    $position['x'] = $this->getWidth() - $contentBoxWidth - $positionX - $spaceSize;
                    break;
                case self::ALIGN_CENTER:
                    $position['x'] = $this->getWidth() / 2 - $contentBoxWidth / 2 - $positionX;
                    break;
            }

            switch ($verticalAlign) {
                case self::VERTICAL_ALIGN_TOP:
                default:
                    $position['y'] = $positionY + $fontSize;
                    break;
                case self::VERTICAL_ALIGN_BOTTOM:
                    $position['y'] = $this->getHeight() - $contentBoxHeight;
                    break;
                case self::VERTICAL_ALIGN_MIDDLE:
                    $position['y'] = $this->getHeight() / 2 - $contentBoxHeight / 2 + $fontSize;
                    break;
            }

//            if ($blockWidth == 'auto') {
//                $width = $contentBoxWidth + $spaceSize;
//                if ($contentBoxHeight > $this->getHeight()) {
//                    $contentBoxHeight += $lineHeight;
//                }
//                $this->resize($width, $contentBoxHeight, 'scale');
//            }

            $relativeX = 0;
            while (false !== ($word = current($content)) && false !== ($wordInfo = current($contentInfo))) {
                next($content);
                $nextWordInfo = next($contentInfo);
                imagettftext($image, $fontSize, 0, $position['x'] + $relativeX, $position['y'], $color, $fontPath, $word);
                $relativeX += $wordInfo['width'];
                if ($nextWordInfo) {
                    $relativeX += $spaceSize;
                }
                if (($relativeX + $nextWordInfo['width']) > $contentBoxWidth) {
                    $relativeX = 0;
                    $position['y'] += $nextWordInfo['height'] + $lineHeight;
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve image resource
     *
     * @return resource
     */
    public function getImage()
    {
        if ($this->image) {
            return $this->image;
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
        }

        return $this->sourceImageType;
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
            if ($this->sourceImagePath) {
                $this->_openSourceImage();
            } else {
                $this->_createEmptyImage();
            }
        }

        return $this->sourceImage;
    }

    /**
     * @return resource
     */
    public function getSourceImageType()
    {
        if (!$this->sourceImageType) {
            if ($this->sourceImagePath) {
                $this->_openSourceImage();
            } else {
                $this->_createEmptyImage();
            }
        }

        return $this->sourceImageType;
    }
}
