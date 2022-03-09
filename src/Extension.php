<?php

namespace saosangmo\simphptwig;
/**
 * Simphp Extension class.
 *
 * This class is used by simphp as a twig extension and must not be used directly.
 *
 */
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Simphp\System\Library;

class Extension extends AbstractExtension
{
    protected $registry;
    protected $is_admin;

    /**
     * @param \Registry $registry
     */
    public function __construct(\Registry $registry) {
        $this->registry = $registry;

        $this->is_admin = defined('DIR_CATALOG');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('link', array($this, 'linkFunction')),
            new TwigFunction('lang', array($this, 'langFunction')),
            new TwigFunction('image', array($this, 'imageFunction')),
            new TwigFunction('config', array($this, 'configFunction')),
            new TwigFunction('paginate', array($this, 'paginateFunction')),
            new TwigFunction('asset', array($this, 'assetFunction')),
            new TwigFunction('load', array($this, 'loadFunction')),
            new TwigFunction('can_access', array($this, 'canAccessFunction')),
            new TwigFunction('can_modify', array($this, 'canModifyFunction')),
        );
    }

    /**
     * @param null  $route
     * @param array $args
     * @param bool  $secure
     *
     * @return string
     */
    public function linkFunction($route = null, $args = array(), $secure = false)
    {
        $url = $this->registry->get('url');
        $session = $this->registry->get('session');
        $token = isset($session->data['token']) ? $session->data['token'] : null;

        if($this->is_admin && $token) {
            $args['token'] = $token;
        }

        if(is_array($args)) {
            $args = http_build_query($args);
        }

        if(!empty($route)) {
            return $url->link($route, $args);
        }

        return !empty($args) ? HTTP_SERVER . 'index.php?' . $args : HTTP_SERVER;
    }

    /**
     * @param      $key
     * @param null $file
     *
     * @return mixed
     */
    public function langFunction($key, $file = null)
    {
        $language = $this->registry->get('lang');

        if($file) {
            $language->load($file);
        }

        return $language->get($key);
    }

    /**
     * @param      $key
     * @param null $file
     *
     * @return mixed
     */
   

    /**
     * @param      $key
     * @param null $file
     *
     * @return mixed
     */
    public function configFunction($key, $file = null)
    {
        $config = $this->registry->get('config');

        if($file) {
            $config->load($file);
        }

        return $config->get($key);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function canAccessFunction($value) {
        $user = $this->registry->get('user');

        if($user) {
            return $user->hasPermission('access',$value);
        }

        return false;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function canModifyFunction($value) {
        $user = $this->registry->get('user');

        if($user) {
            return $user->hasPermission('modify',$value);
        }

        return false;
    }

    /**
     * @param $path
     *
     * @return string
     */
    public function assetFunction($path) {
        if(!$this->is_admin) {
            if(file_exists(DIR_TEMPLATE . $this->registry->get('config')->get('config_template') . '/assets/' . $path)) {
                return 'frontend/' . $this->registry->get('config')->get('config_template') . '/assets/' . $path;
            } else if(file_exists(DIR_TEMPLATE . 'default/assets/' . $path)) {
                return 'frontend/default/assets/' . $path;
            }
        } else if(file_exists(DIR_TEMPLATE . '../' . $path)) {
            return 'oadmin/template/' . $path;
        }

        return $path;
    }

    /**
     * @param $file
     *
     * @return mixed
     */
    public function loadFunction($file) {
        $loader = $this->registry->get('load');

        return $loader->controller($file);
    }

    /**
     * @param       $total
     * @param null  $route
     * @param array $args
     * @param null  $template
     *
     * @return string
     */
    public function paginateFunction($total, $limit = 5, $route = null, $args = array(), $template = null)
    {
        $request = $this->registry->get('request');
        $page = isset($request->get['page']) ? $request->get['page'] : 1;
        $secure = $request->server['HTTPS'];

        $pagination = new \Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;

        $args['page'] = '{page}';

        $pagination->url = $this->linkFunction($route, $args, $secure);

        if($template) {
            $loader = $this->registry->get('load');

            return $loader->view($template, get_object_vars($pagination));
        } else {
            return $pagination->render();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('money', array($this, 'moneyFilter')),
            new TwigFilter('tax', array($this, 'taxFilter')),
            new TwigFilter('len', array($this, 'lenFilter')),
            new TwigFilter('wei', array($this, 'weiFilter')),
            new TwigFilter('truncate', array($this, 'truncateFilter')),
            new TwigFilter('encrypt', array($this, 'encryptFilter')),
            new TwigFilter('decrypt', array($this, 'decryptFilter')),
        );
    }

    /**
     * @param        $number
     * @param string $currency
     * @param string $value
     * @param bool   $format
     *
     * @return mixed
     */
    public function moneyFilter($number, $currency = '', $value = '', $format = true)
    {
        $lib = $this->registry->get('currency');

        return $lib->format($number, $currency, $value, $format);
    }

    /**
     * @param      $value
     * @param      $tax_class_id
     * @param bool $calculate
     *
     * @return mixed
     */
    public function taxFilter($value, $tax_class_id, $calculate = true)
    {
        $tax = $this->registry->get('tax');

        return $tax->calculate($value, $tax_class_id, $calculate);
    }

    /**
     * @param        $value
     * @param        $length_class_id
     * @param string $decimal_point
     * @param string $thousand_point
     *
     * @return mixed
     */
    public function lenFilter($value, $length_class_id, $decimal_point = '.', $thousand_point = ',')
    {
        $length = $this->registry->get('length');

        return $length->format($value, $length_class_id, $decimal_point, $thousand_point);
    }

    /**
     * @param        $value
     * @param        $weight_class_id
     * @param string $decimal_point
     * @param string $thousand_point
     *
     * @return mixed
     */
    public function weiFilter($value, $weight_class_id, $decimal_point = '.', $thousand_point = ',')
    {
        $weight = $this->registry->get('weight');

        return $weight->format($value, $weight_class_id, $decimal_point, $thousand_point);
    }

    /**
     * @param        $value
     * @param string $end
     * @param null   $limit
     *
     * @return string
     */
    public function truncateFilter($value, $end = '...', $limit = null)
    {
        $config = $this->registry->get('config');

        if( ! $limit) {
            $limit = $config->get('config_product_description_length');
        }

        $str = strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));

        if(strlen($str) > $limit) {
            $str = mb_substr($str, 0, $limit) . $end;
        }

        return  $str;
    }

    /**
     * @param        $filename
     * @param string $context
     *
     * @param null   $width
     * @param null   $height
     *
     * @return string|void
     */
    public function imageFunction($filename, $width = false, $height = false, $context = false) {
        if (!is_file(DIR_MEDIA . $filename)) {
            return;
        }

        $request = $this->registry->get('request');
        $config = $this->registry->get('config');

        if(!$width) {
            $width = $config->get('theme_image_'. $context .'_width');
        }

        if(!$height) {
            $height = $config->get('theme_image_'. $context .'_height');
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $old_image = $filename;
        $new_image = 'cache/' . mb_substr($filename, 0, mb_strrpos($filename, '.')) . '-' . $width . 'x' . $height . '.' . $extension;

        if (!is_file(DIR_MEDIA . $new_image) || (filectime(DIR_MEDIA . $old_image) > filectime(DIR_MEDIA . $new_image))) {
            $path = '';

            $directories = explode('/', dirname(str_replace('../', '', $new_image)));

            foreach ($directories as $directory) {
                $path = $path . '/' . $directory;

                if (!is_dir(DIR_MEDIA . $path)) {
                    @mkdir(DIR_MEDIA . $path, 0777);
                }
            }

            list($width_orig, $height_orig) = getimagesize(DIR_MEDIA . $old_image);
            include(DIR_SYSTEM.'library/image.php');
            
            if ($width_orig != $width || $height_orig != $height) {
                $image = new Image(DIR_MEDIA . $old_image);
                $image->resize($width, $height);
                $image->save(DIR_MEDIA . $new_image);
            } else {
                copy(DIR_MEDIA . $old_image, DIR_MEDIA . $new_image);
            }
        }

        return HTTP_MEDIA . $new_image;
    }

    /**
     * @param $value
     *
     * @return string
     */
    public function encryptFilter($value)
    {
        $config = $this->registry->get('config');

        $encription = new \Encryption($config->get('config_encription'));

        return $encription->encrypt($value);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public function decryptFilter($value)
    {
        $config = $this->registry->get('config');

        $encription = new \Encryption($config->get('config_encription'));

        return $encription->decrypt($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getGlobals()
    {
        $document = $this->registry->get('document');

        $globals = array(
            'document_title' => $document->getTitle(),
            'document_description' => $document->getDescription(),
            'document_keywords' => $document->getKeywords(),
            'document_links' => $document->getLinks(),
            'document_styles' => $document->getStyles(),
            'document_scripts' => $document->getScripts(),
            'route' => isset($this->registry->get('request')->get['route']) ? $this->registry->get('request') : '',
        );

        if($this->is_admin) {
            $user = $this->registry->get('user');
            $globals['user'] = $user;
            $globals['is_logged'] = $user->isLogged();
        } else {
            $customer = $this->registry->get('customer');
            $globals['customer'] = $customer;
            $globals['is_logged'] = $customer->isLogged();
            $globals['cart'] = $this->registry->get('cart');
        }

        return $globals;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'simphp';
    }
}

class Image {
	private $file;
	private $image;
	private $info;

	public function __construct($file) {
		if (file_exists($file)) {
			$this->file = $file;

			$info = getimagesize($file);

			$this->info = array(
				'width'  => $info[0],
				'height' => $info[1],
				'bits'   => $info['bits'],
				'mime'   => $info['mime']
			);

			$this->image = $this->create($file);
		} else {
			exit('Error: Could not load image ' . $file . '!');
		}
	}

	private function create($image) {
		$mime = $this->info['mime'];

		if ($mime == 'image/gif') {
			return imagecreatefromgif($image);
		} elseif ($mime == 'image/png') {
			return imagecreatefrompng($image);
		} elseif ($mime == 'image/jpeg') {
			return imagecreatefromjpeg($image);
		}
	}

	public function save($file, $quality = 90) {
		$info = pathinfo($file);

		$extension = strtolower($info['extension']);

		if (is_resource($this->image) || is_object($this->image)) {
			if ($extension == 'jpeg' || $extension == 'jpg') {
				imagejpeg($this->image, $file, $quality);
			} elseif($extension == 'png') {
				imagepng($this->image, $file);
			} elseif($extension == 'gif') {
				imagegif($this->image, $file);
			}

			imagedestroy($this->image);
		}
	}

	public function resize($width = 0, $height = 0, $default = '') {
		if (!$this->info['width'] || !$this->info['height']) {
			return;
		}

		$xpos = 0;
		$ypos = 0;
		$scale = 1;

		$scale_w = $width / $this->info['width'];
		$scale_h = $height / $this->info['height'];

		if ($default == 'w') {
//		if ($default == 'w' || $width/$height > $this->info['width']/$this->info['height']) {
			$scale = $scale_w;
		} elseif ($default == 'h'){
//		} elseif ($default == 'h' || $width/$height < $this->info['width']/$this->info['height']){
			$scale = $scale_h;
		} else {
			$scale = min($scale_w, $scale_h);
		}

		if ($scale == 1 && $scale_h == $scale_w && $this->info['mime'] != 'image/png') {
			return;
		}

		$new_width = (int)($this->info['width'] * $scale);
		$new_height = (int)($this->info['height'] * $scale);			
		$xpos = (int)(($width - $new_width) / 2);
		$ypos = (int)(($height - $new_height) / 2);

		$image_old = $this->image;
		if($image_old){
			$this->image = imagecreatetruecolor($width, $height);

			if (isset($this->info['mime']) && $this->info['mime'] == 'image/png') {		
				imagealphablending($this->image, false);
				imagesavealpha($this->image, true);
				$background = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
				imagecolortransparent($this->image, $background);
			} else {
				$background = imagecolorallocate($this->image, 255, 255, 255);
			}

			imagefilledrectangle($this->image, 0, 0, $width, $height, $background);

			imagecopyresampled($this->image, $image_old, $xpos, $ypos, 0, 0, $new_width, $new_height, $this->info['width'], $this->info['height']);
			imagedestroy($image_old);

			$this->info['width']  = $width;
			$this->info['height'] = $height;
		} else {
			return false;
		}
	}

	public function watermark($file, $position = 'bottomright') {
		$watermark = $this->create($file);

		$watermark_width = imagesx($watermark);
		$watermark_height = imagesy($watermark);

		switch($position) {
			case 'topleft':
				$watermark_pos_x = 0;
				$watermark_pos_y = 0;
				break;
			case 'topright':
				$watermark_pos_x = $this->info['width'] - $watermark_width;
				$watermark_pos_y = 0;
				break;
			case 'bottomleft':
				$watermark_pos_x = 0;
				$watermark_pos_y = $this->info['height'] - $watermark_height;
				break;
			case 'bottomright':
				$watermark_pos_x = $this->info['width'] - $watermark_width;
				$watermark_pos_y = $this->info['height'] - $watermark_height;
				break;
		}

		imagecopy($this->image, $watermark, $watermark_pos_x, $watermark_pos_y, 0, 0, 120, 40);

		imagedestroy($watermark);
	}

	public function crop($top_x, $top_y, $bottom_x, $bottom_y) {
		$image_old = $this->image;
		$this->image = imagecreatetruecolor($bottom_x - $top_x, $bottom_y - $top_y);

		imagecopy($this->image, $image_old, 0, 0, $top_x, $top_y, $this->info['width'], $this->info['height']);
		imagedestroy($image_old);

		$this->info['width'] = $bottom_x - $top_x;
		$this->info['height'] = $bottom_y - $top_y;
	}

	public function rotate($degree, $color = 'FFFFFF') {
		$rgb = $this->html2rgb($color);

		$this->image = imagerotate($this->image, $degree, imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]));

		$this->info['width'] = imagesx($this->image);
		$this->info['height'] = imagesy($this->image);
	}

	private function filter($filter) {
		imagefilter($this->image, $filter);
	}

	private function text($text, $x = 0, $y = 0, $size = 5, $color = '000000') {
		$rgb = $this->html2rgb($color);

		imagestring($this->image, $size, $x, $y, $text, imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]));
	}

	private function merge($file, $x = 0, $y = 0, $opacity = 100) {
		$merge = $this->create($file);

		$merge_width = imagesx($image);
		$merge_height = imagesy($image);

		imagecopymerge($this->image, $merge, $x, $y, 0, 0, $merge_width, $merge_height, $opacity);
	}

	private function html2rgb($color) {
		if ($color[0] == '#') {
			$color = substr($color, 1);
		}

		if (strlen($color) == 6) {
			list($r, $g, $b) = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);   
		} elseif (strlen($color) == 3) {
			list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);    
		} else {
			return false;
		}

		$r = hexdec($r); 
		$g = hexdec($g); 
		$b = hexdec($b);    

		return array($r, $g, $b);
	}	
	

	public function cropsize($width = 0, $height = 0):void {
	    
	    	if (!$this->info['width'] || !$this->info['height']) {
	    		return;
	    	}
        
	        //afmetingen bepalen
	        $photo_width = $this->info['width']; 
	        $photo_height = $this->info['height'];
	        
	        $new_width = $width;
	        $new_height = $height;
	        
	        //als foto te hoog is
	        if (($photo_width/$new_width) < ($photo_height/$new_height)) {
	        
	        	$from_y = ceil(($photo_height - ($new_height * $photo_width / $new_width))/2);
	        	$from_x = '0';
	        	$photo_y = ceil(($new_height * $photo_width / $new_width)); 
	        	$photo_x = $photo_width;
	        
	        }
	        
	        //als foto te breed is
	        if (($photo_height/$new_height) < ($photo_width/$new_width)) {

	        	$from_x = ceil(($photo_width - ($new_width * $photo_height / $new_height))/2);
	        	$from_y = '0';
	        	$photo_x = ceil(($new_width * $photo_height / $new_height)); 
	        	$photo_y = $photo_height;

        	}
	        
	        //als verhoudingen gelijk zijn	
	        if (($photo_width/$new_width) == ($photo_height/$new_height)) {
	        
	        	$from_x = ceil(($photo_width - ($new_width * $photo_height / $new_height))/2);
	        	$from_y = '0';
	        	$photo_x = ceil(($new_width * $photo_height / $new_height)); 
	        	$photo_y = $photo_height;
	        
	        }
	        
	        	        
	       	$image_old = $this->image;
	        $this->image = imagecreatetruecolor($width, $height);
			
			if (isset($this->info['mime']) && $this->info['mime'] == 'image/png') {		
				imagealphablending($this->image, false);
				imagesavealpha($this->image, true);
				$background = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
				imagecolortransparent($this->image, $background);
			} else {
				$background = imagecolorallocate($this->image, 255, 255, 255);
			}
			
			imagefilledrectangle($this->image, 0, 0, $width, $height, $background);
		
		
	        imagecopyresampled($this->image, $image_old, 0, 0, $from_x, $from_y, $new_width, $new_height, $photo_x, $photo_y);
	        imagedestroy($image_old);
	           
	        $this->info['width']  = $width;
	        $this->info['height'] = $height;

	    
	    }
    
    
	    public function onesize($maxsize = 0) {
	    
	    	if (!$this->info['width'] || !$this->info['height']) {
	    		return;
	    	}
        
	        //afmetingen bepalen
	        $photo_width = (int) $this->info['width']; 
	        $photo_height = (int) $this->info['height'];
 	        
	        
	        // calculate dimensions
        	if ($photo_width > $maxsize OR $photo_height > $maxsize) {
        	
        		if ($photo_width == $photo_height) {
        		
        			$width = $maxsize;
        			$height = $maxsize;
        	 	
        	 	}elseif($photo_width > $photo_height) {
        	 	
        		    	$scale = $photo_width / $maxsize;
        		  		$width = $maxsize;
        				$height = round ($photo_height / $scale);
        		
        		}else{
        		
        			$scale = $photo_height / $maxsize;
        			$height = $maxsize;
        			$width = round ($photo_width / $scale);
        		
        		}
        	
        	}else{
        	
        		$width = $photo_width;
        		$height = $photo_height;
        	
        	}
	        	
	        // and bring it all to live	        
	       	$image_old = $this->image;
	        $this->image = imagecreatetruecolor($width, $height);
			
			if (isset($this->info['mime']) && $this->info['mime'] == 'image/png') {		
				imagealphablending($this->image, false);
				imagesavealpha($this->image, true);
				$background = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
				imagecolortransparent($this->image, $background);
			} else {
				$background = imagecolorallocate($this->image, 255, 255, 255);
			}
			
			imagefilledrectangle($this->image, 0, 0, $width, $height, $background);
		
		
	        imagecopyresampled($this->image, $image_old, 0, 0, 0, 0, $width, $height, $photo_width, $photo_height);
	        imagedestroy($image_old);
	           
	        $this->info['width']  = $width;
	        $this->info['height'] = $height;

	    
	    }
}
