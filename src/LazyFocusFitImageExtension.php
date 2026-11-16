<?php

namespace EvansHunt\LazyFocusFit;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

class LazyFocusFitImageExtension extends Extension
{
    private static $default_webp = false;

    private $pictureClassString;
    private $pictureSrcArray;
    private $autoSizesBoolean;
    private $serveWp = false;

    public $IsObjectFit = false;
    public $AttributeString;
    public $ImgAttributeString;
    public $AspectRatio;
    public $IsNaturalWidth = false;
    public $ImgSrcset = '';
    public $ImgSrc = '';

    private static $casting = [
        'AttributeString' => 'HTMLVarchar',
        'ImgAttributeString' => 'HTMLVarchar'
    ];

    /**
     * ResponsivePicture - generates a <picture> element with lazyloaded responsive sizing
     *
     * First argument should be className
     * All subsequent arguments should be '{ImageWidth}-{WidthDescriptor}-{PixelDensityDescriptor} {MinWidth}`
     *
     * Width and Pixel density descriptors are optional as defined here:
     * https://developer.mozilla.org/en-US/docs/Web/HTML/Element/source#Attributes
     *
     * MinWidth should be a screen width for a (min-width: xxx) media query, or 'default'
     * Start with largest width and work your way down to default
     *
     * Example:
     * $Image.ResponsivePicture(classname, 770-1x 1440-2x 992px, 496-1x 992-2x default)
     */

    public function ResponsivePicture(...$arguments)
    {
        $arguments = $this->unwrapArguments($arguments);
        if (!$this->getOwner()->Exists()) {
            return null;
        }

        $this->populateResponsiveImageAttributes($arguments);

        return $this->getOwner()->renderWith('EvansHunt\\LazyFocusFit\\Picture');
    }

    /**
     * ResponsiveImg - generates a <img> element with lazyloaded responsive sizing
     *
     * First argument should be className
     * All subsequent arguments should be image widths in pixels
     * Lazysizes will insert the correct image according to the rendered size
     *
     * Example:
     * $Image.ResponsivePicture(classname, 800, 600, 300)
     */

    public function ResponsiveImg(...$arguments)
    {
        $arguments = $this->unwrapArguments($arguments);
        if (!$this->getOwner()->Exists()) {
            return null;
        }

        $srcsetArray = [];

        $this->pictureClassString = array_shift($arguments);

        foreach ($arguments as $argument) {
            $sourceItems = explode(' ', $argument);
            $size = array_shift($sourceItems);

            if (is_numeric($size)) {
                $image = $this->croppedImage($size);
                $srcsetArray[] =  $image->URL . ' ' . $image->Width . 'w';
            }
        }

        $this->getOwner()->ImgSrcset = implode(", ", $srcsetArray);

        return $this->getOwner()->renderWith('EvansHunt\\LazyFocusFit\\Img');
    }

    /**
     * ResponsiveBgAttributes - generates html attributes element with a lazyloaded responsive image background
     *
     * First argument should be className
     * All subsequent arguments should be '{ImageWidth}-{WidthDescriptor}-{PixelDensityDescriptor} {MinWidth}`
     *
     * Width and Pixel density descriptors are optional as defined here:
     * https://developer.mozilla.org/en-US/docs/Web/HTML/Element/source#Attributes
     *
     * MinWidth should be a screen width for a (min-width: xxx) media query, or 'default'
     * Start with largest width and work your way down to default
     *
     * Example:
     * <div $Image.ResponsiveBgAttributes(classname, 770-1x 1440-2x 992px, 496-1x 992-2x default)>
     *     ...
     * </div>
     */

    public function ResponsiveBgAttributes(...$arguments)
    {
        $arguments = $this->unwrapArguments($arguments);
        $this->populateResponsiveImageAttributes($arguments);

        return $this->getOwner()->renderWith('EvansHunt\\LazyFocusFit\\BackgroundAttributes');
    }

    /**
     * SS6 passes template variables into methods wrapped in ViewLayerData: use their string values
     */
    private function unwrapArguments(array $arguments): array
    {
        return array_map(function ($argument) {
            return is_object($argument) ? (string) $argument : $argument;
        }, $arguments);
    }

