<?php
/**
 * Class to describe a single Ansel image.
 *
 * Copyright 2001-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @copyright 2001-2014 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/gpl GPL
 * @package Ansel
 */
/**
 * Class to describe a single Ansel image.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @copyright 2001-2014 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/gpl GPL
 * @package Ansel
 */
class Ansel_Image implements Iterator
{
    /**
     * The gallery id of this image's parent gallery
     *
     * @var integer
     */
    public $gallery;

    /**
     * Image Id
     *
     * @var integer
     */
    public $id = null;

    /**
     * The filename for this image
     *
     * @var string
     */
    public $filename = '';

    /**
     * The image title.
     *
     * @var string
     */
    public $title = 'Untitled';

    /**
     * Image caption
     *
     * @var string
     */
    public $caption = '';

    /**
     * The image's mime type
     *
     * @var string
     */
    public $type = 'image/jpeg';

    /**
     * Timestamp of uploaded datetime
     *
     * @var integer
     */
    public $uploaded;

    /**
     * Sort count for this image
     *
     * @var integer
     */
    public $sort;

    /**
     * The number of comments for this image, if available.
     *
     * @var integer
     */
    public $commentCount;

    /**
     * Number of faces in this image
     * @var integer
     */
    public $facesCount;

    /**
     * Latitude
     *
     * @var string
     */
    public $lat;

    /**
     * Longitude
     *
     * @var string
     */
    public $lng;

    /**
     * Textual location
     *
     * @var string
     */
    public $location;

    /**
     * Timestamp for when image was geotagged
     *
     * @var integer
     */
    public $geotag_timestamp;

    /**
     * Timestamp of original date.
     *
     * @var integer
     */
    public $originalDate;

    /**
     * Horde_Image object for this image.
     *
     * @var Horde_Image_Base
     */
    protected $_image;

    /**
     * Dirty flag
     *
     * @var boolean
     */
    protected $_dirty;

    /**
     * Flags for loaded views
     *
     * @var string
     */
    protected $_loaded;

    /**
     * Holds an array of tags for this image
     *
     * @var array
     */
    protected $_tags = array();

    /**
     * Cache the raw EXIF data locally
     *
     * @var array
     */
    protected $_exif = array();

    /**
     * Attribute handler
     *
     * @var Ansel_Attributes
     */
    protected $_attributes;

    /**
     * Const'r
     *
     * @param array $image  An array describing the image properties. May
     *                      contain any of the following:
     *   image_filename        - The filename (REQUIRED)
     *   image_title           - The title
     *   gallery_id -          - The gallery id the image belongs to
     *   image_caption         - The image caption
     *   image_sort            - The image sort value
     *   image_id              - The image id (empty if a new image).
     *   image_uploaded_date   - Image uploaded date
     *   image_type            - The image type (jpg/png etc...)
     *   tags                  - Comma delimited list of tags
     *   image_faces           - Faces in this image
     *   image_location        - Image location
     *   image_original_date   - Original date
     *   image_latitude        - Latitude
     *   image_longitude       - Longitude
     *   image_geotag_date     - Date image tagged
     *   data                  - Binary image data as a string or stream
     *                           resource
     *
     * @return Ansel_Image
     */
    public function __construct(array $image = array())
    {
        if (!empty($image)) {
            $this->filename = $image['image_filename'];
            if (!empty($image['image_title'])) {
                $this->title = $image['image_title'];
            }
            if  (!empty($image['gallery_id'])) {
                $this->gallery = $image['gallery_id'];
            }
            if (!empty($image['image_caption'])) {
                $this->caption = $image['image_caption'];
            }
            if (isset($image['image_sort'])) {
                $this->sort = $image['image_sort'];
            }
            if (!empty($image['image_id'])) {
                $this->id = $image['image_id'];
            }
            if (!empty($image['image_uploaded_date'])) {
                $this->uploaded = $image['image_uploaded_date'];
            } else {
                $this->uploaded = time();
            }
            if (!empty($image['image_type'])) {
                $this->type = $image['image_type'];
            }
            if (!empty($image['tags'])) {
                $this->_tags = $image['tags'];
            }
            if (!empty($image['image_faces'])) {
                $this->facesCount = $image['image_faces'];
            }

            $this->location = !empty($image['image_location']) ? $image['image_location'] : '';

            // The following may have to be rewritten by EXIF.
            // EXIF requires both an image id and a stream, so we can't
            // get EXIF data before we save the image to the VFS.
            if (!empty($image['image_original_date'])) {
                $this->originalDate = $image['image_original_date'];
            } else {
                $this->originalDate = $this->uploaded;
            }
            $this->lat = !empty($image['image_latitude']) ? $image['image_latitude'] : '';
            $this->lng = !empty($image['image_longitude']) ? $image['image_longitude'] : '';
            $this->geotag_timestamp = !empty($image['image_geotag_date']) ? $image['image_geotag_date'] : '0';
        }

        $this->_image = Ansel::getImageObject();
        $this->_image->reset();

        if (!empty($image['data'])) {
            $this->_image->loadString($image['data']);
            $this->_loaded = 'full';
        }

        $this->id = !empty($image['image_id']) ? $image['image_id'] : null;
    }

