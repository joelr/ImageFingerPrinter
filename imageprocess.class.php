<?php


error_reporting(E_ALL);
set_time_limit (0);
class FingerPrintImage
{
    // property declaration
	public $resize_width = 100;
	public $resize_height = 100;
	public $div = 140;
	//public $div = 100;
	public $img;
	public $resized;
	public $width;
	public $height;
	public $pixels;
	public $pixels_processed;
	public $pixels_processed_grey;
	public $pixels_mode;
	public $max;
	public $min;
	public $pixels_list_grey = array();
    public function displayVar() {
        echo $this->var;
    }

	public function loadimage($image) {
		$this->img = imagecreatefromjpeg($image);
		// Get new sizes
		list($this->width, $this->height) = getimagesize($image);
	}
	
	function array_mode(array $set)
	{
	$counts = array_count_values($set);
	$modes  = array_keys($counts, current($counts), TRUE);
 
	// If each value only occurs once, there is no mode
	if (count($set) === count($counts))
		return FALSE;
 
	// Only one modal value
	if (count($modes) === 1)
		return $modes[0];
 
	// Multiple modal values
	return $modes[0];
	}
	
	function resize() {
		$this->resized = imagecreatetruecolor($this->resize_width, $this->resize_height);
		imagecopyresized($this->resized, $this->img, 0, 0, 0, 0, $this->resize_width, $this->resize_height, $this->width, $this->height);
		
		imagejpeg($this->resized, 'output.jpg');
	}
	
	function process_pixels()
	{
	$this->min = 255;
	$this->max = 0;
		for ($x=0;$x < $this->resize_width;$x++)
		{
			for ($y=0;$y< $this->resize_height;$y++)
			{
				$rgb = imagecolorat($this->resized, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$this->pixels[$x][$y] = array($r, $g, $b);
				
				$grey = round(($r + $g + $b)/(3));
				if ($grey > $this->max)
					$this->max = $grey;
				if ($grey < $this->min)
					$this->min = $grey;
					
				$this->pixels_processed_grey[$x][$y] = $grey;
			}
		}
		$this->normalize_pixels();
		//$this->median_pixels();
		$this->further_process();
	}
	
	
	
	function median_pixels()
	{
	
	for ($x=0;$x < $this->resize_width;$x++)
		{
			for ($y=0;$y< $this->resize_height;$y++)
			{
				$cols = $this->pixels[$x][$y];
				$r = $cols[0];
				$g = $cols[1];
				$b = $cols[2];
				$this->pixels_processed[$x][$y] = array(round($r/$this->div), round($g/$this->div), round($b/$this->div));
				$grey = round(($r + $g + $b)/($this->div*3));
				$this->pixels_processed_grey[$x][$y] = $grey;
				
				//array_push($this->pixels_list_grey, $grey);
			}
		}	
	
	
	}
	
	function normalize_pixels()
	{
	$range = $this->max - $this->min;
	
	$div = $range / 255;
	//echo $range." ".$div." <b>max:".round(($this->max - $this->min) / $div)." min: ".round(($this->min - $this->min) / $div)." max:".$this->max." min:". $this->min."<br />";
	for ($x=0;$x < $this->resize_width;$x++)
		{
			for ($y=0;$y< $this->resize_height;$y++)
			{
				//$this->pixels_processed_grey[$x][$y] = round($this->pixels_processed_grey[$x][$y] / $div);
				$val = $this->pixels_processed_grey[$x][$y] ;
				$val = $val - $this->min;
				$val = round($val / $div / $this->div);
				//if ($val < 0 or $val > 255)
				//	echo "ERROR: ".$val;
				$this->pixels_processed_grey[$x][$y] = $val;
				//echo $this->pixels_processed_grey[$x][$y]  ."<br />";
			}
		}
	
	
	
	}
	
	
	
	function further_process()
	{
		//0,0  -> 9,9    10,0  ->  19, 9  
		
		for ($yy =0; $yy < 100; $yy = $yy + 25)
		{
			for ($xx = 0; $xx < 100;$xx = $xx + 25)
			{		
				$pixels = array();
				
				for ($x=$xx; $x < ($xx +25); $x++)
				{
					for ($y=$yy; $y < ($yy +25); $y++)
					{
						array_push($pixels, (int)$this->pixels_processed_grey[$x][$y]);		
					}
				}
				$modal = $this->array_mode($pixels);
				array_push($this->pixels_list_grey, $modal);
			//	echo $modal."<br />";
			}
		}
	
	}
	
	function create_hash($data)
	{
		return md5($data);
	}
	
	public function set_threshold($var)
	{
		$this->div = $var;
	}
	
	public function view_fingerprint($file = null)
	{
		$sizefactor = 1;
		$image = imagecreatetruecolor($this->resize_width*$sizefactor, $this->resize_height*$sizefactor);
		for ($x=0;$x < ($this->resize_width*$sizefactor);$x++)
		{
			for ($y=0;$y< ($this->resize_height * $sizefactor);$y++)
			{
				$col = round($this->pixels_processed_grey[($x/$sizefactor)][($y/$sizefactor)] * ($this->div));
				
				$color = imagecolorallocate($image, $col, $col, $col);
			    imagesetpixel($image, round($x),round($y), $color); 
			}
			
		}

	//	header('Content-Type: image/jpeg');
		
		imagejpeg($image,$file, 100);
	}
	
	public function get_hash() {

		$data = $this->pixels_list_grey;
		$data_string = implode("|", $data);		
//		return $data_string;
		return $this->create_hash($data_string);
	}
	
	public function lazyhash($image) {
		$this->pixels_list_grey = array();
		$this->loadimage($image);
		$this->resize();
		$this->process_pixels();
		return $this->get_hash();
	}
	
	
	
	public function do_tests($control, $tests,$extension = ".jpg", $show_prints = false)
	{
	global $failures;
		$control_hash =  $this->lazyhash($control.$extension);		
		$this->view_fingerprint($control."_p".$extension);
		//echo $control_hash."<br />";
		echo "<br /><img src='".$control."_p".$extension."' /> Control<br /><br />";
		
		foreach($tests as $test)
		{
			$hash =  $this->lazyhash($test.$extension);
			$this->view_fingerprint($test."_p".$extension);
			$result = (($hash == $control_hash) ? "Correct" : "Failure");
			if ($hash != $control_hash)
			{
				$failures++;
				echo "failure on ".$test."<br />";
			}
			echo $hash."<br />";;
			echo "<img src='".$test."_p".$extension."' /> ".$result."<br /><br />";
		}
	}
	
}


$a = new FingerPrintImage();


//$failures = 0;
//for ($f =120;$f<=126;$f=$f+1)
//{
$failures=0;
$a->set_threshold(122);
$a->do_tests('c1', array('c2','c3','c4'));
$a->do_tests('b1', array('b2','b3','b4'));
$a->do_tests('b_stretch1', array('b_stretch2','b_stretch3','b_stretch4'));
$a->do_tests('beach', array('beach_noise','beach_blur','beach_sharp','beach_selective_blur','beach_grid'));
$a->do_tests('gradient1', array('gradient2','gradient3','gradient4'));
$a->do_tests('faint_gradient1', array('faint_gradient2','faint_gradient3','faint_gradient4'));

echo " ".$failures." <br />";

//}
//122





//$a->view_fingerprint();


?>