    private function populateResponsiveImageAttributes($arguments)
    {
        $this->pictureClassString = array_shift($arguments);
        $this->pictureSrcArray = array_map(function ($source) {
            $sourceItems = explode(' ', $source);
            $minWidth = array_pop($sourceItems);
            $minWidth = $minWidth === 'default' ? '' : $minWidth;

            return [
                'Sizes' => $this->getOwner()->exists() ? ArrayList::create(array_map(function ($item) {
                    $params = explode('-', $item);
                    $size = array_shift($params);
                    $image = $this->croppedImage($size);

                    return ArrayData::create([
                        'URL' => $image ? $image->URL : '',
                        'Descriptors' => join(' ', $params)
                    ]);
                }, $sourceItems)) : ArrayList::create(),
                'MinWidth' => $minWidth,
                'AutoSizes' => array_reduce($sourceItems, function ($carry, $item) {
                    return $carry || strpos($item, 'w') !== false;
                })
            ];
        }, $arguments);

        $this->autoSizesBoolean = array_reduce($this->pictureSrcArray, function ($carry, $item) {
            return $carry || $item['AutoSizes'];
        });
    }

    public function PictureClass()
    {
        return $this->pictureClassString;
    }

    public function PictureSources()
    {
        return ArrayList::create(array_map(function ($source) {
            return ArrayData::create($source);
        }, $this->pictureSrcArray ?: []));
    }

    // If using a value other than image title for alt tag
    // logic can be added here
    // @Todo: add extension point
    public function Caption()
    {
        return $this->getOwner()->Title;
    }

    public function TinySrc()
    {
        $image = $this->croppedImage(32);

        return $image ? $image->URL : '';
    }

    private function croppedImage($size)
    {
        // SVGs have no height, so we can't crop them
        if ($this->getOwner()->exists() && $this->getOwner()->Height) {
            $originalRatio = $this->getOwner()->Width / $this->getOwner()->Height;

            if ($this->getOwner()->AspectRatio) {
                if ($this->getOwner()->AspectRatio > $originalRatio) {
                    $scaledImage = $this->getOwner()
                        ->FocusCropHeight(round($this->getOwner()->Width / $this->getOwner()->AspectRatio))
                        ->ScaleMaxWidth($size);
                } else {
                    $scaledImage = $this->getOwner()
                        ->FocusCropWidth(round($this->getOwner()->Height * $this->getOwner()->AspectRatio))
                        ->ScaleMaxWidth($size);
                }
            } else {
                $scaledImage = $this->getOwner()->ScaleMaxWidth($size);
            }

            // Webp() comes from a separate module (e.g. bigfork/silverstripe-webp-formatter); without one, serve as is
            $useWebp = $this->getOwner()->config()->get('default_webp') || $this->getOwner()->serveWebP;
            return ($useWebp && $scaledImage->hasMethod('Webp'))
                ? $scaledImage->Webp()
                : $scaledImage;
        }

        return $this->getOwner();
    }

    public function AutoSizes()
    {
        return $this->autoSizesBoolean;
    }

    // Adds any attribute to the <picture> element returned by ResponsivePicture() or the <img> element
    // returned by ResponsiveImg
    public function AddAttribute($name, $value = null)
    {
        $this->getOwner()->AttributeString .= $this->getOwner()->AttributeString ? ' ' : '';
        $this->getOwner()->AttributeString .= $this->attribute($name, $value);

        return $this->getOwner();
    }

    // Adds any attribute to the <img> element returned by ResponsivePicture()
    public function AddImgAttribute($name, $value = null)
    {
        $this->getOwner()->ImgAttributeString .= $this->getOwner()->ImgAttributeString ? ' ' : '';
        $this->getOwner()->ImgAttributeString .= $this->attribute($name, $value);

        return $this->getOwner();
    }

    // The attribute strings are output as HTML: only safe attribute names, values escaped
    // (a template could pass CMS or user content as the value)
    private function attribute($name, $value = null): string
    {
        $name = preg_replace('/[^A-Za-z0-9_:.-]/', '', (string) $name);
        return $name . ($value ? '="' . Convert::raw2att((string) $value) . '"' : '');
    }

    // Allows specifying an aspect ratio when using ResponsivePicture()
    public function AddAspectRatio($width, $height)
    {
        $this->getOwner()->AspectRatio = $width / $height;
        return $this->getOwner();
    }

    // Allows using object-fit and object-cover css properties, w/ IE polyfill and FocusPoint integration
    public function ObjectFit($objectFitType = 'cover')
    {
        $this->getOwner()->IsObjectFit = true;
        $this->getOwner()->ObjectFitType = $objectFitType;
        return $this->getOwner();
    }

    public function NaturalWidth()
    {
        $this->getOwner()->IsNaturalWidth = true;
        return $this->getOwner();
    }

    // Original width will be used for html width attribute
    // Height attribute is width/aspect ratio
    public function CroppedHeight()
    {
        return $this->getOwner()->AspectRatio
            ? round($this->getOwner()->Width / $this->getOwner()->AspectRatio)
            : $this->getOwner()->Height;
    }

    public function UseWebP()
    {
        $this->getOwner()->serveWebP = true;
        return $this->getOwner();
    }
}