    /**
     * Return the underlying Horde_Image
     *
     * @return Horde_Image_Base
     */
    public function getHordeImage()
    {
        return $this->_image;
    }

    /**
     * Return the vfs path for this image.
     *
     * @param string $view        The view we want.
     * @param Ansel_Style $style  A gallery style.
     *
     * @return string  The vfs path for this image.
     */
    public function getVFSPath($view = 'full', Ansel_Style $style = null)
    {
        return $this->getVFSPathFromHash($this->getViewHash($view, $style));
    }

    /**
     * Generate a path on the VFS given a known style hash.
     *
     * @param string $hash  The sytle hash
     *
     * @return string the VFS path to the directory for the provided hash
     */
    public function getVFSPathFromHash($hash)
    {
         return '.horde/ansel/'
                . substr(str_pad($this->id, 2, 0, STR_PAD_LEFT), -2)
                . '/' . $hash;
    }

    /**
     * Returns the file name of this image as used in the VFS backend.
     *
     * @param string $view  The image view (full, screen, thumb, mini).
     *
     * @return string  This image's VFS file name.
     */
    public function getVFSName($view)
    {
        $vfsname = $this->id;

        if ($view == 'full' && $this->type) {
            $type = strpos($this->type, '/') === false ?
                'image/' . $this->type :
                $this->type;
            if ($ext = Horde_Mime_Magic::mimeToExt($type)) {
                $vfsname .= '.' . $ext;
            }
        } elseif (($GLOBALS['conf']['image']['type'] == 'jpeg') || $view == 'screen') {
            $vfsname .= '.jpg';
        } else {
            $vfsname .= '.png';
        }

        return $vfsname;
    }

    /**
     * Loads the given view into memory.
     *
     * @param string $view        Which view to load.
     * @param Ansel_Style $style  The gallery style.
     *
     * @throws Ansel_Exception
     */
    public function load($view = 'full', Ansel_Style $style = null)
    {
        // If this is a new image that hasn't been saved yet, we will
        // already have the full data loaded.
        if ($view == 'full' && $this->_loaded == 'full') {
            return;
        } elseif ($view == 'full') {
            $data = $this->_getVfsStream(
                $this->getVFSPath('full'),
                $this->getVFSName('full')
            );
            $viewHash = 'full';
        } else {
            $viewHash = $this->getViewHash($view, $style);
            if ($this->_loaded == $viewHash) {
                return;
            }

            $this->createView(
                $view,
                $style,
                (($view == 'screen' && $GLOBALS['prefs']->getValue('watermark_auto')) ?
                    $GLOBALS['prefs']->getValue('watermark_text', '') : '')
            );

            // If createView() had to resize the full image, we've already
            // loaded the data, so return now.
            if ($this->_loaded == $viewHash) {
                return;
            }

            // Get the VFS info.
            $vfspath = $this->getVFSPath($view, $style);

            // Read in the requested view.
            $data = $this->_getVfsStream($vfspath, $this->getVFSName($view));
        }

        // We've definitely successfully loaded the image now.
        $this->_loaded = $viewHash;
        $this->_image->loadString($data);
    }

    /**
     * Check if an image view exists and returns the vfs name complete with
     * the hash directory name prepended if appropriate.
     *
     * @param integer $id         Image id to check
     * @param string $view        Which view to check for
     * @param Ansel_Style $style  Style object
     *
     * @return mixed  False if image does not exists | string vfs name
     */
    public static function viewExists($id, $view, Ansel_Style $style)
    {
        // We cannot check empty styles since we cannot get the hash
        if (empty($style)) {
            return false;
        }

        // Get the VFS path.
        $view = $style->getHash($view);

        // Can't call the various vfs methods here, since this method is static.
        $vfspath = '.horde/ansel/'
            . substr(str_pad($id, 2, 0, STR_PAD_LEFT), -2)
            . '/'
            . $view;

        // Get VFS name
        $vfsname = $id . '.';
        if ($GLOBALS['conf']['image']['type'] == 'jpeg' || $view == 'screen') {
            $vfsname .= 'jpg';
        } else {
            $vfsname .= 'png';
        }

        if ($GLOBALS['injector']->getInstance('Horde_Core_Factory_Vfs')
                ->create('images')
                ->exists($vfspath, $vfsname)) {
            return $view . '/' . $vfsname;
        } else {
            return false;
        }
    }

    /**
     * Creates and caches the given view.
     *
     * @param string $view         Which view to create.
     * @param Ansel_Style  $style  A style object
     * @param string $watermark    A watermark to apply to screen images.
     *
     * @todo  Use a parameter array for style and watermark - maybe refactor
     *        out the watermark being done in this method at all.
     *
     * @throws Ansel_Exception
     */
    public function createView($view, Ansel_Style $style = null, $watermark = '')
    {
        global $storage, $injector;

        // Default to the gallery's style
        if (empty($style)) {
            $style = $storage->getGallery(abs($this->gallery))->getStyle();
        }

        // Get the VFS info, and check if the view already exists.
        $vfspath = $this->getVFSPath($view, $style);
        if ($injector->getInstance('Horde_Core_Factory_Vfs')
            ->create('images')
            ->exists($vfspath, $this->getVFSName($view))) {
            return;
        }

        // Doesn't exist, must create it from the full image.
        $data = $this->_getVfsStream(
            $this->getVFSPath('full'),
            $this->getVFSName('full')
        );

        // Force screen images to ALWAYS be jpegs for performance/size
        if ($view == 'screen' && $GLOBALS['conf']['image']['type'] != 'jpeg') {
            $originalType = $this->_image->setType('jpeg');
        } else {
            $originalType = false;
        }

        // Load the full image data into the image object.
        $vHash = $this->getViewHash($view, $style);
        $this->_image->loadString($data);

        // Figure out the $viewType for the generator.
        if ($view == 'thumb') {
            $viewType = $style->thumbstyle;
        } else {
            // Screen, Mini
            $viewType = ucfirst($view);
        }

        // Use a ImageGenerator to create the view type.
        try {
            $iview = Ansel_ImageGenerator::factory(
                $viewType, array('image' => $this, 'style' => $style));
        } catch (Ansel_Exception $e) {
            // If we don't support the requested effect, try ansel_default
            // before giving up.
            if ($view == 'thumb' && $viewType != 'Thumb') {
                $iview = Ansel_ImageGenerator::factory(
                    'Thumb',
                    array(
                        'image' => $this,
                        'style' => Ansel::getStyleDefinition('ansel_default')
                    )
                );
            } else {
                throw $e;
            }
        }

        // generate the view
        $iview->create();
        $this->_image->raw(false, array('stream' => true));
        $this->_loaded = $vHash;

        $injector->getInstance('Horde_Core_Factory_Vfs')
            ->create('images')
            ->writeData(
                $vfspath,
                $this->getVFSName($vHash),
                $this->_image->raw(false, array('stream' -> true)),
                true
            );

        // Watermark?
        if (!empty($watermark)) {
            $this->watermark($view);
            $this->_image->raw(false, array('stream' -> true));
            $injector->getInstance('Horde_Core_Factory_Vfs')
                ->create('images')
                ->writeData(
                    $vfspath,
                    $this->getVFSName($view),
                    $this->_image->raw(false, array('stream' -> true))
                );
        }

        // Revert any type change
        if ($originalType) {
            $this->_image->setType($originalType);
        }
    }

    /**
     * Writes the current full image data to vfs. Will not attempt to save if
     * no data at all is loaded. Normally used when saving a new image.
     *
     * @throws Ansel_Exception
     */
    protected function _writeData()
    {
        $this->_dirty = false;

        if ($this->_loaded == 'full') {
            try {
                $GLOBALS['injector']->getInstance('Horde_Core_Factory_Vfs')
                    ->create('images')
                    ->writeData(
                        $this->getVFSPath('full'),
                        $this->getVFSName('full'),
                        $this->_image->raw(),
                        true);
            } catch (Horde_Vfs_Exception $e) {
                throw new Ansel_Exception($e);
            }
        } else if (!empty($this->_loaded)) {
            throw new Ansel_Exception(
                'This method should only be called with full data loaded.'
            );
        }
    }

    /**
     * Change the image data. Deletes old cache and writes the new
     * data to the VFS. Used when updating an image
     *
     * @param string $data  The new data for this image.
     * @param string $view  If specified, the $data represents only this
     *                      particular view. Cache will not be deleted.
     *
     * @throws Ansel_Exception
     */
    public function updateData($data, $view = 'full')
    {
        /* Delete old cached data if we are replacing the full image */
        if ($view == 'full') {
            $this->deleteCache();
        }

        try {
            $GLOBALS['injector']->getInstance('Horde_Core_Factory_Vfs')
                ->create('images')->writeData(
                    $this->getVFSPath($view),
                    $this->getVFSName($view),
                    $data,
                    true);
        } catch (Horde_Vfs_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Update the image's geotag data. Saves to backend storage as well, so no
     * need to call self::save()
     *
     * @param string $lat       Latitude
     * @param string $lng       Longitude
     * @param string $location  Textual location
     *
     */
    public function geotag($lat, $lng, $location = '')
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->location = $location;
        $this->geotag_timestamp = time();
        $this->save();
    }

    /**
     * Save image details to storage.
     *
     * @throws Ansel_Exception
     */
    public function save()
    {
        global $storage;

        // Save
        $id = $storage->saveImage($this);

        // Existing image, just save and exit
        if ($this->id) {
            return;
        }

        // New image, need to save the image files
        $this->id = $id;

        // The EXIF functions require a stream, need to save before we read
        $this->_writeData();

        // Get the EXIF data if we are not a gallery key image.
        if ($this->gallery > 0) {
            $needUpdate = $this->getEXIF();
        }

        // Create tags from exif data if desired
        $fields = unserialize($GLOBALS['prefs']->getValue('exif_tags'));
        if ($fields) {
            $this->_exifToTags($fields);
        }

        // Save the tags
        if (count($this->_tags)) {
            try {
                $this->setTags($this->_tags);
            } catch (Exception $e) {
                // Since we got this far, the image has been added, so
                // just log the tag failure.
                Horde::log($e, 'ERR');
            }
        }

        // Save again if EXIF changed any values
        if (!empty($needUpdate)) {
            $storage->saveImage($this);
        }

        return $this->id;
    }

    /**
     * Replace this image's image data.
     *
     * @param array $imageData  An array of image data, the same keys as Const'r
     *
     * @return void
     * @throws Ansel_Exception
     */
    public function replace(array $imageData)
    {
        // Reset the data array and remove all cached images
        $this->reset();
        $this->getEXIF(true);
        $this->updateData($imageData);
    }

    /**
     * Adds specified EXIF fields to this image's tags.
     * Called during image upload/creation.
     *
     * @param array $fields  An array of EXIF fields to import as a tag.
     *
     * @return void
     */
    protected function _exifToTags(array $fields = array())
    {
        $tags = array();
        foreach ($fields as $field) {
            if (!empty($this->_exif[$field])) {
                if (substr($field, 0, 8) == 'DateTime') {
                    $d = new Horde_Date(strtotime($this->_exif[$field]));
                    $tags[] = $d->format("Y-m-d");
                } elseif ($field == 'Keywords') {
                    $tags = array_merge($tags, explode(',', $this->_exif[$field]));
                } else {
                    $tags[] = $this->_exif[$field];
                }
            }
        }

        $this->_tags = array_merge($this->_tags, $tags);
    }

    /**
     * Reads the EXIF data from the image, caches in the object and writes to
     * storage. Also populates any local properties that come from the EXIF
     * data.
     *
     * @param boolean $replacing  Set to true if we are replacing the exif data.
     *
     * @return boolean  True if any local properties were modified, False if not.
     * @throws Ansel_Exception
     */
    public function getEXIF($replacing = false)
    {
        /* Clear the local copy */
        $this->_exif = array();

        /* Get the data */
        try {
            $imageFile = $GLOBALS['injector']
                ->getInstance('Horde_Core_Factory_Vfs')
                ->create('images')
                ->readFile(
                    $this->getVFSPath('full'),
                    $this->getVFSName('full'));
        } catch (Horde_Vfs_Exception $e) {
            throw new Ansel_Exception($e);
        }

        try {
            $exif_fields = $this->_getAttributeObject()
                ->getImageExifData($imageFile);
        } catch (Ansel_Exception $e) {
            // Log the error, but it's not the end of the world, so just ignore
            Horde::log($e, 'ERR');
            $exif_fields = array();
            return false;
        }
        $this->_exif = $this->_getAttributeObject()
            ->imageAttributes($exif_fields, $replacing);

        // Populate any local properties that come from EXIF
        if (!empty($exif_fields['GPSLatitude'])) {
            $this->lat = $exif_fields['GPSLatitude'];
            $this->lng = $exif_fields['GPSLongitude'];
            $this->geotag_timestamp = time();
        }

        if (!empty($exif_fields['DateTimeOriginal'])) {
            $this->originalDate = $exif_fields['DateTimeOriginal'];
        }

        // Overwrite any existing value for title and caption with exif data
        $exif_title = $this->_getAttributeObject()->getTitle();
        $this->title = empty($exif_title) ? $this->filename : $exif_title;
        if ($exif_caption = $this->_getAttributeObject()->getCaption()) {
            $this->caption = $exif_caption;
        }

        // Attempt to autorotate based on Orientation field
        if (!empty($exif_fields['Orientation'])) {
            $this->_autoRotate($exif_fields['Orientation']);
        }

        return true;
    }

    /**
     * Autorotate based on EXIF orientation field. Updates the data in memory
     * only.
     */
    protected function _autoRotate($orientation)
    {
        if (!empty($orientation) && $orientation != 1) {
            switch ($orientation) {
            case 2:
                $this->mirror();
                break;

            case 3:
                $this->rotate('full', 180);
                break;

            case 4:
                $this->mirror();
                $this->rotate('full', 180);
                break;

            case 5:
                $this->flip();
                $this->rotate('full', 90);
                break;

            case 6:
                $this->rotate('full', 90);
                break;

            case 7:
                $this->mirror();
                $this->rotate('full', 90);
                break;

            case 8:
                $this->rotate('full', 270);
                break;
            }

            if ($this->_dirty) {
                $this->raw(false, array('stream' -> true));
                $this->_writeData();
            }
        }
    }

    /**
     * Reset the image, removing all loaded views.
     *
     */
    public function reset()
    {
        $this->_image->reset();
        $this->_loaded = false;
    }

    /**
     * Deletes the specified cache file.
     *
     * If none is specified, deletes all of the cache files.
     *
     * @param string $view  Which cache file to delete.
     */
    public function deleteCache($view = 'all')
    {
        // Delete cached screen image.
        if ($view == 'all' || $view == 'screen') {
            try {
                $GLOBALS['injector']
                    ->getInstance('Horde_Core_Factory_Vfs')
                    ->create('images')
                    ->deleteFile(
                        $this->getVFSPath('screen'),
                        $this->getVFSName('screen'));
            } catch (Horde_Vfs_Exception $e   ) {}
        }

        // Delete cached mini image.
        if ($view == 'all' || $view == 'mini') {
            try {
                $GLOBALS['injector']
                    ->getInstance('Horde_Core_Factory_Vfs')
                    ->create('images')
                    ->deleteFile(
                        $this->getVFSPath('mini'),
                        $this->getVFSName('mini'));
            } catch (Horde_Vfs_Exception $e) {}
        }
        if ($view == 'all' || $view == 'thumb') {
            $hashes = $GLOBALS['storage']->getHashes();
            foreach ($hashes as $hash) {
                try {
                    $GLOBALS['injector']->getInstance('Horde_Core_Factory_Vfs')
                        ->create('images')
                        ->deleteFile(
                            $this->getVFSPathFromHash($hash),
                            $this->getVFSName('thumb'));
                } catch (Horde_Vfs_Exception $e) {}
            }
        }
    }

    /**
     * Returns the raw data for the given view.
     *
     * @param string $view  Which view to return.
     *
     * @return string  The raw binary image data
     */
    public function raw($view = 'full', $params = array())
    {
        if ($this->_dirty) {
            $data = $this->_image->raw(false, $params);
            $this->reset();
            return $data;
        } else {
            $this->load($view);
            return $this->_image->raw(false, $params);
        }
    }

    /**
     * Sends the correct HTTP headers to the browser to download this image.
     *
     * @param string $view  The view to download.
     */
    public function downloadHeaders($view = 'full')
    {
        global $browser, $conf;

        $filename = $this->filename;
        if ($view != 'full') {
            if ($ext = Horde_Mime_Magic::mimeToExt('image/' . $conf['image']['type'])) {
                $filename .= '.' . $ext;
            }
        }

        $browser->downloadHeaders($filename);
    }

    /**
     * Display the requested view.
     *
     * @param string $view        Which view to display.
     * @param Ansel_Style $style  Force use of this gallery style.
     *
     * @throws Horde_Exception_PermissionDenied, Ansel_Exception
     */
    public function display($view = 'full', Ansel_Style $style = null)
    {
        global $storage;

        if ($view == 'full' && !$this->_dirty) {
            // Check full photo permissions
            $gallery = $storage->getGallery(abs($this->gallery));

            if (!$gallery->canDownload()) {
                throw Horde_Exception_PermissionDenied(
                    _("Access denied downloading photos from this gallery."));
            }

            $data = $this->_getVfsStream(
                $this->getVFSPath('full'),
                $this->getVFSName('full')
            );
            $output = fopen('php://output', 'w');
            while (!feof($data)) {
                fwrite($output, fread($data, 8192));
            }
        } elseif (!$this->_dirty) {
            $this->load($view, $style);
            $this->_image->display();
        } else {
            $this->_image->display();
        }
    }

    /**
     * Wraps the given view into a file.
     *
     * @param string $view  Which view to wrap up.
     *
     * @return string  Path to temporary file.
     * @deprecated Not used anywhere, remove in A4.
     */
    public function toFile($view = 'full')
    {
        $this->load($view);

        // @TODO: This logic looks broken to me. SHould probably be
        // just always $this->_image->toFile();
        return $this->_image->toFile(
            $this->_dirty ?
                null :
                $this->_image->raw(false, array('stream' => true)));
    }

    /**
     * Returns the dimensions of the given view.
     *
     * @param string $view  The view (full, screen etc..) to get dimensions for
     *
     * @return array  A hash of 'width and 'height' dimensions.
     */
    public function getDimensions($view = 'full')
    {
        $this->load($view);
        return $this->_image->getDimensions();
    }

    /**
     * Rotates the image.
     *
     * @param string $view    The view (size) to work with.
     * @param integer $angle  What angle to rotate the image by.
     * @todo Ansel 4: reverse order of parameters so we can make $angle required
     *       but $view optional. In fact, view can be taken out entirely as
     *       we only ever need to rotate the full image anyway.
     */
    public function rotate($view = 'full', $angle = 90)
    {
        $this->load($view);
        $this->_dirty = true;
        $this->_image->rotate($angle);
    }

    /**
     * Crop this image to desired dimensions. Crops the currently loaded
     * view present in the Horde_Image object.
     *
     * @see Horde_Image_Base::crop for explanation of parameters
     *
     * @param integer $x1
     * @param integer $y1
     * @param integer $x2
     * @param integer $y2
     *
     * @throws Ansel_Exception
     */
    public function crop($x1, $y1, $x2, $y2)
    {
        $this->_dirty = true;
        try {
            $this->_image->crop($x1, $y1, $x2, $y2);
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Resize the current image.
     *
     * @param integer $width        The new width.
     * @param integer $height       The new height.
     * @param boolean $ratio        Maintain original aspect ratio.
     * @param boolean $keepProfile  Keep the image meta data.
     *
     * @throws Ansel_Exception
     */
    public function resize($width, $height, $ratio = true, $keepProfile = false)
    {
        try {
            $this->_image->resize($width, $height, $ratio, $keepProfile);
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Converts the image to grayscale.
     *
     * @param string $view The view (screen, full, etc...) to work with.
     *
     * @throws Ansel_Exception
     */
    public function grayscale($view = 'full')
    {
        $this->load($view);
        $this->_dirty = true;
        try {
            $this->_image->grayscale();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Watermarks the image.
     *
     * @param string $view       The view (size) to work with.
     * @param string $watermark  String to use as the watermark.
     * @param string $halign     Horizontal alignment (Left, Right, Center)
     * @param string $valign     Vertical alignment (Top, Center, Bottom)
     * @param string $font       The font to use (not all image drivers will
     *                           support this).
     *
     * @throws Ansel_Exception
     */
    public function watermark($view = 'full', $watermark = null, $halign = null,
            $valign = null, $font = null)
    {
        if (empty($watermark)) {
            $watermark = $GLOBALS['prefs']->getValue('watermark_text');
        }
        if (empty($halign)) {
            $halign = $GLOBALS['prefs']->getValue('watermark_horizontal');
        }
        if (empty($valign)) {
            $valign = $GLOBALS['prefs']->getValue('watermark_vertical');
        }
        if (empty($font)) {
            $font = $GLOBALS['prefs']->getValue('watermark_font');
        }
        if (empty($watermark)) {
            $identity = $GLOBALS['injector']
                ->getInstance('Horde_Core_Factory_Identity')
                ->create();
            $name = $identity->getValue('fullname');
            if (empty($name)) {
                $name = $GLOBALS['registry']->getAuth();
            }
            $watermark = sprintf(_("(c) %s %s"), date('Y'), $name);
        }

        $this->load($view);
        $this->_dirty = true;
        $params = array(
            'text' => $watermark,
            'halign' => $halign,
            'valign' => $valign,
            'fontsize' => $font);
        if (!empty($GLOBALS['conf']['image']['font'])) {
            $params['font'] = $GLOBALS['conf']['image']['font'];
        }

        try {
            $this->_image->addEffect('TextWatermark', $params);
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Flips the image.
     *
     * @param string $view The view to work with.
     *
     * @throws Ansel_Exception
     */
    public function flip($view = 'full')
    {
        $this->load($view);
        $this->_dirty = true;

        try {
            $this->_image->flip();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Mirrors the image.
     *
     * @param string $view The view (size) to work with.
     *
     * @throws Ansel_Exception
     */
    public function mirror($view = 'full')
    {
        $this->load($view);
        $this->_dirty = true;
        try {
            $this->_image->mirror();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Add an effect to the effect stack
     *
     * @param string $type    The effect to add.
     * @param array  $params  The effect parameters.
     *
     * @throws Ansel_Exception
     */
    public function addEffect($type, $params = array())
    {
        try {
            $this->_image->addEffect($type, $params);
        } catch (Horde_Image_Exception $e) {
            Horde::log($e, 'ERR');
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Apply any pending effects to the underlaying Horde_Image
     *
     * @throws Ansel_Exception
     */
    public function applyEffects()
    {
        try {
            $this->_image->applyEffects();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Returns this image's tags.
     *
     * @see Ansel_Tags::readTags()
     *
     * @return array  An array of tags
     * @throws Horde_Exception_PermissionDenied, Ansel_Exception
     */
    public function getTags()
    {
        if (count($this->_tags)) {
            return $this->_tags;
        }
        $gallery = $GLOBALS['storage']->getGallery($this->gallery);
        if ($gallery->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::READ)) {
            return $GLOBALS['injector']->getInstance('Ansel_Tagger')
                ->getTags($this->id, 'image');
        } else {
            throw new Horde_Exception_PermissionDenied(
                _("Access denied viewing this photo."));
        }
    }

    /**
     * Either add or replace this image's tags.
     *
     * @param array $tags       An array of tag names
     * @param boolean $replace  Replace all tags with those provided.
     *
     * @throws Horde_Exception_PermissionDenied
     */
    public function setTags(array $tags, $replace = true)
    {
        $gallery = $GLOBALS['storage']->getGallery(abs($this->gallery));
        if ($gallery->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::EDIT)) {
            $this->_tags = array();

            if ($replace) {
                $GLOBALS['injector']
                    ->getInstance('Ansel_Tagger')
                    ->replaceTags(
                        (string)$this->id,
                        $tags,
                        $gallery->get('owner'),
                        'image');
            } else {
                $GLOBALS['injector']
                    ->getInstance('Ansel_Tagger')
                    ->tag(
                        (string)$this->id,
                        $tags,
                        $gallery->get('owner'),
                        'image');
            }
        } else {
            throw new Horde_Exception_PermissionDenied(_("Access denied adding tags to this photo."));
        }
    }

    /**
     * Remove a single tag from this image's tag collection
     *
     * @param string $tag  The tag name to remove.
     */
    public function removeTag($tag)
    {
        $gallery = $GLOBALS['storage']->getGallery(abs($this->gallery));
        if ($gallery->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::EDIT)) {
            $GLOBALS['injector']
                ->getInstance('Ansel_Tagger')
                ->untag(
                    (string)$this->id,
                    $tag);
        }
    }

    /**
     * Get the Ansel_View_Image_Thumb object
     *
     * @param Ansel_Gallery $parent  The parent Ansel_Gallery object.
     * @param Ansel_Style   $style   A gallery definition to use.
     * @param boolean       $mini    Force the use of a mini thumbnail?
     * @param array         $params  Any additional parameters the Ansel_Tile
     *                               object may need.
     *
     * @return string  HTML for this image's view tile.
     */
    public function getTile(Ansel_Gallery $parent = null,
                            Ansel_Style $style = null,
                            $mini = false,
                            array $params = array())
    {
        if (!is_null($parent) && is_null($style)) {
            $style = $parent->getStyle();
        }

        return Ansel_Tile_Image::getTile($this, $style, $mini, $params);
    }

    /**
     * Get the image type for the requested view.
     *
     * @return string  The requested view's mime type
     */
    public function getType($view = 'full')
    {
        if ($view == 'full') {
            return $this->type;
        } elseif ($view == 'screen') {
            return 'image/jpeg';
        } else {
            return 'image/' . $GLOBALS['conf']['image']['type'];
        }
    }

    /**
     * Return a hash key for the given view and style.
     *
     * @param string $view        The view (thumb, prettythumb etc...)
     * @param Ansel_Style $style  The style.
     *
     * @return string  A md5 hash suitable for use as a key.
     */
    public function getViewHash($view, Ansel_Style $style = null)
    {
        // These views do not care about style...just return the $view value.
        if ($view == 'screen' || $view == 'mini' || $view == 'full') {
            return $view;
        }

        if (is_null($style)) {
            $gallery = $GLOBALS['storage']->getGallery(abs($this->gallery));
            $style = $gallery->getStyle();
        }

        return $style->getHash($view);
    }

    /**
     * Get the image attributes from the backend.
     *
     * @return array  A hash of Exif fieldnames => values.
     */
    public function getAttributes()
    {
        return $this->_getAttributeObject()->getAttributes();
    }

    /**
     * Indicates if this image represents a multipage image.
     *
     * @return boolean
     * @throws Ansel_Exception
     */
    public function isMultiPage()
    {
        $this->load();
        try {
            return $this->_image->getImagePageCount() > 1;
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Get the number of pages that a multipage image contains.
     *
     * @return integer  The number of pages.
     * @throws Ansel_Exception
     */
    public function getImagePageCount()
    {
        // @TODO: Should we clone the current image and replace it?
        if ($this->_loaded != 'full') {
            $this->load();
        }

        try {
            return $this->_image->getImagePageCount();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Return this image representated in JSON.
     *
     * @param Ansel_Style $style  The style definition
     *
     * @return stdClass  The image object.
     */
    public function toJson($style = null)
    {
        global $conf, $registry, $injector;

        $gallery = $GLOBALS['storage']->getGallery($this->gallery);
        // @TODO Deprecate tiny
        $tiny = $conf['image']['tiny'] &&
                ($conf['vfs']['src'] == 'direct' || $gallery->hasPermission($registry->getAuth(), Horde_Perms::READ));

        // Need to include different sized images?
        $i = new StdClass();
        $i->id = $this->id;
        $i->url = Ansel::getImageUrl($this->id, 'thumb', false, $style)->toString(true);
        $i->screen = Ansel::getImageUrl($this->id, 'screen', $tiny, Ansel::getStyleDefinition('ansel_default'))->toString(true);
        $i->fn = $this->filename;
        $i->t = $this->title;
        $i->c = $injector->getInstance('Horde_Core_Factory_TextFilter')->filter($this->caption, 'text2html', array('parselevel' => Horde_Text_Filter_Text2html::NOHTML));

        $dim = $this->getDimensions('screen');
        $i->width_s = $dim['width'];
        $i->height_s = $dim['height'];

        $i->tags = array_values($this->getTags());
        $i->d = (string)new Horde_Date($this->originalDate);
        $i->l = $this->location;

        return $i;
    }

    /**
     * Reset the iterator to the first image in the set.
     *
     * @return void
     * @throws Ansel_Exception
     */
    public function rewind()
    {
        // @TODO should we save/reset the image?
        if ($this->_loaded != 'full') {
            $this->load();
        }
        try {
            $this->_image->rewind();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Return the current image from the internal iterator.
     *
     * @return Ansel_Image
     */
    public function current()
    {
        if ($this->_loaded != 'full') {
            $this->load();
        }
        try {
            return $this->_buildImageObject($this->_image->current());
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Get the index of the internal iterator.
     *
     * @return integer
     * @throws Ansel_Exception
     */
    public function key()
    {
        if ($this->_loaded != 'full') {
            $this->load();
        }
        try {
            return $this->_image->key();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Advance the iterator
     *
     * @return mixed Ansel_Image or false if not valid()
     */
    public function next()
    {
        if ($this->_loaded != 'full') {
            $this->load();
        }
        if ($next = $this->_image->next()) {
            return $this->_buildImageObject($next);
        }

        return false;
    }

    /**
     * Deterimines if the current iterator item is valid.
     *
     * @return boolean
     * @throws Ansel_Exception
     */
    public function valid()
    {
        if ($this->_loaded != 'full') {
            $this->load();
        }
        try {
            return $this->_image->valid();
        } catch (Horde_Image_Exception $e) {
            throw new Ansel_Exception($e);
        }
    }

    /**
     * Build an Ansel_Image from a given Horde_Image.
     * Used to wrap iterating the Horde_Image
     *
     * @param Horde_Image_Base $image  The Horde_Image
     *
     * @return Ansel_Image
     */
    protected function _buildImageObject(Horde_Image_Base $image)
    {
        $params = array(
                'image_filename' => $this->filename,
                'data' => $image->raw(false, array('stream' -> true)),
        );
        $newImage = new Ansel_Image($params);

        return $newImage;
    }

    protected function _getAttributeObject()
    {
        if (empty($this->_attributes)) {
            $this->_attributes = new Ansel_Attributes($this->id);
        }

        return $this->_attributes;
    }

    /**
     * Returns a stream containing the requested image view-type.
     *
     * @param string $path  The VFS path to the image file.
     * @param string $name  The VFS name of the image file.
     *
     * @return Horde_Stream
     * @throws  Ansel_Exception
     */
    protected function _getVfsStream($path, $name)
    {
        global $injector;

        $vfs = $injector
            ->getInstance('Horde_Core_Factory_Vfs')
            ->create('images');

        // If we can get a stream directly from the VFS, use that.
        if (is_callable(array($vfs, 'readStream'))) {
            return $vfs->readStream($path, $name);
        }

        $stream = new Horde_Stream_Temp();
        try {
            $stream->add($vfs->read($path, $name), true);
        } catch (Horde_Vfs_Exception $e) {
            Horde::log($e, 'ERR');
            throw new Ansel_Exception($e);
        }

        return $stream;
    }

}
